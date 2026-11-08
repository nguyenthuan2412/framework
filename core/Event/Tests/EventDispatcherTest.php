<?php

namespace Ryra\Core\Event\Tests;

use PHPUnit\Framework\TestCase;
use Ryra\Core\Event\EventDispatcher;
use Ryra\Core\Event\NullDispatcher;

// ---------------------------------------------------------------------------
// Stubs
// ---------------------------------------------------------------------------

class SampleEvent
{
    public function __construct(public string $name = 'default') {}
}

class AnotherEvent
{
    public function __construct(public int $value = 0) {}
}

class SampleSubscriber implements \Ryra\Core\Contracts\Event\EventSubscriber
{
    public static array $called = [];

    public static function getSubscribedEvents(): array
    {
        return [
            SampleEvent::class => 'onSample',
            AnotherEvent::class => ['onAnother', 'onAnotherSecond'],
        ];
    }

    public function subscribe(\Ryra\Core\Contracts\Event\EventDispatcher $dispatcher): void {}

    public function onSample(SampleEvent $event): void
    {
        static::$called[] = 'onSample:' . $event->name;
    }

    public function onAnother(AnotherEvent $event): void
    {
        static::$called[] = 'onAnother:' . $event->value;
    }

    public function onAnotherSecond(AnotherEvent $event): void
    {
        static::$called[] = 'onAnotherSecond:' . $event->value;
    }
}

// ---------------------------------------------------------------------------
// EventDispatcher Tests
// ---------------------------------------------------------------------------

class EventDispatcherTest extends TestCase
{
    private EventDispatcher $dispatcher;

    protected function setUp(): void
    {
        $this->dispatcher = new EventDispatcher();
        SampleSubscriber::$called = [];
    }

    // --- listen + dispatch ---

    public function test_dispatches_to_callable_listener_with_object_event(): void
    {
        $received = [];
        $this->dispatcher->listen(SampleEvent::class, function (SampleEvent $e) use (&$received) {
            $received[] = $e->name;
        });

        $this->dispatcher->dispatch(new SampleEvent('hello'));

        $this->assertSame(['hello'], $received);
    }

    public function test_dispatches_to_multiple_listeners_in_order(): void
    {
        $log = [];
        $this->dispatcher->listen(SampleEvent::class, function () use (&$log) { $log[] = 'first'; });
        $this->dispatcher->listen(SampleEvent::class, function () use (&$log) { $log[] = 'second'; });

        $this->dispatcher->dispatch(new SampleEvent());

        $this->assertSame(['first', 'second'], $log);
    }

    public function test_passes_extra_payload_to_listener(): void
    {
        $extra = null;
        $this->dispatcher->listen(SampleEvent::class, function (SampleEvent $e, $x) use (&$extra) {
            $extra = $x;
        });

        $this->dispatcher->dispatch(new SampleEvent(), ['bonus']);

        $this->assertSame('bonus', $extra);
    }

    public function test_dispatch_returns_the_event_object(): void
    {
        $event = new SampleEvent('ret');
        $result = $this->dispatcher->dispatch($event);

        $this->assertSame($event, $result);
    }

    public function test_dispatch_plain_string_event_returns_null(): void
    {
        $this->dispatcher->listen('plain.event', function () {});

        $result = $this->dispatcher->dispatch('plain.event');

        $this->assertNull($result);
    }

    public function test_stops_propagation_when_listener_returns_false(): void
    {
        $log = [];
        $this->dispatcher->listen(SampleEvent::class, function () use (&$log) { $log[] = 'a'; return false; });
        $this->dispatcher->listen(SampleEvent::class, function () use (&$log) { $log[] = 'b'; });

        $this->dispatcher->dispatch(new SampleEvent());

        $this->assertSame(['a'], $log);
    }

    public function test_dispatch_with_no_listeners_returns_event(): void
    {
        $event = new SampleEvent('lonely');
        $result = $this->dispatcher->dispatch($event);

        $this->assertInstanceOf(SampleEvent::class, $result);
    }

    // --- getListenersForEvent ---

    public function test_get_listeners_by_class_name(): void
    {
        $cb = fn() => null;
        $this->dispatcher->listen(SampleEvent::class, $cb);

        $this->assertSame([$cb], $this->dispatcher->getListenersForEvent(SampleEvent::class));
    }

    public function test_get_listeners_by_object_instance(): void
    {
        $cb = fn() => null;
        $this->dispatcher->listen(SampleEvent::class, $cb);

        $this->assertSame([$cb], $this->dispatcher->getListenersForEvent(new SampleEvent()));
    }

    public function test_get_listeners_returns_empty_when_none_registered(): void
    {
        $this->assertSame([], $this->dispatcher->getListenersForEvent(SampleEvent::class));
    }

    // --- wildcard ---

    public function test_wildcard_registered_before_specific_event(): void
    {
        $log = [];
        $this->dispatcher->listen('Sample*', function () use (&$log) { $log[] = 'wildcard'; });
        $this->dispatcher->listen(SampleEvent::class, function () use (&$log) { $log[] = 'specific'; });

        $this->dispatcher->dispatch(new SampleEvent());

        $this->assertContains('wildcard', $log);
        $this->assertContains('specific', $log);
    }

    public function test_wildcard_registered_after_specific_event(): void
    {
        $log = [];
        $this->dispatcher->listen(SampleEvent::class, function () use (&$log) { $log[] = 'specific'; });
        $this->dispatcher->listen('Sample*', function () use (&$log) { $log[] = 'wildcard'; });

        $this->dispatcher->dispatch(new SampleEvent());

        $this->assertContains('specific', $log);
        $this->assertContains('wildcard', $log);
    }

    public function test_wildcard_does_not_match_unrelated_event(): void
    {
        $log = [];
        $this->dispatcher->listen('Another*', function () use (&$log) { $log[] = 'wildcard'; });
        $this->dispatcher->listen(SampleEvent::class, function () use (&$log) { $log[] = 'specific'; });

        $this->dispatcher->dispatch(new SampleEvent());

        $this->assertSame(['specific'], $log);
    }

    // --- subscriber ---

    public function test_subscriber_registers_single_method(): void
    {
        $this->dispatcher->subscriber(SampleSubscriber::class);
        $this->dispatcher->dispatch(new SampleEvent('sub'));

        $this->assertContains('onSample:sub', SampleSubscriber::$called);
    }

    public function test_subscriber_registers_multiple_methods(): void
    {
        $this->dispatcher->subscriber(SampleSubscriber::class);
        $this->dispatcher->dispatch(new AnotherEvent(42));

        $this->assertContains('onAnother:42', SampleSubscriber::$called);
        $this->assertContains('onAnotherSecond:42', SampleSubscriber::$called);
    }

    public function test_subscriber_methods_called_in_declared_order(): void
    {
        $this->dispatcher->subscriber(SampleSubscriber::class);
        $this->dispatcher->dispatch(new AnotherEvent(1));

        $idx1 = array_search('onAnother:1', SampleSubscriber::$called);
        $idx2 = array_search('onAnotherSecond:1', SampleSubscriber::$called);

        $this->assertLessThan($idx2, $idx1);
    }

    // --- clearListeners ---

    public function test_clear_listeners_removes_all_for_event(): void
    {
        $this->dispatcher->listen(SampleEvent::class, fn() => null);
        $this->dispatcher->clearListeners(SampleEvent::class);

        $this->assertSame([], $this->dispatcher->getListenersForEvent(SampleEvent::class));
    }

    public function test_clear_listeners_non_existent_event_does_not_throw(): void
    {
        $this->expectNotToPerformAssertions();
        $this->dispatcher->clearListeners('does.not.exist');
    }

    public function test_clear_listeners_only_removes_specified_event(): void
    {
        $cb = fn() => null;
        $this->dispatcher->listen(SampleEvent::class, $cb);
        $this->dispatcher->listen(AnotherEvent::class, $cb);

        $this->dispatcher->clearListeners(SampleEvent::class);

        $this->assertSame([], $this->dispatcher->getListenersForEvent(SampleEvent::class));
        $this->assertSame([$cb], $this->dispatcher->getListenersForEvent(AnotherEvent::class));
    }
}

// ---------------------------------------------------------------------------
// NullDispatcher Tests
// ---------------------------------------------------------------------------

class NullDispatcherTest extends TestCase
{
    private NullDispatcher $dispatcher;

    protected function setUp(): void
    {
        $this->dispatcher = new NullDispatcher();
        SampleSubscriber::$called = [];
    }

    public function test_dispatch_always_returns_null(): void
    {
        $this->assertNull($this->dispatcher->dispatch(new SampleEvent()));
        $this->assertNull($this->dispatcher->dispatch('any.event'));
    }

    public function test_get_listeners_always_returns_empty_array(): void
    {
        $this->assertSame([], $this->dispatcher->getListenersForEvent(SampleEvent::class));
    }

    public function test_listen_returns_self_fluent(): void
    {
        $result = $this->dispatcher->listen(SampleEvent::class, fn() => null);
        $this->assertSame($this->dispatcher, $result);
    }

    public function test_subscriber_returns_self_fluent(): void
    {
        $result = $this->dispatcher->subscriber(SampleSubscriber::class);
        $this->assertSame($this->dispatcher, $result);
    }

    public function test_clear_listeners_does_nothing(): void
    {
        $this->expectNotToPerformAssertions();
        $this->dispatcher->clearListeners('any.event');
    }
}
