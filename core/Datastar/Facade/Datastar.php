<?php

namespace Ryra\Core\Datastar\Facade;

use Ryra\Core\Datastar\ServerSentEventGenerator;
use Ryra\Core\Datastar\Signals;
use Ryra\Core\Datastar\SseResponse;
use Ryra\Core\Http\Response;
use Ryra\Core\Support\Facade;

/**
 * @method static SseResponse stream(callable $handler, int $status = 200, array $headers = [], array $options = [])
 * @method static SseResponse patchElements(string|iterable $elements, mixed ...$arguments)
 * @method static SseResponse patchSignals(array|string $signals, mixed ...$arguments)
 * @method static SseResponse patchView(string $view, array $context = [], ?string $block = null, mixed ...$arguments)
 * @method static Response page(string $view, array $context = [], string|array $fragments = 'content', ?string $url = null, ?string $title = null)
 * @method static string[] requestedFragments()
 * @method static SseResponse redirect(string $url)
 * @method static ServerSentEventGenerator buffer()
 * @method static Signals|mixed signals(?string $key = null, mixed $default = null)
 * @method static bool isDatastarRequest()
 * @method static string render(string $view, array $context = [], ?string $block = null)
 * @method static string scriptTag(array $attributes = [])
 * @method static string scriptUrl()
 *
 * @see \Ryra\Core\Datastar\DatastarManager
 */
class Datastar extends Facade
{
    protected static function getFacadeAccessor(): string|object
    {
        return 'datastar';
    }
}
