<?php

namespace Ryra\Core\HttpClient\Facade;

use Ryra\Core\Support\Facade;

/**
 * @method static \Ryra\Core\HttpClient\Response get(string $url, iterable $params = [])
 * @method static \Ryra\Core\HttpClient\Response post(string $url, iterable $params = [])
 * @method static \Ryra\Core\HttpClient\Response put(string $url, iterable $params = [])
 * @method static \Ryra\Core\HttpClient\Response delete(string $url, iterable $params = [])
 * @method static \Ryra\Core\HttpClient\Response head(string $url, iterable $params = [])
 * @method static \Ryra\Core\HttpClient\Response patch(string $url, iterable $params = [])
 * @method static \Ryra\Core\HttpClient\Http timeout(float $timeout)
 * @method static \Ryra\Core\HttpClient\Http connectTimeout(float $timeout)
 * @method static \Ryra\Core\HttpClient\Http withBasicAuth(string $username, string $password)
 * @method static \Ryra\Core\HttpClient\Http withHeader(string $key, string $value)
 * @method static \Ryra\Core\HttpClient\Http withHeaders(iterable $headers)
 * @method static \Ryra\Core\HttpClient\Http replaceHeaders(iterable $headers)
 * @method static \Ryra\Core\HttpClient\Http withToken(string $token)
 * @method static \Ryra\Core\HttpClient\Http withApiKey(string $key)
 * @method static \Ryra\Core\HttpClient\Http baseUrl(string $url)
 * @method static \Ryra\Core\HttpClient\Http v2()
 * @method static \Ryra\Core\HttpClient\Http attach(string $name, string $path, ?string $mimeType = null, ?string $fileName = null, int $offset = 0, int $length = 0)
 * @method static \Ryra\Core\HttpClient\Http accept(string $type)
 * @method static \Ryra\Core\HttpClient\Http acceptJson()
 * @method static \Ryra\Core\HttpClient\Http asForm()
 * @method static \Ryra\Core\HttpClient\Http asJson()
 */
class Http extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'http.client';
    }
}