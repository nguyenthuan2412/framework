<?php

namespace Ryra\Core\Event\Facade;

use Ryra\Core\Support\Facade;

/**
 * @method static ?object dispatch(string|object $event, array $payload = []) Dispatch một event. Truyền tên class hoặc object event.
 * @method static array getListenersForEvent(string|object $event) Lấy danh sách listeners đăng ký cho event.
 * @method static \Ryra\Core\Contracts\Event\EventDispatcher listen(string $event, array|callable $callable) Đăng ký listener cho event (hỗ trợ wildcard '*').
 * @method static \Ryra\Core\Contracts\Event\EventDispatcher subscriber(string $subscriber) Đăng ký toàn bộ subscriber.
 * @method static void clearListeners(string $event) Xóa tất cả listeners của một event.
 *
 * @see \Ryra\Core\Event\EventManager
 */
class Event extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'events';
    }
}