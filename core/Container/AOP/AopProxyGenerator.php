<?php

namespace Ryra\Core\Container\AOP;

use Ryra\Core\Container\Proxy\ExportsCode;
use Ryra\Core\Container\Proxy\ProxyFileLocator;

/**
 * Sinh class con của service, override các method bị aspect cắt.
 *
 * Mỗi method sinh ra là code PHP thuần (không Reflection, không __call, danh sách aspect
 * là literal array), nên opcache/JIT tối ưu được như code viết tay:
 *
 *   public function find(int $id): ?\App\User
 *   {
 *       return \...\ProceedingJoinPoint::run($this, \App\UserService::class, 'find', ['id' => $id],
 *           fn(array $a) => parent::find($a['id']), ['App\LogAspect']);
 *   }
 *
 * Method không hỗ trợ (static, private, final, abstract, magic, trả về reference,
 * default value không export được) thì bỏ qua, giữ nguyên hành vi gốc.
 */
class AopProxyGenerator
{
    use ExportsCode;

    public const VERSION = '2';

    /**
     * Method nào bị cắt và bởi aspect nào. Rỗng nếu class không cần proxy.
     *
     * @return array<string, class-string<AroundInterface>[]> tên method => aspects
     */
    public function plan(\ReflectionClass $class, AspectCollector $aspects): array
    {
        if (!$this->supportsClass($class)) {
            return [];
        }

        $plan = [];
        foreach ($class->getMethods() as $method) {
            if (!$this->supportsMethod($method)) {
                continue;
            }

            $attributes = \array_map(static fn(\ReflectionAttribute $a) => $a->getName(), $method->getAttributes());
            $matched = $aspects->aspectsFor($class->getName(), $method->getName(), $attributes);
            if ($matched !== [] && $this->generateMethod($method, $class, $matched) !== null) {
                $plan[$method->getName()] = $matched;
            }
        }

        return $plan;
    }

    /**
     * @param array<string, class-string<AroundInterface>[]> $plan
     */
    public function generate(\ReflectionClass $class, string $proxyClassName, array $plan): ?string
    {
        if ($plan === []) {
            return null;
        }

        $methods = [];
        foreach ($plan as $name => $aspects) {
            $code = $this->generateMethod($class->getMethod($name), $class, $aspects);
            if ($code === null) {
                return null;
            }
            $methods[] = $code;
        }

        $target = '\\' . $class->getName();
        $namespace = ProxyFileLocator::NAMESPACE;
        $readonly = \PHP_VERSION_ID >= 80200 && $class->isReadOnly() ? 'readonly ' : '';
        $marker = '\\' . AopProxy::class;
        $methods = \implode("\n\n", $methods);

        return <<<PHP
        <?php

        // Sinh tự động bởi Ryra\Core\Container\AOP\AopProxyGenerator, không sửa tay.
        // Nguồn: {$class->getName()}

        namespace {$namespace};

        final {$readonly}class {$proxyClassName} extends {$target} implements {$marker}
        {
            public const AOP_TARGET = {$target}::class;

        {$methods}
        }

        PHP;
    }

    public function supportsClass(\ReflectionClass $class): bool
    {
        return !$class->isFinal()
            && !$class->isAbstract()
            && !$class->isInterface()
            && !$class->isTrait()
            && !$class->isEnum()
            && !$class->isAnonymous()
            && !$class->isInternal()
            && !$class->implementsInterface(AopProxy::class);
    }

    private function supportsMethod(\ReflectionMethod $method): bool
    {
        return !$method->isStatic()
            && !$method->isPrivate()
            && !$method->isFinal()
            && !$method->isAbstract()
            && !$method->isConstructor()
            && !$method->returnsReference()
            && !\str_starts_with($method->getName(), '__');
    }

    /**
     * @param class-string<AroundInterface>[] $aspects
     */
    private function generateMethod(\ReflectionMethod $method, \ReflectionClass $class, array $aspects): ?string
    {
        $scope = $method->getDeclaringClass();
        $params = [];
        $arguments = [];
        $call = [];

        foreach ($method->getParameters() as $param) {
            $name = $param->getName();
            $type = $this->typeCode($param->getType(), $scope);
            $ref = $param->isPassedByReference() ? '&' : '';
            $attributes = $this->attributesCode($param->getAttributes());
            if ($attributes === null) {
                return null;
            }
            $code = $attributes . ($type !== '' ? $type . ' ' : '') . $ref;

            if ($param->isVariadic()) {
                if ($ref !== '') {
                    return null;
                }
                $code .= '...$' . $name;
                $call[] = '...$a[' . \var_export($name, true) . ']';
            } else {
                $code .= '$' . $name;
                if ($param->isOptional()) {
                    $default = $this->defaultValueCode($param, $scope);
                    if ($default === null) {
                        return null;
                    }
                    $code .= ' = ' . $default;
                }
                $call[] = '$a[' . \var_export($name, true) . ']';
            }

            $params[] = $code;
            $arguments[] = \var_export($name, true) . ' => ' . $ref . '$' . $name;
        }

        $returnType = $this->typeCode($method->getReturnType(), $scope);
        $isVoid = \in_array(\strtolower($returnType), ['void', 'never'], true);

        // Giữ attribute của method (route, middleware, pointcut theo attribute...) để code đọc metadata
        // qua Reflection trên object proxy vẫn thấy như class gốc.
        $methodAttributes = $this->attributesCode($method->getAttributes());
        if ($methodAttributes === null) {
            return null;
        }
        $methodAttributes = $methodAttributes !== '' ? \rtrim($methodAttributes) . "\n    " : '';

        $visibility = $method->isProtected() ? 'protected' : 'public';
        $methodName = $method->getName();
        $signature = \implode(', ', $params);
        $returnCode = $returnType !== '' ? ': ' . $returnType : '';
        $classCode = '\\' . $class->getName() . '::class';
        $methodCode = \var_export($methodName, true);
        $argumentsCode = '[' . \implode(', ', $arguments) . ']';
        $callCode = \implode(', ', $call);
        $aspectsCode = '[' . \implode(', ', \array_map(static fn(string $a) => '\\' . \ltrim($a, '\\') . '::class', $aspects)) . ']';
        $prefix = $isVoid ? '' : 'return ';
        $joinPoint = '\\' . ProceedingJoinPoint::class;

        return <<<PHP
            {$methodAttributes}{$visibility} function {$methodName}({$signature}){$returnCode}
            {
                {$prefix}{$joinPoint}::run(\$this, {$classCode}, {$methodCode}, {$argumentsCode}, fn(array \$a) => parent::{$methodName}({$callCode}), {$aspectsCode});
            }
        PHP;
    }
}
