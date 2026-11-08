<?php

use Ryra\Core\App;
use Ryra\Core\Datastar\DatastarManager;
use Ryra\Core\Datastar\ServerSentEventGenerator;
use Ryra\Core\Datastar\Signals;
use Ryra\Core\Datastar\SseResponse;

if (!function_exists('datastar')) {
    /**
     * Lấy manager Datastar của request hiện tại.
     */
    function datastar(): DatastarManager
    {
        return App::service('datastar');
    }
}

if (!function_exists('sse')) {
    /**
     * Mở luồng SSE, callback nhận ServerSentEventGenerator.
     *
     * ```php
     * return sse(function (ServerSentEventGenerator $sse) {
     *     $sse->patchSignals(['count' => 1]);
     * });
     * ```
     *
     * @param callable(ServerSentEventGenerator, SseResponse): void $handler
     */
    function sse(callable $handler, int $status = 200, array $headers = [], array $options = []): SseResponse
    {
        return App::service('datastar')->stream($handler, $status, $headers, $options);
    }
}

if (!function_exists('signals')) {
    /**
     * Signals client gửi lên trong request hiện tại, truyền key để lấy nhanh
     * một giá trị theo đường dẫn dạng chấm.
     */
    function signals(?string $key = null, mixed $default = null): mixed
    {
        return App::service('datastar')->signals($key, $default);
    }
}
