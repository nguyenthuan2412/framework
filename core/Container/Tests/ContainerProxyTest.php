<?php

namespace Ryra\Core\Container\Tests;

use Ryra\Core\Container\Container;
use Ryra\Core\Container\Inject;
use Ryra\Core\Container\Proxy\ProxyManager;

// ---------------------------------------------------------------------------
// Stubs cho các trường hợp riêng của proxy
// ---------------------------------------------------------------------------

enum ProxyTestMode: string
{
    case Fast = 'fast';
    case Safe = 'safe';
}

class ProxyTestDefaults
{
    public const LIMIT = 10;

    public function __construct(
        public int $limit = self::LIMIT,
        public ProxyTestMode $mode = ProxyTestMode::Safe,
        public array $tags = ['a', 'b' => [1, 2]],
        public ?SimpleService $simple = null,
        public string $eol = PHP_EOL,
    ) {}
}

class ProxyTestBase
{
    #[Inject]
    private SimpleService $parentPrivate;

    public function parentPrivate(): ?SimpleService
    {
        return $this->parentPrivate ?? null;
    }
}

class ProxyTestChild extends ProxyTestBase
{
    #[Inject]
    protected SimpleService $protectedProp;

    #[Inject('svc_simple')]
    private mixed $privateById = null;

    #[Inject]
    public SimpleService $alreadySet;

    public function __construct()
    {
        $this->alreadySet = new SimpleService();
        $this->alreadySet->name = 'kept';
    }

    public function protectedProp(): ?SimpleService
    {
        return $this->protectedProp ?? null;
    }

    public function privateById(): mixed
    {
        return $this->privateById;
    }
}

class ProxyTestVariadic
{
    public array $items;

    public function __construct(string ...$items)
    {
        $this->items = $items;
    }
}

// ---------------------------------------------------------------------------
// Chạy lại toàn bộ ContainerTest qua đường proxy + các test riêng
// ---------------------------------------------------------------------------

class ContainerProxyTest extends ContainerTest
{
    private string $proxyDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->proxyDir = \sys_get_temp_dir() . '/ryra-proxy-test-' . \bin2hex(\random_bytes(4));
        Container::setProxyManager(ProxyManager::create($this->proxyDir));
    }

    protected function tearDown(): void
    {
        Container::setProxyManager(null);
        foreach (\glob($this->proxyDir . '/*') ?: [] as $file) {
            \unlink($file);
        }
        @\rmdir($this->proxyDir);
    }

    public function test_make_generates_proxy_file(): void
    {
        [$class, $file, $dir] = $this->defineTempClass('public function __construct(public int $v = 1) {}');

        $o = Container::make($class);

        $this->assertSame(1, $o->v);
        $this->assertCount(1, \glob($this->proxyDir . '/P_*.php'));
        $this->assertNotNull(Container::getProxyManager()->proxyFor($class));
        \unlink($file);
        \rmdir($dir);
    }

    /**
     * Tạo một class mới trong file tạm (mỗi test 1 class riêng, không bị proxy nạp từ test khác).
     *
     * @return array{string, string, string}
     */
    private function defineTempClass(string $body): array
    {
        $dir = \sys_get_temp_dir() . '/ryra-proxy-src-' . \bin2hex(\random_bytes(4));
        \mkdir($dir);
        $class = 'ProxyTestGenerated' . \bin2hex(\random_bytes(4));
        $file = "$dir/$class.php";
        \file_put_contents($file, "<?php class $class { $body }");
        require $file;

        return [$class, $file, $dir];
    }

    public function test_proxy_keeps_default_values(): void
    {
        $o = Container::make(ProxyTestDefaults::class);
        $this->assertSame(10, $o->limit);
        $this->assertSame(ProxyTestMode::Safe, $o->mode);
        $this->assertSame(['a', 'b' => [1, 2]], $o->tags);
        $this->assertNull($o->simple);
        $this->assertSame(PHP_EOL, $o->eol);
    }

    public function test_proxy_keeps_nullable_type_behavior(): void
    {
        // Giữ đúng hành vi cũ: type "?X" không khớp binding "X" nên dùng default.
        Container::getInstance()->singleton(SimpleService::class, SimpleService::class);
        $o = Container::make(ProxyTestDefaults::class);
        $this->assertNull($o->simple);
    }

    public function test_proxy_named_parameters_override(): void
    {
        $o = Container::make(ProxyTestDefaults::class, ['mode' => ProxyTestMode::Fast, 'limit' => 3]);
        $this->assertSame(3, $o->limit);
        $this->assertSame(ProxyTestMode::Fast, $o->mode);
    }

    public function test_proxy_injects_private_protected_and_parent_private(): void
    {
        Container::getInstance()->singleton(SimpleService::class, SimpleService::class);
        Container::getInstance()->singleton('svc_simple', AnotherService::class);

        $o = Container::make(ProxyTestChild::class);

        // Giữ đúng hành vi cũ: property private của class cha không được inject.
        $this->assertNull($o->parentPrivate());
        $this->assertSame(Container::getInstance()->get(SimpleService::class), $o->protectedProp());
        $this->assertInstanceOf(AnotherService::class, $o->privateById());
        // Property đã có giá trị thì không bị ghi đè (giống đường Reflection).
        $this->assertSame('kept', $o->alreadySet->name);
    }

    public function test_proxy_and_reflection_give_same_result(): void
    {
        Container::getInstance()->singleton(SimpleService::class, SimpleService::class);
        Container::getInstance()->singleton('svc_simple', AnotherService::class);
        $viaProxy = Container::make(ProxyTestChild::class);

        Container::setProxyManager(null);
        $viaReflection = Container::make(ProxyTestChild::class);

        $this->assertSame($viaReflection->parentPrivate(), $viaProxy->parentPrivate());
        $this->assertSame($viaReflection->protectedProp(), $viaProxy->protectedProp());
        $this->assertSame($viaReflection->privateById(), $viaProxy->privateById());
    }

    public function test_injectProperties_on_service_created_by_container(): void
    {
        Container::getInstance()->singleton(SimpleService::class, SimpleService::class);
        Container::getInstance()->transient('svc_with_inject', fn() => new ServiceWithInjectByType());

        $o = Container::getInstance()->get('svc_with_inject');
        $this->assertSame(Container::getInstance()->get(SimpleService::class), $o->simple);
    }

    public function test_variadic_constructor_is_not_proxied(): void
    {
        // Đường Reflection hiện tại cũng không hỗ trợ variadic (getDefaultValue() ném lỗi),
        // proxy chỉ cần không sinh cho class này.
        $this->assertNull(Container::getProxyManager()->proxyFor(ProxyTestVariadic::class));
    }

    public function test_anonymous_class_falls_back_to_reflection(): void
    {
        Container::getInstance()->singleton(SimpleService::class, SimpleService::class);
        $anon = new class {
            #[Inject]
            public SimpleService $simple;
        };

        $o = Container::make($anon::class);
        $this->assertSame(Container::getInstance()->get(SimpleService::class), $o->simple);
        $this->assertNull(Container::getProxyManager()->proxyFor($anon::class));
    }

    public function test_proxy_regenerated_when_source_file_changes(): void
    {
        [$class, $file, $dir] = $this->defineTempClass('public function __construct(public int $v = 1) {}');

        $manager = Container::getProxyManager();
        $first = $manager->proxyFor($class);
        $this->assertNotNull($first);

        // Giả lập sửa file: đổi mtime, xoá cache RAM (tương đương worker reload).
        \touch($file, \time() + 10);
        \clearstatcache();
        $manager->flush();
        $second = $manager->proxyFor($class);

        $this->assertNotSame($first, $second);
        $this->assertCount(1, \glob($this->proxyDir . '/P_*.php'), 'Proxy cũ phải bị xoá');

        \unlink($file);
        \rmdir($dir);
    }
}
