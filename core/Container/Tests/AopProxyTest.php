<?php

namespace Ryra\Core\Container\Tests;

use PHPUnit\Framework\TestCase;
use Ryra\Core\Container\AOP\AopProxy;
use Ryra\Core\Container\AOP\AopProxyManager;
use Ryra\Core\Container\AOP\AroundInterface;
use Ryra\Core\Container\AOP\Aspect;
use Ryra\Core\Container\AOP\AspectCollector;
use Ryra\Core\Container\AOP\ProceedingJoinPoint;
use Ryra\Core\Container\Container;
use Ryra\Core\Container\Inject;
use Ryra\Core\Container\Proxy\ProxyManager;

// ---------------------------------------------------------------------------
// Stubs
// ---------------------------------------------------------------------------

#[\Attribute(\Attribute::TARGET_METHOD)]
class AopTestLogged {}

class AopTestLog
{
    public static array $lines = [];
}

#[Aspect(classes: [AopTestService::class . '::greet', AopTestService::class . '::sum', AopTestService::class . '::touch'])]
class AopTestTraceAspect implements AroundInterface
{
    public function process(ProceedingJoinPoint $joinPoint): mixed
    {
        AopTestLog::$lines[] = "trace:before:{$joinPoint->methodName}";
        $result = $joinPoint->process();
        AopTestLog::$lines[] = "trace:after:{$joinPoint->methodName}";
        return $result;
    }
}

#[Aspect(classes: [AopTestService::class . '::greet'], priority: 10)]
class AopTestUpperAspect implements AroundInterface
{
    public function process(ProceedingJoinPoint $joinPoint): mixed
    {
        AopTestLog::$lines[] = 'upper';
        $joinPoint->setArgument('name', \strtoupper($joinPoint->getArgument('name')));
        return $joinPoint->process();
    }
}

#[Aspect(attributes: [AopTestLogged::class])]
class AopTestAttributeAspect implements AroundInterface
{
    public function process(ProceedingJoinPoint $joinPoint): mixed
    {
        AopTestLog::$lines[] = 'attr:' . $joinPoint->className . '::' . $joinPoint->methodName;
        return $joinPoint->process();
    }
}

class AopTestShortCircuitAspect implements AroundInterface
{
    public function process(ProceedingJoinPoint $joinPoint): mixed
    {
        return 'cached';
    }
}

class AopTestService
{
    public const PREFIX = 'Hi';

    #[Inject]
    private SimpleService $privateDep;

    public function greet(string $name, string $prefix = self::PREFIX): string
    {
        return "{$prefix} {$name}";
    }

    public function sum(int $base, int ...$values): int
    {
        return $base + \array_sum($values);
    }

    public function touch(array &$bag): void
    {
        $bag[] = 'touched';
    }

    #[AopTestLogged]
    public function fluent(): static
    {
        return $this;
    }

    public function callInternal(): string
    {
        return $this->internal(2);
    }

    #[AopTestLogged]
    protected function internal(int $x): string
    {
        return "internal {$x}";
    }

    public function notIntercepted(): string
    {
        return 'plain';
    }

    public function privateDep(): ?SimpleService
    {
        return $this->privateDep ?? null;
    }

    public function self(?self $other = null): ?self
    {
        return $other;
    }
}

final class AopTestFinalService
{
    #[AopTestLogged]
    public function run(): string
    {
        return 'final';
    }
}

// ---------------------------------------------------------------------------

class AopProxyTest extends TestCase
{
    private ?string $proxyDir = null;

    protected function setUp(): void
    {
        AopTestLog::$lines = [];
        ProceedingJoinPoint::flushAspectInstances();
        Container::setAopProxyManager(AopProxyManager::create(new AspectCollector()));
        Container::getInstance()->singletonIf(SimpleService::class, SimpleService::class);
    }

    protected function tearDown(): void
    {
        Container::setAopProxyManager(null);
        Container::setProxyManager(null);
        if ($this->proxyDir !== null) {
            foreach (\glob($this->proxyDir . '/*') ?: [] as $file) {
                \unlink($file);
            }
            @\rmdir($this->proxyDir);
        }
    }

    public function test_no_aspects_returns_original_class(): void
    {
        $o = Container::make(AopTestService::class);

        $this->assertSame(AopTestService::class, $o::class);
    }

    public function test_make_returns_subclass_and_runs_aspects_in_priority_order(): void
    {
        Container::aspects()->add(AopTestTraceAspect::class)->add(AopTestUpperAspect::class);

        $o = Container::make(AopTestService::class);

        $this->assertInstanceOf(AopTestService::class, $o);
        $this->assertInstanceOf(AopProxy::class, $o);
        $this->assertSame('Hi BOB', $o->greet('bob'));
        $this->assertSame(['upper', 'trace:before:greet', 'trace:after:greet'], AopTestLog::$lines);
    }

    public function test_variadic_by_ref_and_void(): void
    {
        Container::aspects()->add(AopTestTraceAspect::class);
        $o = Container::make(AopTestService::class);

        $this->assertSame(10, $o->sum(1, 2, 3, 4));
        $this->assertSame(1, $o->sum(1));

        $bag = [];
        $this->assertNull($o->touch($bag));
        $this->assertSame(['touched'], $bag);
    }

    public function test_attribute_pointcut_static_return_and_internal_protected_call(): void
    {
        Container::aspects()->add(AopTestAttributeAspect::class);
        $o = Container::make(AopTestService::class);

        $this->assertSame($o, $o->fluent());
        $this->assertSame('internal 2', $o->callInternal());
        $this->assertSame('plain', $o->notIntercepted());
        $this->assertSame([
            'attr:' . AopTestService::class . '::fluent',
            'attr:' . AopTestService::class . '::internal',
        ], AopTestLog::$lines);
    }

    public function test_short_circuit_and_wildcard_pattern(): void
    {
        Container::aspects()->add(AopTestShortCircuitAspect::class, ['Ryra\Core\Container\Tests\AopTest*::not*']);
        $o = Container::make(AopTestService::class);

        $this->assertSame('cached', $o->notIntercepted());
        $this->assertSame('Hi x', $o->greet('x'));
    }

    public function test_final_class_is_left_untouched(): void
    {
        Container::aspects()->add(AopTestAttributeAspect::class);

        $o = Container::make(AopTestFinalService::class);

        $this->assertSame(AopTestFinalService::class, $o::class);
        $this->assertSame('final', $o->run());
    }

    public function test_string_binding_gets_proxy_and_private_inject_of_target(): void
    {
        Container::aspects()->add(AopTestTraceAspect::class);
        $container = Container::getInstance();
        $container->transient('aop.svc', AopTestService::class);

        $o = $container->get('aop.svc');

        $this->assertInstanceOf(AopProxy::class, $o);
        $this->assertSame($container->get(SimpleService::class), $o->privateDep());
    }

    public function test_adding_aspect_later_invalidates_cache(): void
    {
        $this->assertSame(AopTestService::class, Container::make(AopTestService::class)::class);

        Container::aspects()->add(AopTestTraceAspect::class);

        $this->assertInstanceOf(AopProxy::class, Container::make(AopTestService::class));
    }

    public function test_file_mode_with_factory_proxy(): void
    {
        $this->proxyDir = \sys_get_temp_dir() . '/ryra-aop-test-' . \bin2hex(\random_bytes(4));
        Container::setProxyManager(ProxyManager::create($this->proxyDir));
        $aspects = (new AspectCollector())->add(AopTestTraceAspect::class)->add(AopTestShortCircuitAspect::class, [AopTestService::class . "::callInternal"]);
        Container::setAopProxyManager(AopProxyManager::create($aspects, $this->proxyDir));

        $o = Container::make(AopTestService::class);

        $this->assertInstanceOf(AopProxy::class, $o);
        $this->assertSame('Hi a', $o->greet('a'));
        $this->assertSame(Container::getInstance()->get(SimpleService::class), $o->privateDep());
        $this->assertNull($o->self());
        $this->assertCount(1, \glob($this->proxyDir . '/A_*.php'));
        $this->assertNotEmpty(\glob($this->proxyDir . '/P_*.php'));
    }
}
