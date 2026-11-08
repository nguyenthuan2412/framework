<?php

namespace Ryra\Core\Http;

use BackedEnum;
use DateTimeInterface;
use Stringable;
use Ryra\Core\App;
use Ryra\Core\Date\Date;
use Ryra\Core\Http\File\UploadedFile;
use Ryra\Core\Route\Route;
use Ryra\Core\Session\Session;
use Ryra\Core\Support\Str;

class Request
{
    protected string $method;

    protected string $uri;

    protected string $protocol;

    public readonly HeaderBag $headers;

    public readonly InputBag $cookie;

    public readonly InputBag $query;

    public readonly ServerBag $server;

    public readonly InputBag $request;

    protected string $rawContent;

    public readonly FileBag $files;

    private ?Session $session = null;

    private array $trustedValuesCache = [];

    private bool $isForwardedValid = true;

    private ?bool $isFromTrustedProxyCache = null;

    private ?Route $route = null;

    public readonly int $fd;

    private ?array $languages = null;

    /**
     * @var string[]
     */
    protected static array $trustedProxies = [];

    protected static int $trustedHeaderSet = -1;

    protected static ?array $formats = null;

    public const HEADER_FORWARDED = 0b000001; // When using RFC 7239
    public const HEADER_X_FORWARDED_FOR = 0b000010;
    public const HEADER_X_FORWARDED_HOST = 0b000100;
    public const HEADER_X_FORWARDED_PROTO = 0b001000;
    public const HEADER_X_FORWARDED_PORT = 0b010000;
    public const HEADER_X_FORWARDED_PREFIX = 0b100000;

    public const HEADER_X_FORWARDED_AWS_ELB = 0b0011010; // AWS ELB doesn't send X-Forwarded-Host
    public const HEADER_X_FORWARDED_TRAEFIK = 0b0111110; // All "X-Forwarded-*" headers sent by Traefik reverse proxy

    /**
     * Names for headers that can be trusted when
     * using trusted proxies.
     *
     * The FORWARDED header is the standard as of rfc7239.
     *
     * The other headers are non-standard, but widely used
     * by popular reverse proxies (like Apache mod_proxy or Amazon EC2).
     */
    private const TRUSTED_HEADERS = [
        self::HEADER_FORWARDED => 'forwarded',
        self::HEADER_X_FORWARDED_FOR => 'x_forwarded_for',
        self::HEADER_X_FORWARDED_HOST => 'x_forwarded_host',
        self::HEADER_X_FORWARDED_PROTO => 'x_forwarded_proto',
        self::HEADER_X_FORWARDED_PORT => 'x_forwarded_port',
        self::HEADER_X_FORWARDED_PREFIX => 'x_forwarded_prefix',
    ];

    private const FORWARDED_PARAMS = [
        self::HEADER_X_FORWARDED_FOR => 'for',
        self::HEADER_X_FORWARDED_HOST => 'host',
        self::HEADER_X_FORWARDED_PROTO => 'proto',
        self::HEADER_X_FORWARDED_PORT => 'host',
    ];

    private ?\Swoole\Http\Request $swooleRequest;

    public function getSwooleRequest(): ?\Swoole\Http\Request
    {
        return $this->swooleRequest;
    }

    public function initRequest(\Swoole\Http\Request $swooleRequest)
    {
        $this->swooleRequest = $swooleRequest;
        $server = $swooleRequest->server;
        $this->fd = $swooleRequest->fd;
        $this->method = $server['request_method'] ?? 'GET';
        $this->headers = new HeaderBag($swooleRequest->header ?? []);
        $this->protocol = isset($server['server_protocol']) ? $server['server_protocol'] : 'HTTP/1.1';
        $this->cookie = new InputBag($swooleRequest->cookie ?? []);
        $this->query = new InputBag($swooleRequest->get ?? []);
        $this->server = new ServerBag($swooleRequest->server ?? []);
        $request = $swooleRequest->post ?? [];

        $this->rawContent = $swooleRequest->rawContent();
        if ($this->header('content-type') === 'application/json' && json_validate($this->rawContent)) {
            $request = array_merge($request, json_decode($this->rawContent, true));
        }
        $this->request = new InputBag($request);
        $this->files = new FileBag($swooleRequest->files ?? []);
    }

    public function setSession(Session $session): void
    {
        $this->session = $session;
    }

    public function hasSession(): bool
    {
        return $this->getSession() !== null;
    }

    public function session(): ?Session
    {
        return $this->getSession();
    }

    public function getSession(): ?Session
    {
        return $this->session;
    }

    public function file(string $field): ?UploadedFile
    {
        return $this->files->get($field);
    }

    public function isMethod(string $method): bool
    {
        return $this->method === strtoupper($method);
    }

    public function method(): string
    {
        return $this->method;
    }

    public function protocol(): string
    {
        return $this->protocol;
    }

    public function getHost(): string
    {
        if ($this->isFromTrustedProxy() && $host = $this->getTrustedValues(self::HEADER_X_FORWARDED_HOST)) {
            return $host[0];
        }

        return $this->headers->get('host');
    }

    public function port(): int|string|null
    {
        if ($this->isFromTrustedProxy() && $host = $this->getTrustedValues(self::HEADER_X_FORWARDED_PORT)) {
            $host = $host[0];
        } elseif ($this->isFromTrustedProxy() && $host = $this->getTrustedValues(self::HEADER_X_FORWARDED_HOST)) {
            $host = $host[0];
        } elseif (!$host = $this->headers->get('host')) {
            return $this->server->get('server_port');
        }

        if ('[' === $host[0]) {
            $pos = strpos($host, ':', strrpos($host, ']'));
        } else {
            $pos = strrpos($host, ':');
        }

        if (false !== $pos && $port = substr($host, $pos + 1)) {
            return (int) $port;
        }

        return $this->isSecure() ? 443 : 80;
    }

    public function header(string $key, ?string $default = null): mixed
    {
        return $this->headers->get($key, $default);
    }

    public function hasHeader(string $key): bool
    {
        return $this->headers->has($key);
    }

    public function cookie(string $key, ?string $default = null): mixed
    {
        return $this->cookie->get($key, $default);
    }

    public function hasCookie(string $key): bool
    {
        return $this->cookie->has($key);
    }

    public function userAgent(): ?string
    {
        return $this->headers->get('User-Agent');
    }

    public function getClientIp(): string
    {
        return $this->getClientIps()[0];
    }

    /**
     * Checks whether or not the method is safe.
     *
     * @see https://tools.ietf.org/html/rfc7231#section-4.2.1
     */
    public function isMethodSafe(): bool
    {
        return \in_array($this->method(), ['GET', 'HEAD', 'OPTIONS', 'TRACE']);
    }

    public function ip(): string
    {
        return $this->getClientIp();
    }

    public function getClientIps(): array
    {
        $ip = $this->server->get('remote_addr');

        if (!$this->isFromTrustedProxy()) {
            return [$ip];
        }

        return $this->getTrustedValues(self::HEADER_X_FORWARDED_FOR, $ip) ?: [$ip];
    }

    public function ips(): array
    {
        return $this->getClientIps();
    }

    public function query(string $key): mixed
    {
        return $this->query->get($key);
    }

    public function has(string|array $key): bool
    {
        $keys = (array) $key;

        foreach ($keys as $k) {
            if (!$this->request->has($k) && !$this->query->has($k) && !$this->files->has($k)) {
                return false;
            }
        }

        return true;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        if ($this->request->has($key)) {
            return $this->request->get($key, $default);
        }

        if ($this->query->has($key)) {
            return $this->query->get($key, $default);
        }

        return $default;
    }

    public function integer(string $key, ?int $default = null): ?int
    {
        return (int) $this->input($key, $default);
    }

    public function boolean(string $key, ?bool $default = null): ?bool
    {
        return (bool) $this->input($key, $default);
    }

    public function float(string $key, ?float $default = null): ?float
    {
        return (float) $this->input($key, $default);
    }

    public function string(string $key, ?string $default = null): ?Stringable
    {
        return Str::of((string) $this->input($key, $default));
    }

    public function date(string $key, ?string $format = null): ?DateTimeInterface
    {
        $date = $this->input($key);
        if (empty($date)) {
            return null;
        }

        if (\is_null($format)) {
            return Date::parse($date);
        }
        return Date::createFromFormat($format, $date);
    }

    public function enum(string $key, string $class, ?BackedEnum $default = null): ?BackedEnum
    {
        $value = $this->input($key);
        if (\is_null($value)) {
            return $default;
        }

        return $class::tryFrom($value);
    }

    public function url(): string
    {
        return App::baseUrl() . $this->uri();
    }

    public function uri(): string
    {
        return $this->server->get('request_uri');
    }

    public function fullUrl(): string
    {
        $queryString = !empty($this->server->get('query_string'))
            ? "?{$this->server->get('query_string')}" : '';
        return App::baseUrl() . $this->uri() . $queryString;
    }

    public function fullUrlWithQuery(array $query): string
    {
        $oldQuery = [];
        if (!empty($this->server->get('query_string'))) {
            parse_str($this->server->get('query_string'), $oldQuery);
        }

        $query = array_merge($oldQuery, $query);
        return App::baseUrl() . "{$this->uri()}?" . http_build_query($query);
    }

    public function all(): array
    {
        return array_merge($this->query->all(), $this->request->all(), $this->files->all());
    }

    /**
     * Indicates whether this request originated from a trusted proxy.
     *
     * This can be useful to determine whether or not to trust the
     * contents of a proxy-specific header.
     */
    public function isFromTrustedProxy(): bool
    {
        if ($this->isFromTrustedProxyCache !== null) {
            return $this->isFromTrustedProxyCache;
        }

        if (!self::$trustedProxies) {
            return $this->isFromTrustedProxyCache = false;
        }

        // Nếu có 'remote_addr' trong trusted proxies → trust tất cả
        if (in_array('remote_addr', self::$trustedProxies, true)) {
            return $this->isFromTrustedProxyCache = true;
        }

        return $this->isFromTrustedProxyCache = IpUtils::checkIp(
            $this->server->get('remote_addr', ''),
            self::$trustedProxies
        );
    }

    /**
     * Checks whether the request is secure or not.
     *
     * This method can read the client protocol from the "X-Forwarded-Proto" header
     * when trusted proxies were set via "setTrustedProxies()".
     *
     * The "X-Forwarded-Proto" header must contain the protocol: "https" or "http".
     */
    public function isSecure(): bool
    {
        if ($this->isFromTrustedProxy() && $proto = $this->getTrustedValues(self::HEADER_X_FORWARDED_PROTO)) {
            return \in_array(strtolower($proto[0]), ['https', 'on', 'ssl', '1'], true);
        }

        $https = $this->server->get('https');

        return $https && 'off' !== strtolower($https);
    }

    public function schema(): string
    {
        return $this->isSecure() ? 'https' : 'http';
    }

    public static function setTrustedProxies(array $proxies, int $trustedHeaderSet): void
    {
        self::$trustedProxies = $proxies;
        self::$trustedHeaderSet = $trustedHeaderSet;
    }

    /**
     * This method is rather heavy because it splits and merges headers, and it's called by many other methods such as
     * getPort(), isSecure(), getHost(), getClientIps(), getBaseUrl() etc. Thus, we try to cache the results for
     * best performance.
     */
    private function getTrustedValues(int $type, ?string $ip = null): array
    {
        $cacheKey = $type . "\0" . ((self::$trustedHeaderSet & $type) ? $this->headers->get(self::TRUSTED_HEADERS[$type]) : '');
        $cacheKey .= "\0" . $ip . "\0" . $this->headers->get(self::TRUSTED_HEADERS[self::HEADER_FORWARDED]);

        if (isset($this->trustedValuesCache[$cacheKey])) {
            return $this->trustedValuesCache[$cacheKey];
        }

        $clientValues = [];
        $forwardedValues = [];

        if ((self::$trustedHeaderSet & $type) && $this->headers->has(self::TRUSTED_HEADERS[$type])) {
            foreach (explode(',', $this->headers->get(self::TRUSTED_HEADERS[$type])) as $v) {
                $clientValues[] = (self::HEADER_X_FORWARDED_PORT === $type ? '0.0.0.0:' : '') . trim($v);
            }
        }

        if (
            (self::$trustedHeaderSet & self::HEADER_FORWARDED)
            && (isset(self::FORWARDED_PARAMS[$type]))
            && $this->headers->has(self::TRUSTED_HEADERS[self::HEADER_FORWARDED])
        ) {
            $forwarded = $this->headers->get(self::TRUSTED_HEADERS[self::HEADER_FORWARDED]);
            $parts = HeaderUtils::split($forwarded, ',;=');
            $param = self::FORWARDED_PARAMS[$type];
            foreach ($parts as $subParts) {
                if (null === $v = HeaderUtils::combine($subParts)[$param] ?? null) {
                    continue;
                }
                if (self::HEADER_X_FORWARDED_PORT === $type) {
                    if (str_ends_with($v, ']') || false === $v = strrchr($v, ':')) {
                        $v = $this->isSecure() ? ':443' : ':80';
                    }
                    $v = '0.0.0.0' . $v;
                }
                $forwardedValues[] = $v;
            }
        }

        if (null !== $ip) {
            $clientValues = $this->normalizeAndFilterClientIps($clientValues, $ip);
            $forwardedValues = $this->normalizeAndFilterClientIps($forwardedValues, $ip);
        }

        if ($forwardedValues === $clientValues || !$clientValues) {
            return $this->trustedValuesCache[$cacheKey] = $forwardedValues;
        }

        if (!$forwardedValues) {
            return $this->trustedValuesCache[$cacheKey] = $clientValues;
        }

        if (!$this->isForwardedValid) {
            return $this->trustedValuesCache[$cacheKey] = null !== $ip ? ['0.0.0.0', $ip] : [];
        }
        $this->isForwardedValid = false;

        return [];
        // throw new ConflictingHeadersException(sprintf('The request has both a trusted "%s" header and a trusted "%s" header, conflicting with each other. You should either configure your proxy to remove one of them, or configure your project to distrust the offending one.', self::TRUSTED_HEADERS[self::HEADER_FORWARDED], self::TRUSTED_HEADERS[$type]));
    }

    private function normalizeAndFilterClientIps(array $clientIps, string $ip): array
    {
        if (!$clientIps) {
            return [];
        }
        $clientIps[] = $ip; // Complete the IP chain with the IP the request actually came from
        $firstTrustedIp = null;

        foreach ($clientIps as $key => $clientIp) {
            if (strpos($clientIp, '.')) {
                // Strip :port from IPv4 addresses. This is allowed in Forwarded
                // and may occur in X-Forwarded-For.
                $i = strpos($clientIp, ':');
                if ($i) {
                    $clientIps[$key] = $clientIp = substr($clientIp, 0, $i);
                }
            } elseif (str_starts_with($clientIp, '[')) {
                // Strip brackets and :port from IPv6 addresses.
                $i = strpos($clientIp, ']', 1);
                $clientIps[$key] = $clientIp = substr($clientIp, 1, $i - 1);
            }

            if (!filter_var($clientIp, \FILTER_VALIDATE_IP)) {
                unset($clientIps[$key]);

                continue;
            }

            if (IpUtils::checkIp($clientIp, self::$trustedProxies)) {
                unset($clientIps[$key]);

                // Fallback to this when the client IP falls into the range of trusted proxies
                $firstTrustedIp ??= $clientIp;
            }
        }

        // Now the IP chain contains only untrusted proxies and the client IP
        return $clientIps ? array_reverse($clientIps) : [$firstTrustedIp];
    }

    /**
     * Checks whether the method is cacheable or not.
     */
    public function isMethodCacheable(): bool
    {
        return \in_array($this->method(), ['GET', 'HEAD']);
    }

    public function isNoCache(): bool
    {
        return $this->headers->hasCacheControlDirective('no-cache') || 'no-cache' === $this->headers->get('pragma');
    }

    /**
     * Gets the Etags.
     */
    public function getETags(): array
    {
        return preg_split('/\s*,\s*/', $this->headers->get('if-none-match', ''), -1, \PREG_SPLIT_NO_EMPTY);
    }

    public function isJson(): bool
    {
        $contentType = $this->headers->get('content-type') ?? '';

        return str_contains($contentType, '/json') || str_contains($contentType, '+json');
    }

    public function wantsJson(): bool
    {
        $acceptable = $this->getAcceptableContentTypes();
        return isset($acceptable[0])
            && (str_contains($acceptable[0], '/json')
                || str_contains($acceptable[0], '+json'));
    }

    /**
     * Returns the preferred language.
     *
     * @param string[] $locales An array of ordered available locales
     */
    public function getPreferredLanguage(?array $locales = null): ?string
    {
        $preferredLanguages = $this->getLanguages();

        if (!$locales) {
            return $preferredLanguages[0] ?? null;
        }

        $locales = array_map(static::formatLocale(...), $locales);
        if (!$preferredLanguages) {
            return $locales[0];
        }

        $combinations = array_merge(...array_map(static::getLanguageCombinations(...), $preferredLanguages));
        foreach ($combinations as $combination) {
            foreach ($locales as $locale) {
                if (str_starts_with($locale, $combination)) {
                    return $locale;
                }
            }
        }

        return $locales[0];
    }

    /**
     * Gets a list of languages acceptable by the client browser ordered in the user browser preferences.
     *
     * @return string[]
     */
    public function getLanguages(): array
    {
        if (null !== $this->languages) {
            return $this->languages;
        }

        $languages = AcceptHeader::fromString($this->headers->get('accept-language'))->all();
        $this->languages = [];
        foreach ($languages as $acceptHeaderItem) {
            $lang = $acceptHeaderItem->getValue();
            $this->languages[] = self::formatLocale($lang);
        }
        $this->languages = array_unique($this->languages);

        return $this->languages;
    }

    public function getAcceptableContentTypes(): array
    {
        return array_map('strval', array_keys(AcceptHeader::fromString($this->headers->get('Accept'))->all()));
    }

    /**
     * Determines whether the current requests accepts a given content type.
     */
    public function accepts(string|array $contentTypes): bool
    {
        $accepts = $this->getAcceptableContentTypes();

        if (count($accepts) === 0) {
            return true;
        }

        $types = (array) $contentTypes;

        foreach ($accepts as $accept) {
            if ($accept === '*/*' || $accept === '*') {
                return true;
            }

            foreach ($types as $type) {
                $accept = strtolower($accept);

                $type = strtolower($type);

                if ($this->matchesType($accept, $type) || $accept === strtok($type, '/') . '/*') {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Determine if the given content types match.
     */
    public static function matchesType(string $actual, string $type): bool
    {
        if ($actual === $type) {
            return true;
        }

        $split = explode('/', $actual);

        return isset($split[1]) && preg_match('#' . preg_quote($split[0], '#') . '/.+\+' . preg_quote($split[1], '#') . '#', $type);
    }

    public function ajax(): bool
    {
        return 'XMLHttpRequest' === $this->headers->get('x-requested-with');
    }

    public function pjax(): bool
    {
        return (bool) $this->headers->get('x-pjax');
    }

    /**
     * Gets the request format.
     *
     * Here is the process to determine the format:
     *
     *  * format defined by the user (with setRequestFormat())
     *  * _format request attribute
     *  * $default
     *
     * @see getPreferredFormat
     */
    public function getRequestFormat(?string $default = 'html'): ?string
    {
        return $this->headers->has(key: 'HTTP_ACCEPT') ? $this->headers->get('HTTP_ACCEPT') : $default;
    }

    /**
     * Gets the mime type associated with the format.
     */
    public function getMimeType(string $format): ?string
    {
        if (null === static::$formats) {
            static::initializeFormats();
        }

        return isset(static::$formats[$format]) ? static::$formats[$format][0] : null;
    }

    /**
     * Gets the mime types associated with the format.
     *
     * @return string[]
     */
    public static function getMimeTypes(string $format): array
    {
        if (null === static::$formats) {
            static::initializeFormats();
        }

        return static::$formats[$format] ?? [];
    }

    public function setRoute(Route $route): static
    {
        $this->route = $route;
        return $this;
    }

    public function getRoute(): ?Route
    {
        return $this->route;
    }

    public function route(): ?Route
    {
        return $this->getRoute();
    }

    /**
     * Version của route đang xử lý, null nếu route là version neutral.
     */
    public function apiVersion(): ?string
    {
        return $this->getRoute()?->getVersion();
    }

    public function fullUrlIs(string $pattern): bool
    {
        return Str::is($pattern, $this->fullUrl());
    }

    public function is(string $pattern): bool
    {
        return Str::is($pattern, $this->uri());
    }

    public function bearerToken(): ?string
    {
        $header = $this->header('Authorization', '');

        if (str_starts_with(strtolower($header), 'bearer ')) {
            return substr($header, 7);
        }

        return null;
    }

    /**
     * Initializes HTTP request formats.
     */
    protected static function initializeFormats(): void
    {
        static::$formats = [
            'html' => ['text/html', 'application/xhtml+xml'],
            'txt' => ['text/plain'],
            'js' => ['application/javascript', 'application/x-javascript', 'text/javascript'],
            'css' => ['text/css'],
            'json' => ['application/json', 'application/x-json'],
            'jsonld' => ['application/ld+json'],
            'xml' => ['text/xml', 'application/xml', 'application/x-xml'],
            'rdf' => ['application/rdf+xml'],
            'atom' => ['application/atom+xml'],
            'rss' => ['application/rss+xml'],
            'form' => ['application/x-www-form-urlencoded', 'multipart/form-data'],
        ];
    }

    public function __get(string $name)
    {
        return $this->input($name);
    }

    /**
     * Strips the locale to only keep the canonicalized language value.
     *
     * Depending on the $locale value, this method can return values like :
     * - language_Script_REGION: "fr_Latn_FR", "zh_Hans_TW"
     * - language_Script: "fr_Latn", "zh_Hans"
     * - language_REGION: "fr_FR", "zh_TW"
     * - language: "fr", "zh"
     *
     * Invalid locale values are returned as is.
     *
     * @see https://wikipedia.org/wiki/IETF_language_tag
     * @see https://datatracker.ietf.org/doc/html/rfc5646
     */
    private static function formatLocale(string $locale): string
    {
        [$language, $script, $region] = self::getLanguageComponents($locale);

        return implode('_', array_filter([$language, $script, $region]));
    }

    /**
     * Returns an array with the language components of the locale.
     *
     * For example:
     * - If the locale is "fr_Latn_FR", this method will return "fr", "Latn", "FR"
     * - If the locale is "fr_FR", this method will return "fr", null, "FR"
     * - If the locale is "zh_Hans", this method will return "zh", "Hans", null
     *
     * @see https://wikipedia.org/wiki/IETF_language_tag
     * @see https://datatracker.ietf.org/doc/html/rfc5646
     *
     * @return array{string, string|null, string|null}
     */
    private static function getLanguageComponents(string $locale): array
    {
        $locale = str_replace('_', '-', strtolower($locale));
        $pattern = '/^([a-zA-Z]{2,3}|i-[a-zA-Z]{5,})(?:-([a-zA-Z]{4}))?(?:-([a-zA-Z]{2}))?(?:-(.+))?$/';
        if (!preg_match($pattern, $locale, $matches)) {
            return [$locale, null, null];
        }
        if (str_starts_with($matches[1], 'i-')) {
            // Language not listed in ISO 639 that are not variants
            // of any listed language, which can be registered with the
            // i-prefix, such as i-cherokee
            $matches[1] = substr($matches[1], 2);
        }

        return [
            $matches[1],
            isset($matches[2]) ? ucfirst(strtolower($matches[2])) : null,
            isset($matches[3]) ? strtoupper($matches[3]) : null,
        ];
    }

    /**
     * Returns an array of all possible combinations of the language components.
     *
     * For instance, if the locale is "fr_Latn_FR", this method will return:
     * - "fr_Latn_FR"
     * - "fr_Latn"
     * - "fr_FR"
     * - "fr"
     *
     * @return string[]
     */
    private static function getLanguageCombinations(string $locale): array
    {
        [$language, $script, $region] = self::getLanguageComponents($locale);

        return array_unique([
            implode('_', array_filter([$language, $script, $region])),
            implode('_', array_filter([$language, $script])),
            implode('_', array_filter([$language, $region])),
            $language,
        ]);
    }
}