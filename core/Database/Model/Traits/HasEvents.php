<?php

namespace Ryra\Core\Database\Model\Traits;

use Ryra\Core\Contracts\Event\EventDispatcher;
use Ryra\Core\Database\Attributes\Event;
use Ryra\Core\Event\NullDispatcher;

trait HasEvents
{
    protected static EventDispatcher $eventDispatcher;

    protected function registerEvents(): void
    {
        $classAttribute = Event::class;
        $methods = $this->getReflectionMethodsByAttribute($classAttribute);
        foreach ($methods as $method) {
            $attribute = $method->getAttributes($classAttribute)[0];
            $event = $attribute->newInstance();
            static::registerModelEvent($event, [static::class, $method->getName()]);
        }
    }
    
    public static function setEventDispatcher(EventDispatcher $eventDispatcher): void
    {
        static::$eventDispatcher = $eventDispatcher;
    }

    public static function creating(callable $callback): void
    {
        static::registerModelEvent(__FUNCTION__, $callback);
    }

    public static function created(callable $callback): void
    {
        static::registerModelEvent(__FUNCTION__, $callback);
    }

    public static function updating(callable $callback): void
    {
        static::registerModelEvent(__FUNCTION__, $callback);
    }

    public static function updated(callable $callback): void
    {
        static::registerModelEvent(__FUNCTION__, $callback);
    }

    public static function saving(callable $callback): void
    {
        static::registerModelEvent(__FUNCTION__, $callback); 
    }

    public static function saved(callable $callback): void
    {
        static::registerModelEvent(__FUNCTION__, $callback);
    }

    public static function deleting(callable $callback): void
    {
        static::registerModelEvent(__FUNCTION__, $callback);
    }

    public static function deleted(callable $callback): void
    {
        static::registerModelEvent(__FUNCTION__, $callback);
    }

    public static function withoutEvents(callable $callback): mixed
    {
        $dispatcher = static::$eventDispatcher;

        static::setEventDispatcher(new NullDispatcher());

        try {
            return $callback();            
        } finally {
            static::setEventDispatcher($dispatcher);
        }
    }

    public function saveQuietly(): bool
    {
        return static::withoutEvents(fn () => $this->save());
    }

    public function deleteQuietly(): bool
    {
        return static::withoutEvents(fn () => $this->delete());
    }

    protected function fireModelEvent(string $event): void
    {
        $class = static::class;
        static::$eventDispatcher->dispatch("model:$class.$event", [$this]);
    }

    protected static function registerModelEvent(string $event, callable $callback): void
    {
        $class = static::class;
        static::$eventDispatcher->listen("model:$class.$event", $callback);
    }
}