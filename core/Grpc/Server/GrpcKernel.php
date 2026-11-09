<?php

namespace Ryra\Core\Grpc\Server;

use Closure;
use Ryra\Core\App;
use Ryra\Core\Grpc\Exception\DecodeException;
use Ryra\Core\Grpc\Exception\GrpcException;
use Ryra\Core\Grpc\Metadata\MethodMetadata;
use Ryra\Core\Grpc\Protobuf\Codec;
use Ryra\Core\Grpc\Protobuf\Frame;
use Ryra\Core\ErrorHandler\ErrorRenderer\CliErrorRenderer;
use Swoole\Http\Request as SwooleRequest;
use Swoole\Http\Response as SwooleResponse;

/**
 * Xử lý request gRPC (HTTP/2, content-type application/grpc) trên Swoole server.
 */
final class GrpcKernel
{
    private const RESERVED_HEADERS = [
        'content-type', 'te', 'user-agent', 'grpc-timeout', 'grpc-encoding',
        'grpc-accept-encoding', 'content-length', 'host', 'accept-encoding',
    ];

    /**
     * @param string[] $interceptors Class implement Interceptor, chạy theo thứ tự khai báo.
     */
    public function __construct(
        private readonly ServiceRegistry $registry,
        private readonly Codec $codec,
        private readonly array $interceptors = [],
        private readonly bool $debug = false,
    ) {}

    public static function isGrpcRequest(SwooleRequest $request): bool
    {
        $contentType = $request->header['content-type'] ?? '';
        return \str_starts_with($contentType, 'application/grpc');
    }

    public function handle(SwooleRequest $request, SwooleResponse $response): void
    {
        $response->status(200);
        $response->header('content-type', 'application/grpc+proto');

        $path = $request->server['request_uri'] ?? '';
        $route = $this->registry->find($path);

        if ($route === null) {
            $this->finish($response, Code::UNIMPLEMENTED, "Method $path is not implemented");
            return;
        }

        [$implementation, $service, $method] = $route;
        $headers = $request->header ?? [];
        $context = new ServerContext(
            service: $service,
            method: $method,
            metadata: \array_diff_key($headers, \array_flip(self::RESERVED_HEADERS)),
            peer: isset($request->server['remote_addr']) ? $request->server['remote_addr'] . ':' . ($request->server['remote_port'] ?? '') : null,
            deadline: ServerContext::parseTimeout($headers['grpc-timeout'] ?? null),
        );

        try {
            if (($request->server['request_method'] ?? 'POST') !== 'POST') {
                throw new GrpcException(Code::UNIMPLEMENTED, 'gRPC requires POST');
            }

            $payloads = Frame::unpackAll((string) $request->rawContent(), $headers['grpc-encoding'] ?? null);
            if ($method->clientStreaming) {
                $codec = $this->codec;
                $input = (static function () use ($payloads, $codec, $method) {
                    foreach ($payloads as $payload) {
                        yield $codec->decode($payload, $method->requestClass);
                    }
                })();
            } else {
                if (\count($payloads) !== 1) {
                    throw new GrpcException(Code::INVALID_ARGUMENT, 'Unary rpc expects exactly one request message');
                }
                $input = $this->codec->decode($payloads[0], $method->requestClass);
            }

            $result = $this->pipeline($implementation, $method)($input, $context);

            // Swoole không hỗ trợ write() (chunk) trên HTTP/2, nên các message của
            // server streaming được gom lại và gửi một lần cùng trailer.
            $body = '';
            if ($method->serverStreaming) {
                foreach ($result as $message) {
                    $body .= Frame::pack($this->encode($method, $message));
                }
            } else {
                $body = Frame::pack($this->encode($method, $result));
            }

            $this->finish($response, Code::OK, '', $context, $body);
        } catch (GrpcException $e) {
            foreach ($e->metadata as $key => $value) {
                $context->setTrailer($key, $value);
            }
            $this->finish($response, $e->status, $e->getMessage(), $context);
        } catch (DecodeException | \UnexpectedValueException $e) {
            $this->finish($response, Code::INVALID_ARGUMENT, 'Invalid request message: ' . $e->getMessage(), $context);
        } catch (\Throwable $e) {
            CliErrorRenderer::render($e);
            $message = $this->debug ? $e::class . ': ' . $e->getMessage() : 'Internal server error';
            $this->finish($response, Code::INTERNAL, $message, $context);
        }
    }

    private function encode(MethodMetadata $method, mixed $message): string
    {
        if (!$message instanceof $method->responseClass) {
            if ($method->returnsVoid) {
                $message = new $method->responseClass();
            } else {
                throw new \UnexpectedValueException(
                    "Rpc {$method->name} must return {$method->responseClass}, got " . \get_debug_type($message)
                );
            }
        }
        return $this->codec->encode($message);
    }

    private function pipeline(string $implementation, MethodMetadata $method): Closure
    {
        $handler = static function (mixed $input, ServerContext $context) use ($implementation, $method): mixed {
            $service = App::make($implementation);
            $arguments = [];
            for ($i = 0; $i < $method->parameterCount; $i++) {
                if ($i === $method->requestPosition) {
                    $arguments[] = $input;
                } elseif ($i === $method->contextPosition) {
                    $arguments[] = $context;
                } else {
                    break; // tham số optional còn lại dùng giá trị mặc định
                }
            }
            return $service->{$method->phpMethod}(...$arguments);
        };

        foreach (\array_reverse($this->interceptors) as $interceptor) {
            $next = $handler;
            $handler = static function (mixed $input, ServerContext $context) use ($interceptor, $next): mixed {
                /** @var Interceptor $instance */
                $instance = \is_string($interceptor) ? App::make($interceptor) : $interceptor;
                return $instance->intercept($input, $context, $next);
            };
        }

        return $handler;
    }

    private function sendHeaders(SwooleResponse $response, ServerContext $context): void
    {
        foreach ($context->responseHeaders() as $key => $value) {
            $response->header($key, $value);
        }
    }

    private function finish(
        SwooleResponse $response,
        Code $status,
        string $message,
        ?ServerContext $context = null,
        string $body = '',
    ): void {
        if ($context !== null) {
            $this->sendHeaders($response, $context);
        }
        foreach ($context?->trailers() ?? [] as $key => $value) {
            $response->trailer($key, $value);
        }
        $response->trailer('grpc-status', (string) $status->value);
        if ($message !== '') {
            $response->trailer('grpc-message', self::encodeMessage($message));
        }
        $response->end($body);
    }

    /**
     * grpc-message được percent-encode theo spec.
     */
    public static function encodeMessage(string $message): string
    {
        return \preg_replace_callback(
            '/[^\x20-\x24\x26-\x7E]/',
            static fn ($m) => \sprintf('%%%02X', \ord($m[0])),
            $message
        );
    }
}
