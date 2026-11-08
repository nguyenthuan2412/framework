<?php

namespace Ryra\Core\Contracts\HttpClient;

interface Http
{
    public function get(string $url, iterable $params = []): Response;

    public function post(string $url, iterable $params = []): Response;

    public function put(string $url, iterable $params = []): Response;

    public function delete(string $url, iterable $params = []): Response;

    public function patch(string $url, iterable $params = []): Response;

    public function head(string $url, iterable $params = []): Response;

    public function onChunk(callable $handler): static;

    public function stream(string $url, callable $handler, iterable $params = [], string $method = 'POST'): Response;

    public function timeout(float $timeout): static;

    public function connectTimeout(float $timeout): static;

    public function baseUrl(?string $url = null): static|string;

    public function withHeaders(iterable $headers): static;

    public function withHeader(string $key, string $value): static;

    public function replaceHeaders(iterable $headers): static;

    public function withToken(string $token): static;

    public function withApiKey(string $key): static;

    public function withBasicAuth(string $username, string $password): static;

    public function asJson(): static;

    public function asForm(): static;

    public function accept(string $type): static;

    public function acceptJson(): static;

    public function withParams(array $params): static;

    public function attach(
        string $name,
        string $path,
        ?string $mimeType = null,
        ?string $fileName = null,
        int $offset = 0,
        int $length = 0
    ): static;

    public function retry(int $times, int $sleepMs = 0): static;

    public function withVersion(float $version): static;

    public function allowRedirect(
        int $max = 5,
        bool $strict = false,
        bool $referer = false,
        array $protocols = ['http', 'https'],
        bool $trackRedirects = false
    ): static;

    public function onRedirect(callable $handle): static;
}