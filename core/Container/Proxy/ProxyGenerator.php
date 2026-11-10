<?php

namespace Ryra\Core\Container\Proxy;

use Ryra\Core\Container\Inject;

/**
 * Sinh mã PHP cho proxy của một class, thay cho việc dùng Reflection ở mỗi lần
 * Container::make() / Container::injectProperties().
 *
 * Proxy sinh ra giữ đúng hành vi của đường Reflection:
 *  - Tham số constructor: container có type đó thì get(), không thì dùng default (nếu optional), còn lại null.
 *    Tham số truyền vào make() được merge đè lên theo tên (array_merge) như cũ.
 *  - Property có #[Inject]: chỉ gán khi chưa khởi tạo hoặc đang rỗng (empty()).
 */
class ProxyGenerator
{
    use ExportsCode;

    /**
     * Tăng khi đổi template để mọi proxy cũ bị sinh lại.
     */
    public const VERSION = '2';

    /**
     * Sinh mã proxy. Trả về null nếu class không hỗ trợ (dùng lại đường Reflection).
     */
    public function generate(\ReflectionClass $class, string $proxyClassName): ?string
    {
        if ($class->isAnonymous() || $class->isInternal()) {
            return null;
        }

        $make = $class->isInstantiable() ? $this->generateMake($class) : null;
        if ($class->isInstantiable() && $make === null) {
            return null;
        }

        $inject = $this->generateInject($class);
        if ($inject === null) {
            return null;
        }

        $target = '\\' . $class->getName();
        $namespace = ProxyFileLocator::NAMESPACE;
        $makeCode = $make ?? <<<PHP
            public static function make(\$c, array \$parameters = [], ?string \$class = null): object
            {
                return new (\$class ?? {$target})();
            }
        PHP;

        return <<<PHP
        <?php

        // Sinh tự động bởi Ryra\Core\Container\Proxy\ProxyGenerator, không sửa tay.
        // Nguồn: {$class->getName()}

        namespace {$namespace};

        final class {$proxyClassName}
        {
        {$makeCode}

        {$inject}
        }

        PHP;
    }

    private function generateMake(\ReflectionClass $class): ?string
    {
        $target = '\\' . $class->getName();
        $constructor = $class->getConstructor();
        $hasInject = $this->injectProperties($class) !== [];
        $injectCall = $hasInject ? "\n        self::inject(\$o, \$c);" : '';

        if ($constructor === null || $constructor->getNumberOfParameters() === 0) {
            return <<<PHP
                public static function make(\$c, array \$parameters = [], ?string \$class = null): object
                {
                    \$class ??= {$target}::class;
                    \$o = \$parameters ? new \$class(...\\array_values(\$parameters)) : new \$class();{$injectCall}
                    return \$o;
                }
            PHP;
        }

        $lines = [];
        $args = [];
        foreach ($constructor->getParameters() as $i => $param) {
            if ($param->isVariadic()) {
                return null;
            }

            $type = \var_export((string) $param->getType(), true);
            $fallback = 'null';
            if ($param->isOptional()) {
                $fallback = $this->defaultValueCode($param, $class);
                if ($fallback === null) {
                    return null;
                }
            }

            $var = '$a' . $i;
            $lines[] = "        {$var} = \$c->has({$type}) ? \$c->get({$type}) : {$fallback};";
            $args[\var_export($param->getName(), true)] = $var;
        }

        $positional = \implode(', ', $args);
        $named = \implode(', ', \array_map(fn($k, $v) => "{$k} => {$v}", \array_keys($args), $args));
        $body = \implode("\n", $lines);

        return <<<PHP
            public static function make(\$c, array \$parameters = [], ?string \$class = null): object
            {
        {$body}
                \$class ??= {$target}::class;
                \$o = \$parameters
                    ? new \$class(...\\array_values(\\array_merge([{$named}], \$parameters)))
                    : new \$class({$positional});{$injectCall}
                return \$o;
            }
        PHP;
    }

    /**
     * Sinh hàm inject(). Trả về null nếu có property không hỗ trợ (static).
     */
    private function generateInject(\ReflectionClass $class): ?string
    {
        $properties = $this->injectProperties($class);
        if ($properties === null) {
            return null;
        }

        if ($properties === []) {
            return <<<PHP
                public static function inject(object \$o, \$c): void
                {
                }
            PHP;
        }

        // Gom property theo class khai báo: property private/protected chỉ gán được trong scope của class đó.
        $byScope = [];
        foreach ($properties as [$property, $id]) {
            $byScope[$property->getDeclaringClass()->getName()][] = [$property->getName(), $id];
        }

        $binders = [];
        $calls = [];
        $i = 0;
        foreach ($byScope as $scope => $props) {
            $assign = [];
            foreach ($props as [$name, $id]) {
                $idCode = \var_export($id, true);
                $assign[] = "            if (empty(\$o->{$name})) { \$o->{$name} = \$c->get({$idCode}); }";
            }
            $assign = \implode("\n", $assign);
            $binders[] = "    private static ?\\Closure \$b{$i} = null;";
            $calls[] = <<<PHP
                    (self::\$b{$i} ??= \\Closure::bind(static function (object \$o, \$c): void {
            {$assign}
                    }, null, \\{$scope}::class))(\$o, \$c);
            PHP;
            $i++;
        }

        $binders = \implode("\n", $binders);
        $calls = \implode("\n", $calls);

        return <<<PHP
        {$binders}

            public static function inject(object \$o, \$c): void
            {
        {$calls}
            }
        PHP;
    }

    /**
     * Property có #[Inject] kèm id cần get() (theo đúng quy tắc của Container::injectProperties()).
     *
     * @return array<array{\ReflectionProperty, string}>|null null nếu gặp trường hợp không hỗ trợ.
     */
    private function injectProperties(\ReflectionClass $class): ?array
    {
        $result = [];
        foreach ($class->getProperties() as $property) {
            $attributes = $property->getAttributes(Inject::class);
            if (empty($attributes)) {
                continue;
            }
            if ($property->isStatic()) {
                return null;
            }

            $id = (string) \end($attributes)->newInstance();
            if ($id === '') {
                $id = (string) $property->getType();
            }
            $result[] = [$property, $id];
        }

        return $result;
    }
}
