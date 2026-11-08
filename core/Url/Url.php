<?php

namespace Ryra\Core\Url;

use Ryra\Core\App;
use Ryra\Core\Http\Request;
use Ryra\Core\Support\Arrayable;
use Ryra\Core\Support\Traits\Conditionable;

final class Url implements \Stringable, \JsonSerializable, Arrayable
{
    use Conditionable;

    private readonly ?string $scheme;
    private readonly ?string $host;
    private readonly ?int $port;
    private readonly ?string $user;
    private readonly ?string $pass;
    private readonly string $path;
    private readonly QueryBag $query;
    private readonly ?string $fragment;

    private ?string $cached = null;

    private function __construct(
        ?string $scheme,
        ?string $host,
        ?int $port,
        ?string $user,
        ?string $pass,
        string $path,
        QueryBag $query,
        ?string $fragment,
    ) {
        $this->scheme = $scheme !== null ? \strtolower($scheme) : null;
        $this->host = $host !== null ? \strtolower($host) : null;
        $this->port = $port;
        $this->user = $user;
        $this->pass = $pass;
        $this->path = $path;
        $this->query = $query;
        $this->fragment = $fragment;
    }

    // =========================================================================
    // Static Factory Methods
    // =========================================================================

    public static function parse(string $url): self
    {
        $parts = \parse_url($url);

        if ($parts === false) {
            return new self(null, null, null, null, null, $url, new QueryBag(), null);
        }

        return new self(
            scheme: $parts['scheme'] ?? null,
            host: $parts['host'] ?? null,
            port: isset($parts['port']) ? (int) $parts['port'] : null,
            user: $parts['user'] ?? null,
            pass: $parts['pass'] ?? null,
            path: $parts['path'] ?? '',
            query: isset($parts['query']) ? QueryBag::fromString($parts['query']) : new QueryBag(),
            fragment: $parts['fragment'] ?? null,
        );
    }

    public static function fromRequest(Request $request): self
    {
        return self::parse($request->fullUrl());
    }

    public static function current(): self
    {
        return self::fromRequest(App::service(Request::class));
    }

    public static function base(): self
    {
        return self::parse(App::baseUrl());
    }

    public static function create(
        ?string $scheme = null,
        ?string $host = null,
        ?int $port = null,
        string $path = '',
        array|QueryBag $query = [],
        ?string $fragment = null,
        ?string $user = null,
        ?string $pass = null,
    ): self {
        $queryBag = $query instanceof QueryBag ? $query : new QueryBag($query);

        return new self($scheme, $host, $port, $user, $pass, $path, $queryBag, $fragment);
    }

    public static function to(string $path, array $query = [], bool $secure = false): self
    {
        $base = self::base();
        $url = $base->withPath($path);

        if (!empty($query)) {
            $url = $url->withQueryParams($query);
        }

        if ($secure) {
            $url = $url->withScheme('https');
        }

        return $url;
    }

    // =========================================================================
    // Component Accessors
    // =========================================================================

    public function scheme(): ?string
    {
        return $this->scheme;
    }

    public function schemeEnum(): ?Scheme
    {
        return $this->scheme !== null ? Scheme::tryFrom($this->scheme) : null;
    }

    public function host(): ?string
    {
        return $this->host;
    }

    public function port(): ?int
    {
        return $this->port;
    }

    public function effectivePort(): ?int
    {
        if ($this->port !== null) {
            return $this->port;
        }

        return $this->schemeEnum()?->defaultPort();
    }

    public function user(): ?string
    {
        return $this->user;
    }

    public function pass(): ?string
    {
        return $this->pass;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function query(): QueryBag
    {
        return $this->query;
    }

    public function queryString(): string
    {
        return $this->query->toString();
    }

    public function fragment(): ?string
    {
        return $this->fragment;
    }

    public function authority(): ?string
    {
        if ($this->host === null) {
            return null;
        }

        $authority = '';

        if ($this->user !== null) {
            $authority .= $this->user;
            if ($this->pass !== null) {
                $authority .= ':' . $this->pass;
            }
            $authority .= '@';
        }

        $authority .= $this->host;

        if ($this->port !== null && !$this->isDefaultPort()) {
            $authority .= ':' . $this->port;
        }

        return $authority;
    }

    // =========================================================================
    // Immutable Modification
    // =========================================================================

    public function withScheme(string|Scheme $scheme): self
    {
        $value = $scheme instanceof Scheme ? $scheme->value : $scheme;

        return $this->cloneWith(scheme: $value);
    }

    public function withHost(string $host): self
    {
        return $this->cloneWith(host: $host);
    }

    public function withPort(?int $port): self
    {
        return $this->cloneWith(port: $port);
    }

    public function withPath(string $path): self
    {
        if ($this->host !== null && $path !== '' && !\str_starts_with($path, '/')) {
            $path = '/' . $path;
        }

        return $this->cloneWith(path: $path);
    }

    public function withQuery(string|QueryBag|array $query): self
    {
        if (\is_string($query)) {
            $query = QueryBag::fromString($query);
        } elseif (\is_array($query)) {
            $query = new QueryBag($query);
        }

        return $this->cloneWith(query: $query);
    }

    public function withFragment(?string $fragment): self
    {
        return $this->cloneWith(fragment: $fragment);
    }

    public function withUser(?string $user): self
    {
        return $this->cloneWith(user: $user);
    }

    public function withPass(?string $pass): self
    {
        return $this->cloneWith(pass: $pass);
    }

    // =========================================================================
    // Query Shortcuts
    // =========================================================================

    public function withQueryParam(string $key, mixed $value): self
    {
        return $this->cloneWith(query: $this->query->with($key, $value));
    }

    public function withQueryParams(array $params): self
    {
        return $this->cloneWith(query: $this->query->withMany($params));
    }

    public function withoutQueryParam(string|array $keys): self
    {
        return $this->cloneWith(query: $this->query->without($keys));
    }

    public function withoutQuery(): self
    {
        return $this->cloneWith(query: new QueryBag());
    }

    public function hasQueryParam(string $key): bool
    {
        return $this->query->has($key);
    }

    public function getQueryParam(string $key, mixed $default = null): mixed
    {
        return $this->query->get($key, $default);
    }

    // =========================================================================
    // Path Manipulation
    // =========================================================================

    public function withTrailingSlash(): self
    {
        if (\str_ends_with($this->path, '/')) {
            return $this;
        }

        return $this->cloneWith(path: $this->path . '/');
    }

    public function withoutTrailingSlash(): self
    {
        if ($this->path === '/' || !\str_ends_with($this->path, '/')) {
            return $this;
        }

        return $this->cloneWith(path: \rtrim($this->path, '/'));
    }

    public function appendPath(string $suffix): self
    {
        $base = \rtrim($this->path, '/');
        $suffix = \ltrim($suffix, '/');

        return $this->cloneWith(path: $base . '/' . $suffix);
    }

    /**
     * @return string[]
     */
    public function segments(): array
    {
        return \array_values(\array_filter(\explode('/', $this->path), static fn($s) => $s !== ''));
    }

    public function segment(int $index, ?string $default = null): ?string
    {
        $segments = $this->segments();

        return $segments[$index - 1] ?? $default;
    }

    public function dirname(): string
    {
        return \dirname($this->path);
    }

    public function basename(): string
    {
        return \basename($this->path);
    }

    public function extension(): string
    {
        return \pathinfo($this->path, \PATHINFO_EXTENSION);
    }

    // =========================================================================
    // Validation / Inspection
    // =========================================================================

    public function isValid(): bool
    {
        return $this->host !== null || $this->path !== '';
    }

    public function isAbsolute(): bool
    {
        return $this->scheme !== null && $this->host !== null;
    }

    public function isRelative(): bool
    {
        return !$this->isAbsolute();
    }

    public function isSecure(): bool
    {
        $schemeEnum = $this->schemeEnum();

        return $schemeEnum !== null && $schemeEnum->isSecure();
    }

    public function isDefaultPort(): bool
    {
        if ($this->port === null) {
            return true;
        }

        $schemeEnum = $this->schemeEnum();

        return $schemeEnum !== null && $schemeEnum->isDefaultPort($this->port);
    }

    public static function isValidUrl(string $url, array $protocols = []): bool
    {
        return \Ryra\Core\Support\Str::isUrl($url, $protocols);
    }

    // =========================================================================
    // Signed URLs
    // =========================================================================

    public function signed(?string $key = null): self
    {
        $key = $key ?? \config('app.key');
        $url = $this->withoutQueryParam('signature');
        $signature = \hash_hmac('sha256', $url->toString(), $key);

        return $url->withQueryParam('signature', $signature);
    }

    public function temporarySignedUrl(\DateTimeInterface|int $expiration, ?string $key = null): self
    {
        $expires = $expiration instanceof \DateTimeInterface
            ? $expiration->getTimestamp()
            : \time() + $expiration;

        return $this->withQueryParam('expires', $expires)->signed($key);
    }

    public function hasValidSignature(?string $key = null): bool
    {
        $key = $key ?? \config('app.key');
        $signature = $this->getQueryParam('signature');

        if ($signature === null) {
            return false;
        }

        $expires = $this->getQueryParam('expires');
        if ($expires !== null && \time() > (int) $expires) {
            return false;
        }

        $url = $this->withoutQueryParam('signature');
        $expected = \hash_hmac('sha256', $url->toString(), $key);

        return \hash_equals($expected, (string) $signature);
    }

    // =========================================================================
    // Encoding Helpers
    // =========================================================================

    public static function encode(string $value): string
    {
        return \rawurlencode($value);
    }

    public static function decode(string $value): string
    {
        return \rawurldecode($value);
    }

    // =========================================================================
    // Output
    // =========================================================================

    public function toString(): string
    {
        if ($this->cached !== null) {
            return $this->cached;
        }

        $url = '';

        if ($this->scheme !== null) {
            $url .= $this->scheme . ':';
        }

        $authority = $this->authority();
        if ($authority !== null) {
            $url .= '//' . $authority;
        }

        $url .= $this->path;

        $queryString = $this->query->toString();
        if ($queryString !== '') {
            $url .= '?' . $queryString;
        }

        if ($this->fragment !== null) {
            $url .= '#' . $this->fragment;
        }

        return $this->cached = $url;
    }

    public function toArray(): array
    {
        return [
            'scheme' => $this->scheme,
            'host' => $this->host,
            'port' => $this->port,
            'user' => $this->user,
            'pass' => $this->pass,
            'path' => $this->path,
            'query' => $this->query->toArray(),
            'fragment' => $this->fragment,
        ];
    }

    public function toJson(int $flags = 0): string
    {
        return \json_encode($this->toArray(), $flags | \JSON_UNESCAPED_SLASHES);
    }

    public function jsonSerialize(): string
    {
        return $this->toString();
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    // =========================================================================
    // Private: clone-and-modify
    // =========================================================================

    private function cloneWith(mixed ...$overrides): self
    {
        return new self(
            scheme: \array_key_exists('scheme', $overrides) ? $overrides['scheme'] : $this->scheme,
            host: \array_key_exists('host', $overrides) ? $overrides['host'] : $this->host,
            port: \array_key_exists('port', $overrides) ? $overrides['port'] : $this->port,
            user: \array_key_exists('user', $overrides) ? $overrides['user'] : $this->user,
            pass: \array_key_exists('pass', $overrides) ? $overrides['pass'] : $this->pass,
            path: \array_key_exists('path', $overrides) ? $overrides['path'] : $this->path,
            query: \array_key_exists('query', $overrides) ? $overrides['query'] : $this->query,
            fragment: \array_key_exists('fragment', $overrides) ? $overrides['fragment'] : $this->fragment,
        );
    }
}
