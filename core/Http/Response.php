<?php

namespace Ryra\Core\Http;

use Swoole\Http\Response as SwooleResponse;
use Ryra\Core\App;
use Ryra\Core\Session\Facade\Session;

class Response
{
    public const HTTP_CONTINUE = 100;
    public const HTTP_SWITCHING_PROTOCOLS = 101;
    public const HTTP_PROCESSING = 102;            // RFC2518
    public const HTTP_EARLY_HINTS = 103;           // RFC8297
    public const HTTP_OK = 200;
    public const HTTP_CREATED = 201;
    public const HTTP_ACCEPTED = 202;
    public const HTTP_NON_AUTHORITATIVE_INFORMATION = 203;
    public const HTTP_NO_CONTENT = 204;
    public const HTTP_RESET_CONTENT = 205;
    public const HTTP_PARTIAL_CONTENT = 206;
    public const HTTP_MULTI_STATUS = 207;          // RFC4918
    public const HTTP_ALREADY_REPORTED = 208;      // RFC5842
    public const HTTP_IM_USED = 226;               // RFC3229
    public const HTTP_MULTIPLE_CHOICES = 300;
    public const HTTP_MOVED_PERMANENTLY = 301;
    public const HTTP_FOUND = 302;
    public const HTTP_SEE_OTHER = 303;
    public const HTTP_NOT_MODIFIED = 304;
    public const HTTP_USE_PROXY = 305;
    public const HTTP_RESERVED = 306;
    public const HTTP_TEMPORARY_REDIRECT = 307;
    public const HTTP_PERMANENTLY_REDIRECT = 308;  // RFC7238
    public const HTTP_BAD_REQUEST = 400;
    public const HTTP_UNAUTHORIZED = 401;
    public const HTTP_PAYMENT_REQUIRED = 402;
    public const HTTP_FORBIDDEN = 403;
    public const HTTP_NOT_FOUND = 404;
    public const HTTP_METHOD_NOT_ALLOWED = 405;
    public const HTTP_NOT_ACCEPTABLE = 406;
    public const HTTP_PROXY_AUTHENTICATION_REQUIRED = 407;
    public const HTTP_REQUEST_TIMEOUT = 408;
    public const HTTP_CONFLICT = 409;
    public const HTTP_GONE = 410;
    public const HTTP_LENGTH_REQUIRED = 411;
    public const HTTP_PRECONDITION_FAILED = 412;
    public const HTTP_REQUEST_ENTITY_TOO_LARGE = 413;
    public const HTTP_REQUEST_URI_TOO_LONG = 414;
    public const HTTP_UNSUPPORTED_MEDIA_TYPE = 415;
    public const HTTP_REQUESTED_RANGE_NOT_SATISFIABLE = 416;
    public const HTTP_EXPECTATION_FAILED = 417;
    public const HTTP_I_AM_A_TEAPOT = 418;                                               // RFC2324
    public const HTTP_MISDIRECTED_REQUEST = 421;                                         // RFC7540
    public const HTTP_UNPROCESSABLE_ENTITY = 422;                                        // RFC4918
    public const HTTP_LOCKED = 423;                                                      // RFC4918
    public const HTTP_FAILED_DEPENDENCY = 424;                                           // RFC4918
    public const HTTP_TOO_EARLY = 425;                                                   // RFC-ietf-httpbis-replay-04
    public const HTTP_UPGRADE_REQUIRED = 426;                                            // RFC2817
    public const HTTP_PRECONDITION_REQUIRED = 428;                                       // RFC6585
    public const HTTP_TOO_MANY_REQUESTS = 429;                                           // RFC6585
    public const HTTP_REQUEST_HEADER_FIELDS_TOO_LARGE = 431;                             // RFC6585
    public const HTTP_UNAVAILABLE_FOR_LEGAL_REASONS = 451;                               // RFC7725
    public const HTTP_INTERNAL_SERVER_ERROR = 500;
    public const HTTP_NOT_IMPLEMENTED = 501;
    public const HTTP_BAD_GATEWAY = 502;
    public const HTTP_SERVICE_UNAVAILABLE = 503;
    public const HTTP_GATEWAY_TIMEOUT = 504;
    public const HTTP_VERSION_NOT_SUPPORTED = 505;
    public const HTTP_VARIANT_ALSO_NEGOTIATES_EXPERIMENTAL = 506;                        // RFC2295
    public const HTTP_INSUFFICIENT_STORAGE = 507;                                        // RFC4918
    public const HTTP_LOOP_DETECTED = 508;                                               // RFC5842
    public const HTTP_NOT_EXTENDED = 510;                                                // RFC2774
    public const HTTP_NETWORK_AUTHENTICATION_REQUIRED = 511;                             // RFC6585

    /**
     * @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Cache-Control
     */
    private const HTTP_RESPONSE_CACHE_CONTROL_DIRECTIVES = [
        'must_revalidate' => false,
        'no_cache' => false,
        'no_store' => false,
        'no_transform' => false,
        'public' => false,
        'private' => false,
        'proxy_revalidate' => false,
        'max_age' => true,
        's_maxage' => true,
        'stale_if_error' => true,         // RFC5861
        'stale_while_revalidate' => true, // RFC5861
        'immutable' => false,
        'last_modified' => true,
        'etag' => true,
    ];

    public readonly ResponseHeaderBag $headers;

    protected string $content;
    protected string $version;
    protected int $statusCode;
    protected string $statusText;
    protected ?string $charset = null;

    /**
     * Status codes translation table.
     *
     * The list of codes is complete according to the
     * {@link https://www.iana.org/assignments/http-status-codes/http-status-codes.xhtml Hypertext Transfer Protocol (HTTP) Status Code Registry}
     * (last updated 2021-10-01).
     *
     * Unless otherwise noted, the status code is defined in RFC2616.
     */
    public static array $statusTexts = [
        100 => 'Continue',
        101 => 'Switching Protocols',
        102 => 'Processing',            // RFC2518
        103 => 'Early Hints',
        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        203 => 'Non-Authoritative Information',
        204 => 'No Content',
        205 => 'Reset Content',
        206 => 'Partial Content',
        207 => 'Multi-Status',          // RFC4918
        208 => 'Already Reported',      // RFC5842
        226 => 'IM Used',               // RFC3229
        300 => 'Multiple Choices',
        301 => 'Moved Permanently',
        302 => 'Found',
        303 => 'See Other',
        304 => 'Not Modified',
        305 => 'Use Proxy',
        307 => 'Temporary Redirect',
        308 => 'Permanent Redirect',    // RFC7238
        400 => 'Bad Request',
        401 => 'Unauthorized',
        402 => 'Payment Required',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        406 => 'Not Acceptable',
        407 => 'Proxy Authentication Required',
        408 => 'Request Timeout',
        409 => 'Conflict',
        410 => 'Gone',
        411 => 'Length Required',
        412 => 'Precondition Failed',
        413 => 'Content Too Large',                                           // RFC-ietf-httpbis-semantics
        414 => 'URI Too Long',
        415 => 'Unsupported Media Type',
        416 => 'Range Not Satisfiable',
        417 => 'Expectation Failed',
        418 => 'I\'m a teapot',                                               // RFC2324
        421 => 'Misdirected Request',                                         // RFC7540
        422 => 'Unprocessable Content',                                       // RFC-ietf-httpbis-semantics
        423 => 'Locked',                                                      // RFC4918
        424 => 'Failed Dependency',                                           // RFC4918
        425 => 'Too Early',                                                   // RFC-ietf-httpbis-replay-04
        426 => 'Upgrade Required',                                            // RFC2817
        428 => 'Precondition Required',                                       // RFC6585
        429 => 'Too Many Requests',                                           // RFC6585
        431 => 'Request Header Fields Too Large',                             // RFC6585
        451 => 'Unavailable For Legal Reasons',                               // RFC7725
        500 => 'Internal Server Error',
        501 => 'Not Implemented',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
        504 => 'Gateway Timeout',
        505 => 'HTTP Version Not Supported',
        506 => 'Variant Also Negotiates',                                     // RFC2295
        507 => 'Insufficient Storage',                                        // RFC4918
        508 => 'Loop Detected',                                               // RFC5842
        510 => 'Not Extended',                                                // RFC2774
        511 => 'Network Authentication Required',                             // RFC6585
    ];

    /**
     * Tracks headers already sent in informational responses.
     */
    private array $sentHeaders;

    /**
     * @param int $status The HTTP status code (200 "OK" by default)
     *
     * @throws \InvalidArgumentException When the HTTP status code is not valid
     */
    public function __construct(?string $content = '', int $status = 200, array $headers = [])
    {
        $this->headers = new ResponseHeaderBag($headers);
        $this->setContent($content);
        $this->setStatusCode($status);
        $this->setProtocolVersion('1.0');
    }

    public static function json(mixed $data = null, int $status = 200, array $headers = [], bool $json = false): JsonResponse
    {
        return new JsonResponse($data, $status, $headers, $json);
    }

    public static function redirect(string $url = '', int $status = 302, array $headers = []): RedirectResponse
    {
        return new RedirectResponse($url, $status, $headers);
    }

    public static function view(string $path, array $variables = [], array $jsVariables = []): static
    {
        if (strpos($path, '@') !== 0) {
            $route = App::service('request')->getRoute();
            $path = "@{$route->getModule()->getId()}/$path";
        }
        $html = App::service('twig.environment')->render($path, $variables);
        return new static($html);
    }

    public static function html(string $html)
    {
        $res = new static($html);
        $res->headers->set('content-type', 'text/html; charset=utf-8');
        return $res;
    }

    public static function text(string $text)
    {
        $res = new static($text);
        $res->headers->set('content-type', 'text/plain; charset=utf-8');
        return $res;
    }

    public static function stream(string|callable $callbackOrContent, int $status = 200, array $headers = []): static
    {
        if (is_string($callbackOrContent)) {
            $callbackOrContent = fn () => yield $callbackOrContent;
        }

        return new StreamedResponse($callbackOrContent, $status, $headers);
    }

    public static function streamDownload(string|callable $callbackOrContent, ?string $name = null, array $headers = [], ?string $disposition = 'attachment'): static
    {
        if (is_string($callbackOrContent)) {
            $callbackOrContent = fn () => yield $callbackOrContent;
        }

        $response = new StreamedResponse($callbackOrContent, 200, $headers);

        if ($name) {
            $response->headers->set('Content-Disposition', "$disposition; filename=\"$name\"");
        }

        return $response;
    }

    public static function download(\SplFileInfo|string $file, string $filename = '', array $headers = [])
    {
        $res = new BinaryFileResponse($file, 200, $headers, true, null, false, true);
        $res->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $filename);
        return $res;
    }

    /**
     * Returns the Response as an HTTP string.
     *
     * The string representation of the Response is the same as the
     * one that will be sent to the client only if the prepare() method
     * has been called before.
     *
     * @see prepare()
     */
    public function __toString(): string
    {
        return
            \sprintf('HTTP/%s %s %s', $this->version, $this->statusCode, $this->statusText) . "\r\n" .
            $this->headers . "\r\n" .
            $this->getContent();
    }

    /**
     * Clones the current Response instance.
     */
    public function __clone()
    {
        $this->headers = clone $this->headers;
    }

    /**
     * Prepares the Response before it is sent to the client.
     *
     * This method tweaks the Response to ensure that it is
     * compliant with RFC 2616. Most of the changes are based on
     * the Request that is "associated" with this Response.
     *
     * @return $this
     */
    public function prepare(Request $request): static
    {
        $headers = $this->headers;

        if ($this->isInformational() || $this->isEmpty()) {
            $this->setContent(null);
            $headers->remove('Content-Type');
            $headers->remove('Content-Length');
        } else {
            // Content-type based on the Request
            if (!$headers->has('Content-Type')) {
                $format = $request->getRequestFormat(null);
                if (null !== $format && $mimeType = $request->getMimeType($format)) {
                    $headers->set('Content-Type', $mimeType);
                }
            }

            // Fix Content-Type
            $charset = $this->charset ?: 'UTF-8';
            if (!$headers->has('Content-Type')) {
                $headers->set('Content-Type', 'text/html; charset=' . $charset);
            } else if (
                0 === stripos($headers->get('Content-Type') ?? '', 'text/')
                && false === stripos($headers->get('Content-Type') ?? '', 'charset')
            ) {
                // add the charset
                $headers->set('Content-Type', $headers->get('Content-Type') . '; charset=' . $charset);
            }

            // Fix Content-Length
            if ($headers->has('Transfer-Encoding')) {
                $headers->remove('Content-Length');
            }

            if ($request->isMethod('HEAD')) {
                // cf. RFC2616 14.13
                $length = $headers->get('Content-Length');
                $this->setContent(null);
                if ($length) {
                    $headers->set('Content-Length', $length);
                }
            }
        }

        // Fix protocol
        if ('HTTP/1.0' != $request->protocol()) {
            $this->setProtocolVersion('1.1');
        }

        // Check if we need to send extra expire info headers
        if (
            '1.0' == $this->getProtocolVersion()
            && str_contains($headers->get('Cache-Control', ''), 'no-cache')
        ) {
            $headers->set('pragma', 'no-cache');
            $headers->set('expires', '-1');
        }

        $this->ensureIEOverSSLCompatibility($request);

        if ($request->isSecure()) {
            foreach ($headers->getCookies() as $cookie) {
                $cookie->setSecureDefault(true);
            }
        }

        return $this;
    }

    /**
     * Sends HTTP headers.
     */
    public function sendHeaders(SwooleResponse $swooleResponse, ?int $statusCode = null): static
    {
        foreach ($this->headers->all() as $name => $value) {
            if (is_array($value)) {
                foreach ($value as $item) {
                    if ($item instanceof Cookie) {
                        $swooleResponse->setCookie(
                            $item->getName(),
                            $item->getValue(),
                            $item->getExpiresTime(),
                            $item->getPath(),
                            $item->getDomain() ?? '',
                            $item->isSecure(),
                            $item->isHttpOnly(),
                            $item->getSameSite() ?? ''
                        );
                    } else {
                        $swooleResponse->setHeader($name, $item, false);
                    }
                }
                continue;
            }
            $swooleResponse->setHeader($name, $value);
        }
        $statusCode ??= $this->statusCode;
        $swooleResponse->setStatusCode($statusCode, $this->statusText);
        return $this;
    }

    /**
     * Sends content for the current web response.
     */
    public function sendContent(SwooleResponse $swooleResponse): static
    {
        $swooleResponse->end($this->content);
        return $this;
    }

    /**
     * Sends HTTP headers and content.
     */
    public function send(SwooleResponse $swooleResponse): static
    {
        return $this
            ->sendHeaders($swooleResponse)
            ->sendContent($swooleResponse);
    }

    /**
     * Sets the response content.
     */
    public function setContent(?string $content): static
    {
        $this->content = $content ?? '';

        return $this;
    }

    /**
     * Gets the current response content.
     */
    public function getContent(): string|false
    {
        return $this->content;
    }

    /**
     * Sets the HTTP protocol version (1.0 or 1.1).
     */
    public function setProtocolVersion(string $version): static
    {
        $this->version = $version;

        return $this;
    }

    /**
     * Gets the HTTP protocol version.
     */
    public function getProtocolVersion(): string
    {
        return $this->version;
    }

    /**
     * Sets the response status code.
     *
     * If the status text is null it will be automatically populated for the known
     * status codes and left empty otherwise.
     *
     * @throws \InvalidArgumentException When the HTTP status code is not valid
     */
    public function setStatusCode(int $code, ?string $text = null): static
    {
        $this->statusCode = $code;
        if ($this->isInvalid()) {
            throw new \InvalidArgumentException(\sprintf('The HTTP status code "%s" is not valid.', $code));
        }

        if (null === $text) {
            $this->statusText = self::$statusTexts[$code] ?? 'unknown status';

            return $this;
        }

        $this->statusText = $text;

        return $this;
    }

    /**
     * Retrieves the status code for the current web response.
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * Sets the response charset.
     */
    public function setCharset(string $charset): static
    {
        $this->charset = $charset;

        return $this;
    }

    /**
     * Retrieves the response charset.
     */
    public function getCharset(): ?string
    {
        return $this->charset;
    }

    /**
     * Returns true if the response may safely be kept in a shared (surrogate) cache.
     *
     * Responses marked "private" with an explicit Cache-Control directive are
     * considered uncacheable.
     *
     * Responses with neither a freshness lifetime (Expires, max-age) nor cache
     * validator (Last-Modified, ETag) are considered uncacheable because there is
     * no way to tell when or how to remove them from the cache.
     *
     * Note that RFC 7231 and RFC 7234 possibly allow for a more permissive implementation,
     * for example "status codes that are defined as cacheable by default [...]
     * can be reused by a cache with heuristic expiration unless otherwise indicated"
     * (https://tools.ietf.org/html/rfc7231#section-6.1)
     *
     * @final
     */
    public function isCacheable(): bool
    {
        if (!\in_array($this->statusCode, [200, 203, 300, 301, 302, 404, 410])) {
            return false;
        }

        if ($this->headers->hasCacheControlDirective('no-store') || $this->headers->getCacheControlDirective('private')) {
            return false;
        }

        return $this->isValidateable() || $this->isFresh();
    }

    /**
     * Returns true if the response is "fresh".
     *
     * Fresh responses may be served from cache without any interaction with the
     * origin. A response is considered fresh when it includes a Cache-Control/max-age
     * indicator or Expires header and the calculated age is less than the freshness lifetime.
     */
    public function isFresh(): bool
    {
        return $this->getTtl() > 0;
    }

    /**
     * Returns true if the response includes headers that can be used to validate
     * the response with the origin server using a conditional GET request.
     */
    public function isValidateable(): bool
    {
        return $this->headers->has('Last-Modified') || $this->headers->has('ETag');
    }

    /**
     * Marks the response as "private".
     *
     * It makes the response ineligible for serving other clients.
     */
    public function setPrivate(): static
    {
        $this->headers->removeCacheControlDirective('public');
        $this->headers->addCacheControlDirective('private');

        return $this;
    }

    /**
     * Marks the response as "public".
     *
     * It makes the response eligible for serving other clients.
     */
    public function setPublic(): static
    {
        $this->headers->addCacheControlDirective('public');
        $this->headers->removeCacheControlDirective('private');

        return $this;
    }

    /**
     * Marks the response as "immutable".
     */
    public function setImmutable(bool $immutable = true): static
    {
        if ($immutable) {
            $this->headers->addCacheControlDirective('immutable');
        } else {
            $this->headers->removeCacheControlDirective('immutable');
        }

        return $this;
    }

    /**
     * Returns true if the response is marked as "immutable".
     */
    public function isImmutable(): bool
    {
        return $this->headers->hasCacheControlDirective('immutable');
    }

    /**
     * Returns true if the response must be revalidated by shared caches once it has become stale.
     *
     * This method indicates that the response must not be served stale by a
     * cache in any circumstance without first revalidating with the origin.
     * When present, the TTL of the response should not be overridden to be
     * greater than the value provided by the origin.
     */
    public function mustRevalidate(): bool
    {
        return $this->headers->hasCacheControlDirective('must-revalidate') || $this->headers->hasCacheControlDirective('proxy-revalidate');
    }

    /**
     * Returns the Date header as a DateTime instance.
     *
     * @throws \RuntimeException When the header is not parseable
     */
    public function getDate(): ?\DateTimeImmutable
    {
        return $this->headers->getDate('Date');
    }

    /**
     * Sets the Date header.
     */
    public function setDate(\DateTimeInterface $date): static
    {
        $date = \DateTimeImmutable::createFromInterface($date);
        $date = $date->setTimezone(new \DateTimeZone('UTC'));
        $this->headers->set('Date', $date->format('D, d M Y H:i:s') . ' GMT');

        return $this;
    }

    /**
     * Returns the age of the response in seconds.
     */
    public function getAge(): int
    {
        if (null !== $age = $this->headers->get('Age')) {
            return (int) $age;
        }

        return max(time() - (int) $this->getDate()->format('U'), 0);
    }

    /**
     * Marks the response stale by setting the Age header to be equal to the maximum age of the response.
     */
    public function expire(): static
    {
        if ($this->isFresh()) {
            $this->headers->set('Age', $this->getMaxAge());
            $this->headers->remove('Expires');
        }

        return $this;
    }

    /**
     * Returns the value of the Expires header as a DateTime instance.
     */
    public function getExpires(): ?\DateTimeImmutable
    {
        try {
            return $this->headers->getDate('Expires');
        } catch (\RuntimeException) {
            // according to RFC 2616 invalid date formats (e.g. "0" and "-1") must be treated as in the past
            return \DateTimeImmutable::createFromFormat('U', strval(time() - 172800));
        }
    }

    /**
     * Sets the Expires HTTP header with a DateTime instance.
     *
     * Passing null as value will remove the header.
     */
    public function setExpires(?\DateTimeInterface $date): static
    {
        if (null === $date) {
            $this->headers->remove('Expires');

            return $this;
        }

        $date = \DateTimeImmutable::createFromInterface($date);
        $date = $date->setTimezone(new \DateTimeZone('UTC'));
        $this->headers->set('Expires', $date->format('D, d M Y H:i:s') . ' GMT');

        return $this;
    }

    /**
     * Returns the number of seconds after the time specified in the response's Date
     * header when the response should no longer be considered fresh.
     *
     * First, it checks for a s-maxage directive, then a max-age directive, and then it falls
     * back on an expires header. It returns null when no maximum age can be established.
     */
    public function getMaxAge(): ?int
    {
        if ($this->headers->hasCacheControlDirective('s-maxage')) {
            return (int) $this->headers->getCacheControlDirective('s-maxage');
        }

        if ($this->headers->hasCacheControlDirective('max-age')) {
            return (int) $this->headers->getCacheControlDirective('max-age');
        }

        if (null !== $expires = $this->getExpires()) {
            $maxAge = (int) $expires->format('U') - (int) $this->getDate()->format('U');

            return max($maxAge, 0);
        }

        return null;
    }

    /**
     * Sets the number of seconds after which the response should no longer be considered fresh.
     *
     * This method sets the Cache-Control max-age directive.
     */
    public function setMaxAge(int $value): static
    {
        $this->headers->addCacheControlDirective('max-age', strval($value));

        return $this;
    }

    /**
     * Sets the number of seconds after which the response should no longer be returned by shared caches when backend is down.
     *
     * This method sets the Cache-Control stale-if-error directive.
     */
    public function setStaleIfError(int $value): static
    {
        $this->headers->addCacheControlDirective('stale-if-error', strval($value));

        return $this;
    }

    /**
     * Sets the number of seconds after which the response should no longer return stale content by shared caches.
     *
     * This method sets the Cache-Control stale-while-revalidate directive.
     */
    public function setStaleWhileRevalidate(int $value): static
    {
        $this->headers->addCacheControlDirective('stale-while-revalidate', strval($value));

        return $this;
    }

    /**
     * Sets the number of seconds after which the response should no longer be considered fresh by shared caches.
     *
     * This method sets the Cache-Control s-maxage directive.
     */
    public function setSharedMaxAge(int $value): static
    {
        $this->setPublic();
        $this->headers->addCacheControlDirective('s-maxage', strval($value));

        return $this;
    }

    /**
     * Returns the response's time-to-live in seconds.
     *
     * It returns null when no freshness information is present in the response.
     *
     * When the response's TTL is 0, the response may not be served from cache without first
     * revalidating with the origin.
     */
    public function getTtl(): ?int
    {
        $maxAge = $this->getMaxAge();

        return null !== $maxAge ? max($maxAge - $this->getAge(), 0) : null;
    }

    /**
     * Sets the response's time-to-live for shared caches in seconds.
     *
     * This method adjusts the Cache-Control/s-maxage directive.
     */
    public function setTtl(int $seconds): static
    {
        $this->setSharedMaxAge($this->getAge() + $seconds);

        return $this;
    }

    /**
     * Sets the response's time-to-live for private/client caches in seconds.
     *
     * This method adjusts the Cache-Control/max-age directive.
     */
    public function setClientTtl(int $seconds): static
    {
        $this->setMaxAge($this->getAge() + $seconds);

        return $this;
    }

    /**
     * Returns the Last-Modified HTTP header as a DateTime instance.
     *
     * @throws \RuntimeException When the HTTP header is not parseable
     */
    public function getLastModified(): ?\DateTimeImmutable
    {
        return $this->headers->getDate('Last-Modified');
    }

    /**
     * Sets the Last-Modified HTTP header with a DateTime instance.
     *
     * Passing null as value will remove the header.
     */
    public function setLastModified(?\DateTimeInterface $date): static
    {
        if (null === $date) {
            $this->headers->remove('Last-Modified');

            return $this;
        }

        $date = \DateTimeImmutable::createFromInterface($date);
        $date = $date->setTimezone(new \DateTimeZone('UTC'));
        $this->headers->set('Last-Modified', $date->format('D, d M Y H:i:s') . ' GMT');

        return $this;
    }

    /**
     * Returns the literal value of the ETag HTTP header.
     */
    public function getEtag(): ?string
    {
        return $this->headers->get('ETag');
    }

    /**
     * Sets the ETag value.
     *
     * @param string|null $etag The ETag unique identifier or null to remove the header
     * @param bool        $weak Whether you want a weak ETag or not
     */
    public function setEtag(?string $etag, bool $weak = false): static
    {
        if (null === $etag) {
            $this->headers->remove('Etag');
        } else {
            if (!str_starts_with($etag, '"')) {
                $etag = '"' . $etag . '"';
            }

            $this->headers->set('ETag', (true === $weak ? 'W/' : '') . $etag);
        }

        return $this;
    }

    /**
     * Sets the response's cache headers (validation and/or expiration).
     *
     * Available options are: must_revalidate, no_cache, no_store, no_transform, public, private, proxy_revalidate, max_age, s_maxage, immutable, last_modified and etag.
     *
     * @throws \InvalidArgumentException
     */
    public function setCache(array $options): static
    {
        if ($diff = array_diff(array_keys($options), array_keys(self::HTTP_RESPONSE_CACHE_CONTROL_DIRECTIVES))) {
            throw new \InvalidArgumentException(\sprintf('Response does not support the following options: "%s".', implode('", "', $diff)));
        }

        if (isset($options['etag'])) {
            $this->setEtag($options['etag']);
        }

        if (isset($options['last_modified'])) {
            $this->setLastModified($options['last_modified']);
        }

        if (isset($options['max_age'])) {
            $this->setMaxAge($options['max_age']);
        }

        if (isset($options['s_maxage'])) {
            $this->setSharedMaxAge($options['s_maxage']);
        }

        if (isset($options['stale_while_revalidate'])) {
            $this->setStaleWhileRevalidate($options['stale_while_revalidate']);
        }

        if (isset($options['stale_if_error'])) {
            $this->setStaleIfError($options['stale_if_error']);
        }

        foreach (self::HTTP_RESPONSE_CACHE_CONTROL_DIRECTIVES as $directive => $hasValue) {
            if (!$hasValue && isset($options[$directive])) {
                if ($options[$directive]) {
                    $this->headers->addCacheControlDirective(str_replace('_', '-', $directive));
                } else {
                    $this->headers->removeCacheControlDirective(str_replace('_', '-', $directive));
                }
            }
        }

        if (isset($options['public'])) {
            if ($options['public']) {
                $this->setPublic();
            } else {
                $this->setPrivate();
            }
        }

        if (isset($options['private'])) {
            if ($options['private']) {
                $this->setPrivate();
            } else {
                $this->setPublic();
            }
        }

        return $this;
    }

    /**
     * Modifies the response so that it conforms to the rules defined for a 304 status code.
     *
     * This sets the status, removes the body, and discards any headers
     * that MUST NOT be included in 304 responses.
     *
     * @see https://tools.ietf.org/html/rfc2616#section-10.3.5
     */
    public function setNotModified(): static
    {
        $this->setStatusCode(304);
        $this->setContent(null);

        // remove headers that MUST NOT be included with 304 Not Modified responses
        foreach (['Allow', 'Content-Encoding', 'Content-Language', 'Content-Length', 'Content-MD5', 'Content-Type', 'Last-Modified'] as $header) {
            $this->headers->remove($header);
        }

        return $this;
    }

    /**
     * Returns true if the response includes a Vary header.
     */
    public function hasVary(): bool
    {
        return null !== $this->headers->get('Vary');
    }

    /**
     * Returns an array of header names given in the Vary header.
     */
    public function getVary(): array
    {
        if (!$vary = $this->headers->all('Vary')) {
            return [];
        }

        $ret = [];
        foreach ($vary as $item) {
            $ret[] = preg_split('/[\s,]+/', $item);
        }

        return array_merge([], ...$ret);
    }

    /**
     * Sets the Vary header.
     *
     * @param bool $replace Whether to replace the actual value or not (true by default)
     */
    public function setVary(string|array $headers, bool $replace = true): static
    {
        $this->headers->set('Vary', $headers, $replace);

        return $this;
    }

    /**
     * Determines if the Response validators (ETag, Last-Modified) match
     * a conditional value specified in the Request.
     *
     * If the Response is not modified, it sets the status code to 304 and
     * removes the actual content by calling the setNotModified() method.
     */
    public function isNotModified(Request $request): bool
    {
        if (!$request->isMethodCacheable()) {
            return false;
        }

        $notModified = false;
        $lastModified = $this->headers->get('Last-Modified');
        $modifiedSince = $request->headers->get('If-Modified-Since');

        if (($ifNoneMatchEtags = $request->eTags()) && (null !== $etag = $this->getEtag())) {
            if (0 == strncmp($etag, 'W/', 2)) {
                $etag = substr($etag, 2);
            }

            // Use weak comparison as per https://tools.ietf.org/html/rfc7232#section-3.2.
            foreach ($ifNoneMatchEtags as $ifNoneMatchEtag) {
                if (0 == strncmp($ifNoneMatchEtag, 'W/', 2)) {
                    $ifNoneMatchEtag = substr($ifNoneMatchEtag, 2);
                }

                if ($ifNoneMatchEtag === $etag || '*' === $ifNoneMatchEtag) {
                    $notModified = true;
                    break;
                }
            }
        }
        // Only do If-Modified-Since date comparison when If-None-Match is not present as per https://tools.ietf.org/html/rfc7232#section-3.3.
        elseif ($modifiedSince && $lastModified) {
            $notModified = strtotime($modifiedSince) >= strtotime($lastModified);
        }

        if ($notModified) {
            $this->setNotModified();
        }

        return $notModified;
    }

    /**
     * Is response invalid?
     *
     * @see https://www.w3.org/Protocols/rfc2616/rfc2616-sec10.html
     */
    public function isInvalid(): bool
    {
        return $this->statusCode < 100 || $this->statusCode >= 600;
    }

    /**
     * Is response informative?
     *
     * @final
     */
    public function isInformational(): bool
    {
        return $this->statusCode >= 100 && $this->statusCode < 200;
    }

    /**
     * Is response successful?
     */
    public function isSuccessful(): bool
    {
        return $this->statusCode >= 200 && $this->statusCode < 300;
    }

    /**
     * Is the response a redirect?
     */
    public function isRedirection(): bool
    {
        return $this->statusCode >= 300 && $this->statusCode < 400;
    }

    /**
     * Is there a client error?
     */
    public function isClientError(): bool
    {
        return $this->statusCode >= 400 && $this->statusCode < 500;
    }

    /**
     * Was there a server side error?
     */
    public function isServerError(): bool
    {
        return $this->statusCode >= 500 && $this->statusCode < 600;
    }

    /**
     * Is the response OK?
     */
    public function isOk(): bool
    {
        return 200 === $this->statusCode;
    }

    /**
     * Is the response forbidden?
     */
    public function isForbidden(): bool
    {
        return 403 === $this->statusCode;
    }

    /**
     * Is the response a not found error?
     */
    public function isNotFound(): bool
    {
        return 404 === $this->statusCode;
    }

    /**
     * Is the response a redirect of some form?
     */
    public function isRedirect(?string $location = null): bool
    {
        return \in_array($this->statusCode, [201, 301, 302, 303, 307, 308]) && (null === $location ?: $location == $this->headers->get('Location'));
    }

    /**
     * Is the response empty?
     */
    public function isEmpty(): bool
    {
        return \in_array($this->statusCode, [204, 304]);
    }

    /**
     * Marks a response as safe according to RFC8674.
     *
     * @see https://tools.ietf.org/html/rfc8674
     */
    public function setContentSafe(bool $safe = true): void
    {
        if ($safe) {
            $this->headers->set('Preference-Applied', 'safe');
        } elseif ('safe' === $this->headers->get('Preference-Applied')) {
            $this->headers->remove('Preference-Applied');
        }

        $this->setVary('Prefer', false);
    }

    public function with(string $key, mixed $value): static
    {
        Session::flash($key, $value);
        return $this;
    }

    public function withCookie(
        string $name,
        ?string $value = null,
        int|string|\DateTimeInterface $expire = 0,
        ?string $path = '/',
        ?string $domain = null,
        ?bool $secure = null,
        bool $httpOnly = true,
        bool $raw = false,
        ?string $sameSite = Cookie::SAMESITE_LAX,
        bool $partitioned = false
    ): static {
        $this->headers->setCookie(new Cookie(
            $name,
            $value,
            $expire,
            $path,
            $domain,
            $secure,
            $httpOnly,
            $raw,
            $sameSite,
            $partitioned
        ));
        return $this;
    }

    public function withCookies(array $cookies): static
    {
        foreach ($cookies as $cookie) {
            if ($cookie instanceof Cookie) {
                $this->headers->setCookie($cookie);
            } else {
                $this->withCookie(...$cookie);
            }
        }
        return $this;
    }

    public function withHeader(string $key, string|array|null $values, bool $replace = true): static
    {
        $this->headers->set($key, $values, $replace);
        return $this;
    }

    public function withHeaders(array $headers): static
    {
        foreach ($headers as $key => $value) {
            $this->withHeader($key, $value);
        }
        return $this;
    }

    public function withoutCookie(string $key): static
    {
        $this->headers->remove($key);
        return $this;
    }

    public function withErrors(array $errors): static
    {
        Session::setErrors($errors);
        return $this;
    }

    public function withInput(?array $inputs = null): static
    {
        Session::flashInput(
            is_null($inputs) ? App::service('request')->all() : $inputs
        );
        return $this;
    }

    /**
     * Checks if we need to remove Cache-Control for SSL encrypted downloads when using IE < 9.
     *
     * @see http://support.microsoft.com/kb/323308
     */
    protected function ensureIEOverSSLCompatibility(Request $request): void
    {
        if (false !== stripos($this->headers->get('Content-Disposition') ?? '', 'attachment') && 1 == preg_match('/MSIE (.*?);/i', $request->server->get('HTTP_USER_AGENT') ?? '', $match) && true === $request->isSecure()) {
            if ((int) preg_replace('/(MSIE )(.*?);/', '$2', $match[0]) < 9) {
                $this->headers->remove('Cache-Control');
            }
        }
    }
}
