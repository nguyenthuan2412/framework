<?php

namespace Ryra\Core\Container\Tests;

use PHPUnit\Framework\TestCase;
use Ryra\Core\Container\Container;
use Ryra\Core\Container\Inject;
use Ryra\Core\Container\Exception\ServiceNotFoundException;
use LogicException;

// ---------------------------------------------------------------------------
// Stubs
// ---------------------------------------------------------------------------

class SimpleService
{
    public string $name = 'simple';
}

class AnotherService
{
    public string $name = 'another';
}

class ServiceWithConstructorDep
{
    public function __construct(public readonly SimpleService $simple) {}
}

class ServiceWithOptionalDep
{
    public function __construct(public string $label = 'default') {}
}

class ServiceWithInjectByType
{
    #[Inject]
    public SimpleService $simple;
}

class ServiceWithInjectById
{
    #[Inject('svc_simple')]
    public mixed $service = null;
}

class CallableTarget
{
    public function greet(string $name = 'world'): string
    {
        return "hello $name";
    }

    public function getSimple(SimpleService $simple): SimpleService
    {
        return $simple;
    }
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

class ContainerTest extends TestCase
{
    private Container $container;

    protected function setUp(): void
    {
        $ref = new \ReflectionClass(Container::class);
        $prop = $ref->getProperty('instance');
        $prop->setAccessible(true);
        $prop->setValue(null, null);

        $this->container = Container::getInstance();
    }

    // =========================================================================
    // getInstance
    // =========================================================================

    public function test_getInstance_returns_same_instance(): void
    {
        $a = Container::getInstance();
        $b = Container::getInstance();
        $this->assertSame($a, $b);
    }

    public function test_getInstance_returns_container_instance(): void
    {
        $this->assertInstanceOf(Container::class, Container::getInstance());
    }

    // =========================================================================
    // singleton
    // =========================================================================

    public function test_singleton_with_callable_returns_correct_type(): void
    {
        $this->container->singleton('svc', fn() => new SimpleService());
        $this->assertInstanceOf(SimpleService::class, $this->container->get('svc'));
    }

    public function test_singleton_with_string_class_returns_correct_type(): void
    {
        $this->container->singleton('svc', SimpleService::class);
        $this->assertInstanceOf(SimpleService::class, $this->container->get('svc'));
    }

    public function test_singleton_returns_same_instance_on_multiple_calls(): void
    {
        $this->container->singleton('svc', fn() => new SimpleService());
        $a = $this->container->get('svc');
        $b = $this->container->get('svc');
        $this->assertSame($a, $b);
    }

    public function test_singletonIf_registers_when_not_exists(): void
    {
        $this->container->singletonIf('svc', fn() => new SimpleService());
        $this->assertTrue($this->container->has('svc'));
    }

    public function test_singletonIf_does_not_override_existing_binding(): void
    {
        $this->container->singleton('svc', fn() => new SimpleService());
        $original = $this->container->get('svc');

        $this->container->singletonIf('svc', fn() => new AnotherService());
        $this->assertSame($original, $this->container->get('svc'));
    }

    // =========================================================================
    // transient
    // =========================================================================

    public function test_transient_with_callable_returns_correct_type(): void
    {
        $this->container->transient('svc', fn() => new SimpleService());
        $this->assertInstanceOf(SimpleService::class, $this->container->get('svc'));
    }

    public function test_transient_with_string_class_returns_correct_type(): void
    {
        $this->container->transient('svc', SimpleService::class);
        $this->assertInstanceOf(SimpleService::class, $this->container->get('svc'));
    }

    public function test_transient_returns_new_instance_on_every_call(): void
    {
        $this->container->transient('svc', fn() => new SimpleService());
        $a = $this->container->get('svc');
        $b = $this->container->get('svc');
        $this->assertNotSame($a, $b);
    }

    public function test_transientIf_registers_when_not_exists(): void
    {
        $this->container->transientIf('svc', fn() => new SimpleService());
        $this->assertTrue($this->container->has('svc'));
    }

    public function test_transientIf_does_not_override_existing_binding(): void
    {
        $this->container->transient('svc', fn() => new SimpleService());
        $this->container->transientIf('svc', fn() => new AnotherService());
        $this->assertInstanceOf(SimpleService::class, $this->container->get('svc'));
    }

    // =========================================================================
    // scoped
    // =========================================================================

    public function test_scoped_returns_same_instance_in_same_context(): void
    {
        $this->container->scoped('svc', fn() => new SimpleService());
        $a = $this->container->get('svc');
        $b = $this->container->get('svc');
        $this->assertSame($a, $b);
    }

    public function test_scoped_returns_new_instance_after_forgetScopedInstances(): void
    {
        $this->container->scoped('svc', fn() => new SimpleService());
        $a = $this->container->get('svc');
        $this->container->forgetScopedInstances();
        $b = $this->container->get('svc');
        $this->assertNotSame($a, $b);
    }

    public function test_scopedIf_registers_when_not_exists(): void
    {
        $this->container->scopedIf('svc', fn() => new SimpleService());
        $this->assertTrue($this->container->has('svc'));
    }

    public function test_scopedIf_does_not_override_existing_binding(): void
    {
        $this->container->scoped('svc', fn() => new SimpleService());
        $this->container->scopedIf('svc', fn() => new AnotherService());
        $this->assertInstanceOf(SimpleService::class, $this->container->get('svc'));
    }

    // =========================================================================
    // has / get
    // =========================================================================

    public function test_has_returns_true_for_singleton(): void
    {
        $this->container->singleton('svc', SimpleService::class);
        $this->assertTrue($this->container->has('svc'));
    }

    public function test_has_returns_true_for_transient(): void
    {
        $this->container->transient('svc', SimpleService::class);
        $this->assertTrue($this->container->has('svc'));
    }

    public function test_has_returns_true_for_scoped(): void
    {
        $this->container->scoped('svc', SimpleService::class);
        $this->assertTrue($this->container->has('svc'));
    }

    public function test_has_returns_false_when_not_registered(): void
    {
        $this->assertFalse($this->container->has('nonexistent'));
    }

    public function test_get_throws_ServiceNotFoundException_when_not_registered(): void
    {
        $this->expectException(ServiceNotFoundException::class);
        $this->container->get('nonexistent');
    }

    // =========================================================================
    // alias
    // =========================================================================

    public function test_alias_resolves_to_original_service(): void
    {
        $this->container->singleton('svc', SimpleService::class);
        $this->container->alias('svc', 'svc_alias');
        $this->assertInstanceOf(SimpleService::class, $this->container->get('svc_alias'));
    }

    public function test_alias_has_returns_true(): void
    {
        $this->container->singleton('svc', SimpleService::class);
        $this->container->alias('svc', 'svc_alias');
        $this->assertTrue($this->container->has('svc_alias'));
    }

    public function test_alias_to_itself_throws_LogicException(): void
    {
        $this->expectException(LogicException::class);
        $this->container->alias('svc', 'svc');
    }

    public function test_alias_returns_container_for_chaining(): void
    {
        $this->container->singleton('svc', SimpleService::class);
        $result = $this->container->alias('svc', 'svc_alias');
        $this->assertSame($this->container, $result);
    }

    public function test_getAlias_returns_all_registered_aliases(): void
    {
        $this->container->singleton('svc', SimpleService::class);
        $this->container->alias('svc', 'alias_a');
        $this->container->alias('svc', 'alias_b');
        $aliases = $this->container->getAlias('svc');
        $this->assertContains('alias_a', $aliases);
        $this->assertContains('alias_b', $aliases);
    }

    public function test_getAlias_returns_empty_array_when_no_aliases(): void
    {
        $this->container->singleton('svc', SimpleService::class);
        $this->assertSame([], $this->container->getAlias('svc'));
    }

    // =========================================================================
    // make
    // =========================================================================

    public function test_make_creates_instance_of_given_class(): void
    {
        $instance = Container::make(SimpleService::class);
        $this->assertInstanceOf(SimpleService::class, $instance);
    }

    public function test_make_always_returns_new_instance(): void
    {
        $a = Container::make(SimpleService::class);
        $b = Container::make(SimpleService::class);
        $this->assertNotSame($a, $b);
    }

    public function test_make_injects_constructor_dependencies_from_container(): void
    {
        $this->container->singleton(SimpleService::class, SimpleService::class);
        $instance = Container::make(ServiceWithConstructorDep::class);
        $this->assertInstanceOf(ServiceWithConstructorDep::class, $instance);
        $this->assertInstanceOf(SimpleService::class, $instance->simple);
    }

    public function test_make_uses_default_value_when_dependency_not_registered(): void
    {
        $instance = Container::make(ServiceWithOptionalDep::class);
        $this->assertSame('default', $instance->label);
    }

    public function test_make_passes_explicit_parameters(): void
    {
        $instance = Container::make(ServiceWithOptionalDep::class, ['label' => 'custom']);
        $this->assertSame('custom', $instance->label);
    }

    public function test_make_injects_property_with_Inject_by_type(): void
    {
        $this->container->singleton(SimpleService::class, SimpleService::class);
        $instance = Container::make(ServiceWithInjectByType::class);
        $this->assertInstanceOf(SimpleService::class, $instance->simple);
    }

    public function test_make_injects_property_with_Inject_by_id(): void
    {
        $this->container->singleton('svc_simple', SimpleService::class);
        $instance = Container::make(ServiceWithInjectById::class);
        $this->assertInstanceOf(SimpleService::class, $instance->service);
    }

    // =========================================================================
    // call
    // =========================================================================

    public function test_call_closure_with_no_args(): void
    {
        $result = Container::call(fn() => 42);
        $this->assertSame(42, $result);
    }

    public function test_call_closure_injects_type_hinted_dependency(): void
    {
        $this->container->singleton(SimpleService::class, SimpleService::class);
        $result = Container::call(fn(SimpleService $svc) => $svc);
        $this->assertInstanceOf(SimpleService::class, $result);
    }

    public function test_call_closure_uses_named_parameters_to_override_resolved_deps(): void
    {
        $result = Container::call(
            fn(string $name = 'default') => $name,
            ['name' => 'overridden']
        );
        $this->assertSame('overridden', $result);
    }

    public function test_call_closure_with_positional_parameters(): void
    {
        $result = Container::call(fn(string $a, string $b) => "$a-$b", ['x', 'y']);
        $this->assertSame('x-y', $result);
    }

    public function test_call_array_callback_with_object_instance(): void
    {
        $target = new CallableTarget();
        $result = Container::call([$target, 'greet']);
        $this->assertSame('hello world', $result);
    }

    public function test_call_array_callback_resolves_class_from_container(): void
    {
        $this->container->singleton(SimpleService::class, SimpleService::class);
        $this->container->singleton(CallableTarget::class, CallableTarget::class);
        $result = Container::call([CallableTarget::class, 'getSimple']);
        $this->assertInstanceOf(SimpleService::class, $result);
    }

    public function test_call_array_callback_makes_class_when_not_in_container(): void
    {
        $this->container->singleton(SimpleService::class, SimpleService::class);
        $result = Container::call([CallableTarget::class, 'getSimple']);
        $this->assertInstanceOf(SimpleService::class, $result);
    }

    // =========================================================================
    // ArrayAccess
    // =========================================================================

    public function test_offsetExists_returns_true_for_registered_service(): void
    {
        $this->container->singleton('svc', SimpleService::class);
        $this->assertTrue(isset($this->container['svc']));
    }

    public function test_offsetExists_returns_false_when_not_registered(): void
    {
        $this->assertFalse(isset($this->container['nonexistent']));
    }

    public function test_offsetGet_returns_service(): void
    {
        $this->container->singleton('svc', SimpleService::class);
        $this->assertInstanceOf(SimpleService::class, $this->container['svc']);
    }

    public function test_offsetSet_registers_as_transient(): void
    {
        $this->container['svc'] = fn() => new SimpleService();
        $this->assertTrue($this->container->has('svc'));
        $a = $this->container->get('svc');
        $b = $this->container->get('svc');
        $this->assertNotSame($a, $b);
    }

    // =========================================================================
    // __wakeup
    // =========================================================================

    public function test_wakeup_throws_exception(): void
    {
        $this->expectException(\Exception::class);
        $this->container->__wakeup();
    }

    // =========================================================================
    // copyScopedInstances
    // =========================================================================

    public function test_copyScopedInstances_does_nothing_when_parent_has_no_scoped(): void
    {
        // Should not throw
        $this->container->copyScopedInstances(999, 1000);
        $this->assertTrue(true);
    }
}
