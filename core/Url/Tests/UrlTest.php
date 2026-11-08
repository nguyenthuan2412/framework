<?php

namespace Ryra\Core\Url\Tests;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Ryra\Core\Url\Url;
use Ryra\Core\Url\QueryBag;
use Ryra\Core\Url\Scheme;

class UrlTest extends TestCase
{
    // =========================================================================
    // Scheme Enum
    // =========================================================================

    #[Test]
    public function scheme_default_ports(): void
    {
        $this->assertSame(80, Scheme::Http->defaultPort());
        $this->assertSame(443, Scheme::Https->defaultPort());
        $this->assertSame(21, Scheme::Ftp->defaultPort());
        $this->assertSame(22, Scheme::Ssh->defaultPort());
        $this->assertSame(22, Scheme::Sftp->defaultPort());
        $this->assertSame(80, Scheme::Ws->defaultPort());
        $this->assertSame(443, Scheme::Wss->defaultPort());
        $this->assertSame(990, Scheme::Ftps->defaultPort());
        $this->assertNull(Scheme::Mailto->defaultPort());
        $this->assertNull(Scheme::Tel->defaultPort());
    }

    #[Test]
    public function scheme_is_secure(): void
    {
        $this->assertTrue(Scheme::Https->isSecure());
        $this->assertTrue(Scheme::Wss->isSecure());
        $this->assertTrue(Scheme::Ftps->isSecure());
        $this->assertTrue(Scheme::Sftp->isSecure());
        $this->assertTrue(Scheme::Ssh->isSecure());
        $this->assertFalse(Scheme::Http->isSecure());
        $this->assertFalse(Scheme::Ws->isSecure());
        $this->assertFalse(Scheme::Ftp->isSecure());
    }

    #[Test]
    public function scheme_to_secure(): void
    {
        $this->assertSame(Scheme::Https, Scheme::Http->toSecure());
        $this->assertSame(Scheme::Wss, Scheme::Ws->toSecure());
        $this->assertSame(Scheme::Ftps, Scheme::Ftp->toSecure());
        $this->assertSame(Scheme::Https, Scheme::Https->toSecure());
    }

    #[Test]
    public function scheme_uses_authority(): void
    {
        $this->assertTrue(Scheme::Http->usesAuthority());
        $this->assertTrue(Scheme::Https->usesAuthority());
        $this->assertFalse(Scheme::Mailto->usesAuthority());
        $this->assertFalse(Scheme::Tel->usesAuthority());
    }

    #[Test]
    public function scheme_is_default_port(): void
    {
        $this->assertTrue(Scheme::Http->isDefaultPort(80));
        $this->assertFalse(Scheme::Http->isDefaultPort(443));
        $this->assertTrue(Scheme::Https->isDefaultPort(443));
    }

    #[Test]
    public function scheme_try_from_string(): void
    {
        $this->assertSame(Scheme::Http, Scheme::tryFromString('HTTP'));
        $this->assertSame(Scheme::Https, Scheme::tryFromString('Https'));
        $this->assertNull(Scheme::tryFromString('unknown'));
    }

    // =========================================================================
    // QueryBag
    // =========================================================================

    #[Test]
    public function querybag_from_string(): void
    {
        $q = QueryBag::fromString('a=1&b=2&c=hello');

        $this->assertSame('1', $q->get('a'));
        $this->assertSame('2', $q->get('b'));
        $this->assertSame('hello', $q->get('c'));
        $this->assertCount(3, $q);
    }

    #[Test]
    public function querybag_from_string_with_leading_question_mark(): void
    {
        $q = QueryBag::fromString('?foo=bar');

        $this->assertSame('bar', $q->get('foo'));
    }

    #[Test]
    public function querybag_empty_string(): void
    {
        $q = QueryBag::fromString('');

        $this->assertTrue($q->isEmpty());
        $this->assertSame(0, $q->count());
    }

    #[Test]
    public function querybag_has_and_get(): void
    {
        $q = new QueryBag(['name' => 'test', 'page' => '5']);

        $this->assertTrue($q->has('name'));
        $this->assertFalse($q->has('missing'));
        $this->assertSame('test', $q->get('name'));
        $this->assertSame('default', $q->get('missing', 'default'));
    }

    #[Test]
    public function querybag_keys_and_all(): void
    {
        $q = new QueryBag(['a' => '1', 'b' => '2']);

        $this->assertSame(['a', 'b'], $q->keys());
        $this->assertSame(['a' => '1', 'b' => '2'], $q->all());
    }

    #[Test]
    public function querybag_is_immutable_with(): void
    {
        $q1 = new QueryBag(['a' => '1']);
        $q2 = $q1->with('b', '2');

        $this->assertNotSame($q1, $q2);
        $this->assertFalse($q1->has('b'));
        $this->assertTrue($q2->has('b'));
        $this->assertSame('1', $q2->get('a'));
    }

    #[Test]
    public function querybag_with_many(): void
    {
        $q = (new QueryBag(['a' => '1']))->withMany(['b' => '2', 'c' => '3']);

        $this->assertCount(3, $q);
        $this->assertSame('3', $q->get('c'));
    }

    #[Test]
    public function querybag_without(): void
    {
        $q = new QueryBag(['a' => '1', 'b' => '2', 'c' => '3']);

        $q2 = $q->without('b');
        $this->assertFalse($q2->has('b'));
        $this->assertCount(2, $q2);

        $q3 = $q->without(['a', 'c']);
        $this->assertCount(1, $q3);
        $this->assertTrue($q3->has('b'));
    }

    #[Test]
    public function querybag_only(): void
    {
        $q = (new QueryBag(['a' => '1', 'b' => '2', 'c' => '3']))->only(['a', 'c']);

        $this->assertCount(2, $q);
        $this->assertFalse($q->has('b'));
    }

    #[Test]
    public function querybag_merge(): void
    {
        $q1 = new QueryBag(['a' => '1']);
        $q2 = new QueryBag(['b' => '2']);

        $merged = $q1->merge($q2);
        $this->assertCount(2, $merged);
        $this->assertSame('1', $merged->get('a'));
        $this->assertSame('2', $merged->get('b'));

        $mergedArray = $q1->merge(['c' => '3']);
        $this->assertCount(2, $mergedArray);
    }

    #[Test]
    public function querybag_filter(): void
    {
        $q = (new QueryBag(['a' => '1', 'b' => '', 'c' => '3']))
            ->filter(fn($value) => $value !== '');

        $this->assertCount(2, $q);
        $this->assertFalse($q->has('b'));
    }

    #[Test]
    public function querybag_map(): void
    {
        $q = (new QueryBag(['a' => '1', 'b' => '2']))
            ->map(fn($value) => (int) $value * 10);

        $this->assertSame(10, $q->get('a'));
        $this->assertSame(20, $q->get('b'));
    }

    #[Test]
    public function querybag_to_string_roundtrip(): void
    {
        $original = 'name=hello&page=5&sort=asc';
        $q = QueryBag::fromString($original);

        $this->assertSame($original, $q->toString());
    }

    #[Test]
    public function querybag_to_string_caching(): void
    {
        $q = new QueryBag(['x' => '1', 'y' => '2']);

        $first = $q->toString();
        $second = $q->toString();
        $this->assertSame($first, $second);
    }

    #[Test]
    public function querybag_array_access_read(): void
    {
        $q = new QueryBag(['key' => 'value']);

        $this->assertTrue(isset($q['key']));
        $this->assertSame('value', $q['key']);
        $this->assertFalse(isset($q['nope']));
    }

    #[Test]
    public function querybag_array_access_set_throws(): void
    {
        $this->expectException(\LogicException::class);
        $q = new QueryBag();
        $q['foo'] = 'bar';
    }

    #[Test]
    public function querybag_array_access_unset_throws(): void
    {
        $this->expectException(\LogicException::class);
        $q = new QueryBag(['foo' => 'bar']);
        unset($q['foo']);
    }

    #[Test]
    public function querybag_iterable(): void
    {
        $q = new QueryBag(['a' => '1', 'b' => '2']);
        $collected = [];

        foreach ($q as $key => $value) {
            $collected[$key] = $value;
        }

        $this->assertSame(['a' => '1', 'b' => '2'], $collected);
    }

    #[Test]
    public function querybag_json_serialize(): void
    {
        $q = new QueryBag(['a' => '1', 'b' => '2']);
        $json = json_encode($q);

        $this->assertSame('{"a":"1","b":"2"}', $json);
    }

    #[Test]
    public function querybag_is_empty_is_not_empty(): void
    {
        $empty = new QueryBag();
        $filled = new QueryBag(['a' => '1']);

        $this->assertTrue($empty->isEmpty());
        $this->assertFalse($empty->isNotEmpty());
        $this->assertFalse($filled->isEmpty());
        $this->assertTrue($filled->isNotEmpty());
    }

    // =========================================================================
    // Url - Parsing
    // =========================================================================

    #[Test]
    public function parse_full_url(): void
    {
        $url = Url::parse('https://user:pass@example.com:8080/path/to/page?key=value&foo=bar#section');

        $this->assertSame('https', $url->scheme());
        $this->assertSame('example.com', $url->host());
        $this->assertSame(8080, $url->port());
        $this->assertSame('user', $url->user());
        $this->assertSame('pass', $url->pass());
        $this->assertSame('/path/to/page', $url->path());
        $this->assertSame('value', $url->getQueryParam('key'));
        $this->assertSame('bar', $url->getQueryParam('foo'));
        $this->assertSame('section', $url->fragment());
    }

    #[Test]
    public function parse_simple_url(): void
    {
        $url = Url::parse('https://example.com');

        $this->assertSame('https', $url->scheme());
        $this->assertSame('example.com', $url->host());
        $this->assertNull($url->port());
        $this->assertSame('', $url->path());
        $this->assertTrue($url->query()->isEmpty());
        $this->assertNull($url->fragment());
    }

    #[Test]
    public function parse_url_with_path_only(): void
    {
        $url = Url::parse('/api/v1/users');

        $this->assertNull($url->scheme());
        $this->assertNull($url->host());
        $this->assertSame('/api/v1/users', $url->path());
        $this->assertTrue($url->isRelative());
    }

    #[Test]
    public function parse_normalizes_scheme_and_host_to_lowercase(): void
    {
        $url = Url::parse('HTTPS://Example.COM/Path');

        $this->assertSame('https', $url->scheme());
        $this->assertSame('example.com', $url->host());
        $this->assertSame('/Path', $url->path());
    }

    #[Test]
    public function parse_url_with_query_only(): void
    {
        $url = Url::parse('https://example.com?search=test&page=2');

        $this->assertSame('test', $url->getQueryParam('search'));
        $this->assertSame('2', $url->getQueryParam('page'));
    }

    #[Test]
    public function parse_malformed_url(): void
    {
        $url = Url::parse('http:///invalid');

        $this->assertFalse($url->isAbsolute());
    }

    // =========================================================================
    // Url - Component Accessors
    // =========================================================================

    #[Test]
    public function scheme_enum_accessor(): void
    {
        $url = Url::parse('https://example.com');

        $this->assertSame(Scheme::Https, $url->schemeEnum());
        $this->assertTrue($url->schemeEnum()->isSecure());
    }

    #[Test]
    public function scheme_enum_returns_null_for_unknown(): void
    {
        $url = Url::parse('custom://example.com');

        $this->assertSame('custom', $url->scheme());
        $this->assertNull($url->schemeEnum());
    }

    #[Test]
    public function effective_port(): void
    {
        $urlWithPort = Url::parse('https://example.com:9090');
        $this->assertSame(9090, $urlWithPort->effectivePort());

        $urlWithout = Url::parse('https://example.com');
        $this->assertSame(443, $urlWithout->effectivePort());

        $httpUrl = Url::parse('http://example.com');
        $this->assertSame(80, $httpUrl->effectivePort());
    }

    #[Test]
    public function authority_without_credentials(): void
    {
        $url = Url::parse('https://example.com:8080/path');
        $this->assertSame('example.com:8080', $url->authority());
    }

    #[Test]
    public function authority_with_credentials(): void
    {
        $url = Url::parse('https://admin:secret@example.com/path');
        $this->assertSame('admin:secret@example.com', $url->authority());
    }

    #[Test]
    public function authority_hides_default_port(): void
    {
        $url = Url::parse('https://example.com:443/path');
        $this->assertSame('example.com', $url->authority());
    }

    #[Test]
    public function authority_returns_null_without_host(): void
    {
        $url = Url::parse('/relative/path');
        $this->assertNull($url->authority());
    }

    #[Test]
    public function query_string(): void
    {
        $url = Url::parse('https://example.com?a=1&b=2');
        $this->assertSame('a=1&b=2', $url->queryString());
    }

    // =========================================================================
    // Url - Immutable Modification
    // =========================================================================

    #[Test]
    public function with_scheme(): void
    {
        $url = Url::parse('http://example.com');
        $new = $url->withScheme('https');

        $this->assertNotSame($url, $new);
        $this->assertSame('http', $url->scheme());
        $this->assertSame('https', $new->scheme());
    }

    #[Test]
    public function with_scheme_enum(): void
    {
        $url = Url::parse('http://example.com');
        $new = $url->withScheme(Scheme::Https);

        $this->assertSame('https', $new->scheme());
    }

    #[Test]
    public function with_host(): void
    {
        $url = Url::parse('https://old.com/path');
        $new = $url->withHost('new.com');

        $this->assertSame('old.com', $url->host());
        $this->assertSame('new.com', $new->host());
        $this->assertSame('/path', $new->path());
    }

    #[Test]
    public function with_port(): void
    {
        $url = Url::parse('https://example.com');
        $new = $url->withPort(9090);

        $this->assertNull($url->port());
        $this->assertSame(9090, $new->port());
    }

    #[Test]
    public function with_port_null_removes_port(): void
    {
        $url = Url::parse('https://example.com:8080');
        $new = $url->withPort(null);

        $this->assertNull($new->port());
    }

    #[Test]
    public function with_path(): void
    {
        $url = Url::parse('https://example.com/old');
        $new = $url->withPath('/new/path');

        $this->assertSame('/old', $url->path());
        $this->assertSame('/new/path', $new->path());
    }

    #[Test]
    public function with_path_auto_prepends_slash_when_host_present(): void
    {
        $url = Url::parse('https://example.com');
        $new = $url->withPath('no-leading-slash');

        $this->assertSame('/no-leading-slash', $new->path());
    }

    #[Test]
    public function with_query_string(): void
    {
        $url = Url::parse('https://example.com');
        $new = $url->withQuery('a=1&b=2');

        $this->assertSame('1', $new->getQueryParam('a'));
        $this->assertSame('2', $new->getQueryParam('b'));
    }

    #[Test]
    public function with_query_array(): void
    {
        $url = Url::parse('https://example.com');
        $new = $url->withQuery(['page' => '3', 'sort' => 'name']);

        $this->assertSame('3', $new->getQueryParam('page'));
        $this->assertSame('name', $new->getQueryParam('sort'));
    }

    #[Test]
    public function with_query_bag(): void
    {
        $url = Url::parse('https://example.com');
        $bag = new QueryBag(['x' => '10']);
        $new = $url->withQuery($bag);

        $this->assertSame('10', $new->getQueryParam('x'));
    }

    #[Test]
    public function with_fragment(): void
    {
        $url = Url::parse('https://example.com');
        $new = $url->withFragment('top');

        $this->assertNull($url->fragment());
        $this->assertSame('top', $new->fragment());
    }

    #[Test]
    public function with_fragment_null_removes(): void
    {
        $url = Url::parse('https://example.com#section');
        $new = $url->withFragment(null);

        $this->assertNull($new->fragment());
    }

    #[Test]
    public function with_user_and_pass(): void
    {
        $url = Url::parse('https://example.com');
        $new = $url->withUser('admin')->withPass('secret');

        $this->assertSame('admin', $new->user());
        $this->assertSame('secret', $new->pass());
        $this->assertStringContains('admin:secret@', $new->toString());
    }

    // =========================================================================
    // Url - Query Shortcuts
    // =========================================================================

    #[Test]
    public function with_query_param(): void
    {
        $url = Url::parse('https://example.com?a=1');
        $new = $url->withQueryParam('b', '2');

        $this->assertSame('1', $new->getQueryParam('a'));
        $this->assertSame('2', $new->getQueryParam('b'));
        $this->assertFalse($url->hasQueryParam('b'));
    }

    #[Test]
    public function with_query_params(): void
    {
        $url = Url::parse('https://example.com');
        $new = $url->withQueryParams(['page' => 1, 'limit' => 20]);

        $this->assertSame(1, $new->getQueryParam('page'));
        $this->assertSame(20, $new->getQueryParam('limit'));
    }

    #[Test]
    public function without_query_param(): void
    {
        $url = Url::parse('https://example.com?a=1&b=2&c=3');

        $new = $url->withoutQueryParam('b');
        $this->assertFalse($new->hasQueryParam('b'));
        $this->assertTrue($new->hasQueryParam('a'));

        $new2 = $url->withoutQueryParam(['a', 'c']);
        $this->assertTrue($new2->hasQueryParam('b'));
        $this->assertFalse($new2->hasQueryParam('a'));
    }

    #[Test]
    public function without_query(): void
    {
        $url = Url::parse('https://example.com?a=1&b=2');
        $new = $url->withoutQuery();

        $this->assertTrue($new->query()->isEmpty());
    }

    #[Test]
    public function has_query_param(): void
    {
        $url = Url::parse('https://example.com?exists=yes');

        $this->assertTrue($url->hasQueryParam('exists'));
        $this->assertFalse($url->hasQueryParam('nope'));
    }

    #[Test]
    public function get_query_param_with_default(): void
    {
        $url = Url::parse('https://example.com');

        $this->assertNull($url->getQueryParam('missing'));
        $this->assertSame('fallback', $url->getQueryParam('missing', 'fallback'));
    }

    // =========================================================================
    // Url - Path Manipulation
    // =========================================================================

    #[Test]
    public function with_trailing_slash(): void
    {
        $url = Url::parse('https://example.com/path');
        $new = $url->withTrailingSlash();
        $this->assertSame('/path/', $new->path());

        $already = Url::parse('https://example.com/path/');
        $this->assertSame($already, $already->withTrailingSlash());
    }

    #[Test]
    public function without_trailing_slash(): void
    {
        $url = Url::parse('https://example.com/path/');
        $new = $url->withoutTrailingSlash();
        $this->assertSame('/path', $new->path());

        $root = Url::parse('https://example.com/');
        $this->assertSame($root, $root->withoutTrailingSlash());
    }

    #[Test]
    public function append_path(): void
    {
        $url = Url::parse('https://example.com/api');
        $new = $url->appendPath('v2/users');

        $this->assertSame('/api/v2/users', $new->path());
    }

    #[Test]
    public function append_path_handles_slashes(): void
    {
        $url = Url::parse('https://example.com/api/');
        $new = $url->appendPath('/v2/users');

        $this->assertSame('/api/v2/users', $new->path());
    }

    #[Test]
    public function segments(): void
    {
        $url = Url::parse('https://example.com/blog/2024/my-post');

        $this->assertSame(['blog', '2024', 'my-post'], $url->segments());
    }

    #[Test]
    public function segments_from_root(): void
    {
        $url = Url::parse('https://example.com/');

        $this->assertSame([], $url->segments());
    }

    #[Test]
    public function segment_one_indexed(): void
    {
        $url = Url::parse('https://example.com/blog/2024/post');

        $this->assertSame('blog', $url->segment(1));
        $this->assertSame('2024', $url->segment(2));
        $this->assertSame('post', $url->segment(3));
        $this->assertNull($url->segment(4));
        $this->assertSame('default', $url->segment(99, 'default'));
    }

    #[Test]
    public function dirname_and_basename(): void
    {
        $url = Url::parse('https://example.com/blog/2024/post.html');

        $this->assertSame('/blog/2024', $url->dirname());
        $this->assertSame('post.html', $url->basename());
    }

    #[Test]
    public function extension(): void
    {
        $url = Url::parse('https://example.com/files/document.pdf');
        $this->assertSame('pdf', $url->extension());

        $noExt = Url::parse('https://example.com/path/no-extension');
        $this->assertSame('', $noExt->extension());
    }

    // =========================================================================
    // Url - Validation / Inspection
    // =========================================================================

    #[Test]
    public function is_valid(): void
    {
        $this->assertTrue(Url::parse('https://example.com')->isValid());
        $this->assertTrue(Url::parse('/path')->isValid());
    }

    #[Test]
    public function is_absolute(): void
    {
        $this->assertTrue(Url::parse('https://example.com/path')->isAbsolute());
        $this->assertFalse(Url::parse('/relative/path')->isAbsolute());
        $this->assertFalse(Url::parse('path/only')->isAbsolute());
    }

    #[Test]
    public function is_relative(): void
    {
        $this->assertTrue(Url::parse('/path')->isRelative());
        $this->assertFalse(Url::parse('https://example.com')->isRelative());
    }

    #[Test]
    public function is_secure(): void
    {
        $this->assertTrue(Url::parse('https://example.com')->isSecure());
        $this->assertTrue(Url::parse('wss://example.com')->isSecure());
        $this->assertFalse(Url::parse('http://example.com')->isSecure());
        $this->assertFalse(Url::parse('ws://example.com')->isSecure());
    }

    #[Test]
    public function is_default_port(): void
    {
        $this->assertTrue(Url::parse('https://example.com')->isDefaultPort());
        $this->assertTrue(Url::parse('https://example.com:443')->isDefaultPort());
        $this->assertFalse(Url::parse('https://example.com:8080')->isDefaultPort());
    }

    // =========================================================================
    // Url - Create Factory
    // =========================================================================

    #[Test]
    public function create_from_components(): void
    {
        $url = Url::create(
            scheme: 'https',
            host: 'example.com',
            port: 8080,
            path: '/api/v1',
            query: ['key' => 'value'],
            fragment: 'section',
        );

        $this->assertSame('https://example.com:8080/api/v1?key=value#section', $url->toString());
    }

    #[Test]
    public function create_minimal(): void
    {
        $url = Url::create(path: '/just/a/path');

        $this->assertSame('/just/a/path', $url->toString());
        $this->assertTrue($url->isRelative());
    }

    // =========================================================================
    // Url - Signed URLs
    // =========================================================================

    #[Test]
    public function signed_url(): void
    {
        $url = Url::parse('https://example.com/verify?user=42');
        $key = 'test-secret-key';

        $signed = $url->signed($key);

        $this->assertTrue($signed->hasQueryParam('signature'));
        $this->assertTrue($signed->hasValidSignature($key));
        $this->assertSame('42', $signed->getQueryParam('user'));
    }

    #[Test]
    public function signed_url_invalid_with_wrong_key(): void
    {
        $url = Url::parse('https://example.com/verify');

        $signed = $url->signed('correct-key');

        $this->assertFalse($signed->hasValidSignature('wrong-key'));
    }

    #[Test]
    public function signed_url_invalid_with_tampered_params(): void
    {
        $url = Url::parse('https://example.com/verify?user=42');
        $key = 'secret';

        $signed = $url->signed($key);
        $tampered = $signed->withQueryParam('user', '99');

        $this->assertFalse($tampered->hasValidSignature($key));
    }

    #[Test]
    public function temporary_signed_url(): void
    {
        $url = Url::parse('https://example.com/download');
        $key = 'secret';

        $signed = $url->temporarySignedUrl(3600, $key);

        $this->assertTrue($signed->hasQueryParam('expires'));
        $this->assertTrue($signed->hasQueryParam('signature'));
        $this->assertTrue($signed->hasValidSignature($key));

        $expires = (int) $signed->getQueryParam('expires');
        $this->assertGreaterThan(time(), $expires);
        $this->assertLessThanOrEqual(time() + 3600, $expires);
    }

    #[Test]
    public function temporary_signed_url_expired(): void
    {
        $url = Url::parse('https://example.com/download');
        $key = 'secret';

        $signed = $url
            ->withQueryParam('expires', time() - 100)
            ->signed($key);

        $this->assertFalse($signed->hasValidSignature($key));
    }

    #[Test]
    public function has_valid_signature_returns_false_without_signature(): void
    {
        $url = Url::parse('https://example.com/path');

        $this->assertFalse($url->hasValidSignature('key'));
    }

    // =========================================================================
    // Url - Encoding Helpers
    // =========================================================================

    #[Test]
    public function encode_decode(): void
    {
        $original = 'hello world/foo&bar=baz';
        $encoded = Url::encode($original);
        $decoded = Url::decode($encoded);

        $this->assertSame($original, $decoded);
        $this->assertStringNotContainsString(' ', $encoded);
        $this->assertStringNotContainsString('&', $encoded);
    }

    // =========================================================================
    // Url - Output
    // =========================================================================

    #[Test]
    public function to_string_full_url(): void
    {
        $url = Url::parse('https://user:pass@example.com:8080/path?key=value#frag');

        $this->assertSame('https://user:pass@example.com:8080/path?key=value#frag', $url->toString());
        $this->assertSame($url->toString(), (string) $url);
    }

    #[Test]
    public function to_string_omits_default_port(): void
    {
        $url = Url::parse('https://example.com:443/path');

        $this->assertSame('https://example.com/path', $url->toString());
    }

    #[Test]
    public function to_string_shows_non_default_port(): void
    {
        $url = Url::parse('https://example.com:9090/path');

        $this->assertSame('https://example.com:9090/path', $url->toString());
    }

    #[Test]
    public function to_string_relative_path(): void
    {
        $url = Url::parse('/api/v1/users?page=2');

        $this->assertSame('/api/v1/users?page=2', $url->toString());
    }

    #[Test]
    public function to_string_caching(): void
    {
        $url = Url::parse('https://example.com/path');

        $first = $url->toString();
        $second = $url->toString();

        $this->assertSame($first, $second);
    }

    #[Test]
    public function to_array(): void
    {
        $url = Url::parse('https://example.com:8080/path?a=1#frag');
        $arr = $url->toArray();

        $this->assertSame('https', $arr['scheme']);
        $this->assertSame('example.com', $arr['host']);
        $this->assertSame(8080, $arr['port']);
        $this->assertSame('/path', $arr['path']);
        $this->assertSame(['a' => '1'], $arr['query']);
        $this->assertSame('frag', $arr['fragment']);
    }

    #[Test]
    public function to_json(): void
    {
        $url = Url::parse('https://example.com/path');
        $json = $url->toJson();
        $decoded = json_decode($json, true);

        $this->assertSame('https', $decoded['scheme']);
        $this->assertSame('example.com', $decoded['host']);
        $this->assertSame('/path', $decoded['path']);
    }

    #[Test]
    public function json_serialize(): void
    {
        $url = Url::parse('https://example.com/path');

        $this->assertSame('"https:\/\/example.com\/path"', json_encode($url));
    }

    // =========================================================================
    // Url - Fluent Chaining
    // =========================================================================

    #[Test]
    public function fluent_chaining(): void
    {
        $url = Url::parse('http://example.com')
            ->withScheme('https')
            ->withHost('api.example.com')
            ->withPort(9090)
            ->withPath('/v2/users')
            ->withQueryParam('page', 1)
            ->withQueryParam('limit', 20)
            ->withFragment('results');

        $this->assertSame(
            'https://api.example.com:9090/v2/users?page=1&limit=20#results',
            $url->toString()
        );
    }

    #[Test]
    public function immutability_chain(): void
    {
        $original = Url::parse('https://example.com/path?a=1');
        $modified = $original
            ->withPath('/other')
            ->withQueryParam('b', 2)
            ->withFragment('top');

        $this->assertSame('https://example.com/path?a=1', $original->toString());
        $this->assertSame('https://example.com/other?a=1&b=2#top', $modified->toString());
    }

    // =========================================================================
    // Url - Edge Cases
    // =========================================================================

    #[Test]
    public function empty_url(): void
    {
        $url = Url::parse('');

        $this->assertNull($url->scheme());
        $this->assertNull($url->host());
        $this->assertSame('', $url->path());
    }

    #[Test]
    public function url_with_only_fragment(): void
    {
        $url = Url::parse('#section');

        $this->assertSame('section', $url->fragment());
        $this->assertSame('', $url->path());
    }

    #[Test]
    public function url_with_special_characters_in_query(): void
    {
        $url = Url::parse('https://example.com?q=hello+world&tag=c%2B%2B');

        $this->assertSame('hello world', $url->getQueryParam('q'));
        $this->assertSame('c++', $url->getQueryParam('tag'));
    }

    #[Test]
    public function url_with_ipv4_host(): void
    {
        $url = Url::parse('http://192.168.1.1:3000/api');

        $this->assertSame('192.168.1.1', $url->host());
        $this->assertSame(3000, $url->port());
    }

    #[Test]
    public function url_with_nested_query_params(): void
    {
        $url = Url::parse('https://example.com?filters[status]=active&filters[type]=premium');

        $filters = $url->getQueryParam('filters');
        $this->assertIsArray($filters);
        $this->assertSame('active', $filters['status']);
        $this->assertSame('premium', $filters['type']);
    }

    #[Test]
    public function preserves_all_components_through_modification(): void
    {
        $url = Url::parse('https://user:pass@example.com:8080/path?key=val#frag');
        $modified = $url->withPath('/new');

        $this->assertSame('https', $modified->scheme());
        $this->assertSame('example.com', $modified->host());
        $this->assertSame(8080, $modified->port());
        $this->assertSame('user', $modified->user());
        $this->assertSame('pass', $modified->pass());
        $this->assertSame('/new', $modified->path());
        $this->assertSame('val', $modified->getQueryParam('key'));
        $this->assertSame('frag', $modified->fragment());
    }

    // =========================================================================
    // Helper: assertStringContains (for older PHPUnit compat)
    // =========================================================================

    private function assertStringContains(string $needle, string $haystack): void
    {
        $this->assertStringContainsString($needle, $haystack);
    }
}
