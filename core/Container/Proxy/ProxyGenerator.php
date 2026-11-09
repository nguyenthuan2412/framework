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
    /**
     * Tăng khi đổi template để mọi proxy cũ bị sinh lại.
     */
    public const VERSION = '1';

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
            public static function make(\$c, array \$parameters = []): object
            {
                return new {$target}();
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
                public static function make(\$c, array \$parameters = []): object
                {
                    \$o = \$parameters ? new {$target}(...\\array_values(\$parameters)) : new {$target}();{$injectCall}
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
            public static function make(\$c, array \$parameters = []): object
            {
        {$body}
                \$o = \$parameters
                    ? new {$target}(...\\array_values(\\array_merge([{$named}], \$parameters)))
                    : new {$target}({$positional});{$injectCall}
                return \$o;
            }
        PHP;
    }

    /**
     * Mã PHP của giá trị default, hoặc null nếu không biểu diễn được an toàn.
     */
    private function defaultValueCode(\ReflectionParameter $param, \ReflectionClass $class): ?string
    {
        if (!$param->isDefaultValueAvailable()) {
            return null;
        }

        // Hằng số của class: giữ tham chiếu để proxy luôn lấy giá trị mới nhất.
        // Hằng số global: ghi thẳng giá trị (tên trả về có thể đã gắn namespace dù thực tế là global fallback).
        if ($param->isDefaultValueConstant() && \str_contains($name = $param->getDefaultValueConstantName(), '::')) {
            if (\str_starts_with($name, 'self::') || \str_starts_with($name, 'static::')) {
                $name = $class->getName() . \substr($name, \strpos($name, '::'));
            } elseif (\str_starts_with($name, 'parent::')) {
                $name = $class->getParentClass()->getName() . \substr($name, \strpos($name, '::'));
            }
            return '\\' . \ltrim($name, '\\');
        }

        return $this->exportValue($param->getDefaultValue());
    }

    private function exportValue(mixed $value): ?string
    {
        if (\is_null($value) || \is_scalar($value)) {
            return \var_export($value, true);
        }

        if ($value instanceof \UnitEnum) {
            return '\\' . $value::class . '::' . $value->name;
        }

        if (\is_array($value)) {
            $items = [];
            foreach ($value as $k => $v) {
                $code = $this->exportValue($v);
                if ($code === null) {
                    return null;
                }
                $items[] = \var_export($k, true) . ' => ' . $code;
            }
            return '[' . \implode(', ', $items) . ']';
        }

        // Object tạo bằng `new` trong default value: không sinh được an toàn.
        return null;
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
