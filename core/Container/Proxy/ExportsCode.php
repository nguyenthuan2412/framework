<?php

namespace Ryra\Core\Container\Proxy;

/**
 * Helper chung cho các generator: chuyển type / default value từ Reflection sang mã PHP.
 * Chỉ dùng lúc sinh proxy, không chạy ở runtime.
 */
trait ExportsCode
{
    /**
     * Mã PHP của giá trị default, hoặc null nếu không biểu diễn được an toàn.
     */
    protected function defaultValueCode(\ReflectionParameter $param, \ReflectionClass $class): ?string
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

    protected function exportValue(mixed $value): ?string
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
     * Mã PHP của một type khai báo. self/parent được đổi sang tên class tuyệt đối
     * (vì trong class con sinh ra, self sẽ trỏ sang proxy).
     */
    protected function typeCode(?\ReflectionType $type, \ReflectionClass $scope): string
    {
        if ($type === null) {
            return '';
        }

        if ($type instanceof \ReflectionUnionType) {
            return \implode('|', \array_map(
                fn(\ReflectionType $t) => $t instanceof \ReflectionIntersectionType
                    ? '(' . $this->typeCode($t, $scope) . ')'
                    : $this->typeCode($t, $scope),
                $type->getTypes(),
            ));
        }

        if ($type instanceof \ReflectionIntersectionType) {
            return \implode('&', \array_map(fn(\ReflectionType $t) => $this->typeCode($t, $scope), $type->getTypes()));
        }

        /** @var \ReflectionNamedType $type */
        $name = $type->getName();
        $lower = \strtolower($name);
        if ($lower === 'self') {
            $name = '\\' . $scope->getName();
        } elseif ($lower === 'parent') {
            $name = '\\' . $scope->getParentClass()->getName();
        } elseif (!$type->isBuiltin() && $lower !== 'static') {
            $name = '\\' . $name;
        }

        $nullable = $type->allowsNull() && !\in_array($lower, ['mixed', 'null'], true);

        return ($nullable ? '?' : '') . $name;
    }
}
