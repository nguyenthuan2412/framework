<?php

namespace Ryra\Core\Grpc\Client;

use Ryra\Core\Grpc\Exception\GrpcException;
use Ryra\Core\Grpc\Metadata\MetadataFactory;
use Ryra\Core\Grpc\Protobuf\Codec;
use Ryra\Core\Grpc\Protobuf\Frame;
use Ryra\Core\Grpc\Server\Code;
use Swoole\Coroutine;
use Swoole\Coroutine\Channel;
use Swoole\Coroutine\Http2\Client as Http2Client;
use Swoole\Http2\Request as Http2Request;

/**
 * gRPC client chạy trên coroutine của Swoole (HTTP/2), có pool kết nối.
 *
 *   $client = new GrpcClient('127.0.0.1:9501', $codec);
 *   $user = $client->unary('/user.v1.UserService/GetUser', new GetUserRequest(1), User::class);
 *
 *   // hoặc gọi qua service class / interface có #[Service]
 *   $users = $client->service(UserService::class);
 *   $user = $users->getUser(new GetUserRequest(1));
 */
class GrpcClient
{
    private readonly string $host;

    private readonly int $port;

    private ?Channel $pool = null;

    private int $created = 0;

    /**
     * @param array{
     *     ssl?: bool,
     *     timeout?: float,
     *     connect_timeout?: float,
     *     metadata?: array<string, string>,
     *     max_connections?: int,
     *     ssl_host_name?: string,
     *     ssl_verify_peer?: bool,
     *     ssl_cafile?: string,
     *     ssl_cert_file?: string,
     *     ssl_key_file?: string,
     * } $options
     */
    public function __construct(
        string $address,
        private readonly Codec $codec,
        private readonly array $options = [],
    ) {
        $address = \preg_replace('#^(grpcs?|https?)://#', '', $address);
        [$host, $port] = \array_pad(\explode(':', $address, 2), 2, null);
        $this->host = $host;
        $this->port = (int) ($port ?? (($options['ssl'] ?? false) ? 443 : 80));
    }

    /**
     * Tạo proxy gọi rpc theo tên method PHP của service.
     *
     * @template T
     * @param class-string<T> $service
     * @return T&ServiceStub
     */
    public function service(string $service): ServiceStub
    {
        return new ServiceStub($this, MetadataFactory::forService($service));
    }

    /**
     * @template T of object
     * @param class-string<T> $responseClass
     * @param array<string, string> $metadata
     * @return T
     */
    public function unary(string $path, object $request, string $responseClass, array $metadata = [], ?float $timeout = null): object
    {
        $messages = $this->call($path, [$request], $responseClass, $metadata, $timeout);
        if (\count($messages) !== 1) {
            throw new GrpcException(Code::INTERNAL, 'Unary rpc returned ' . \count($messages) . ' messages');
        }
        return $messages[0];
    }

    /**
     * Server streaming: trả về danh sách message (được buffer cho đến khi stream kết thúc).
     *
     * @template T of object
     * @param class-string<T> $responseClass
     * @return \Generator<int, T>
     */
    public function serverStream(string $path, object $request, string $responseClass, array $metadata = [], ?float $timeout = null): \Generator
    {
        yield from $this->call($path, [$request], $responseClass, $metadata, $timeout);
    }

    /**
     * Client streaming: gửi nhiều message, nhận về một response.
     *
     * @template T of object
     * @param iterable<object> $requests
     * @param class-string<T> $responseClass
     * @return T
     */
    public function clientStream(string $path, iterable $requests, string $responseClass, array $metadata = [], ?float $timeout = null): object
    {
        $messages = $this->call($path, $requests, $responseClass, $metadata, $timeout);
        return $messages[0] ?? throw new GrpcException(Code::INTERNAL, 'Client streaming rpc returned no message');
    }

    /**
     * @return object[]
     */
    public function call(string $path, iterable $requests, string $responseClass, array $metadata = [], ?float $timeout = null): array
    {
        if (Coroutine::getCid() < 0) {
            $result = null;
            $error = null;
            Coroutine\run(function () use (&$result, &$error, $path, $requests, $responseClass, $metadata, $timeout) {
                try {
                    $result = $this->doCall($path, $requests, $responseClass, $metadata, $timeout);
                } catch (\Throwable $e) {
                    $error = $e;
                }
            });
            if ($error !== null) {
                throw $error;
            }
            return $result;
        }

        return $this->doCall($path, $requests, $responseClass, $metadata, $timeout);
    }

    private function doCall(string $path, iterable $requests, string $responseClass, array $metadata, ?float $timeout): array
    {
        $timeout ??= (float) ($this->options['timeout'] ?? 5.0);

        $body = '';
        foreach ($requests as $request) {
            $body .= Frame::pack($this->codec->encode($request));
        }

        $http2Request = new Http2Request();
        $http2Request->method = 'POST';
        $http2Request->path = $path;
        $http2Request->headers = \array_merge(
            \array_change_key_case($this->options['metadata'] ?? [], CASE_LOWER),
            \array_change_key_case($metadata, CASE_LOWER),
            [
                'content-type' => 'application/grpc+proto',
                'te' => 'trailers',
                'user-agent' => 'grpc-php-ryra/1.0',
                'grpc-timeout' => \max(1, (int) \ceil($timeout * 1000)) . 'm',
            ],
        );
        $http2Request->data = $body;

        $client = $this->acquire();
        $healthy = false;
        try {
            if ($client->send($http2Request) === false) {
                throw new GrpcException(Code::UNAVAILABLE, "Cannot send request to {$this->host}:{$this->port}: {$client->errMsg}");
            }
            $response = $client->recv($timeout);
            if ($response === false || $response === null) {
                $code = $client->errCode === SOCKET_ETIMEDOUT ? Code::DEADLINE_EXCEEDED : Code::UNAVAILABLE;
                throw new GrpcException($code, "No response from {$this->host}:{$this->port}: {$client->errMsg}");
            }
            $healthy = true;
        } finally {
            $this->release($client, $healthy);
        }

        $headers = $response->headers ?? [];
        $status = isset($headers['grpc-status']) ? Code::tryFrom((int) $headers['grpc-status']) ?? Code::UNKNOWN : null;

        if ($status === null) {
            $status = $response->statusCode === 200 ? Code::UNKNOWN : self::statusFromHttp($response->statusCode);
            throw new GrpcException($status, "Invalid gRPC response (HTTP {$response->statusCode})");
        }
        if ($status !== Code::OK) {
            throw new GrpcException($status, \rawurldecode($headers['grpc-message'] ?? ''), $headers);
        }

        $messages = [];
        foreach (Frame::unpackAll((string) $response->data, $headers['grpc-encoding'] ?? null) as $payload) {
            $messages[] = $this->codec->decode($payload, $responseClass);
        }
        return $messages;
    }

    private static function statusFromHttp(int $status): Code
    {
        return match ($status) {
            400 => Code::INTERNAL,
            401 => Code::UNAUTHENTICATED,
            403 => Code::PERMISSION_DENIED,
            404 => Code::UNIMPLEMENTED,
            429, 502, 503, 504 => Code::UNAVAILABLE,
            default => Code::UNKNOWN,
        };
    }

    private function acquire(): Http2Client
    {
        $max = (int) ($this->options['max_connections'] ?? 16);
        $this->pool ??= new Channel($max);

        if ($this->pool->isEmpty() && $this->created < $max) {
            $this->created++;
            try {
                return $this->connect();
            } catch (\Throwable $e) {
                $this->created--;
                throw $e;
            }
        }

        $client = $this->pool->pop((float) ($this->options['timeout'] ?? 5.0));
        if ($client === false) {
            throw new GrpcException(Code::RESOURCE_EXHAUSTED, 'gRPC connection pool exhausted');
        }
        if (!$client->connected) {
            $client->close();
            $this->created--;
            return $this->acquire();
        }
        return $client;
    }

    private function release(Http2Client $client, bool $healthy): void
    {
        if ($healthy && $client->connected) {
            $this->pool->push($client);
            return;
        }
        $client->close();
        $this->created--;
    }

    private function connect(): Http2Client
    {
        $ssl = (bool) ($this->options['ssl'] ?? false);
        $client = new Http2Client($this->host, $this->port, $ssl);

        $settings = ['timeout' => (float) ($this->options['connect_timeout'] ?? 3.0)];
        foreach (['ssl_host_name', 'ssl_verify_peer', 'ssl_cafile', 'ssl_cert_file', 'ssl_key_file'] as $key) {
            if (isset($this->options[$key])) {
                $settings[$key] = $this->options[$key];
            }
        }
        $client->set($settings);

        if (!$client->connect()) {
            throw new GrpcException(Code::UNAVAILABLE, "Cannot connect to {$this->host}:{$this->port}: {$client->errMsg}");
        }
        return $client;
    }

    public function close(): void
    {
        while ($this->pool !== null && !$this->pool->isEmpty()) {
            $this->pool->pop(0.001)?->close();
        }
        $this->created = 0;
    }
}
