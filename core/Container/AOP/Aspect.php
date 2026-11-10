<?php

namespace Ryra\Core\Container\AOP;

use Attribute;

/**
 * Khai báo một aspect và các điểm cắt (pointcut) của nó.
 *
 *   #[Aspect(classes: [UserService::class . '::find*'], attributes: [Cacheable::class], priority: 10)]
 *   class CacheAspect implements AroundInterface { ... }
 *
 * classes: pattern dạng "Class", "Class::method", hỗ trợ wildcard "*" ở cả hai phần
 *          (vd: "App\Service\*", "App\Service\UserService::get*"). Không phân biệt hoa thường.
 * attributes: method nào gắn một trong các attribute này thì bị cắt.
 * priority: lớn hơn chạy trước (bọc ngoài).
 */
#[Attribute(Attribute::TARGET_CLASS)]
class Aspect
{
    /**
     * @param string[] $classes
     * @param class-string[] $attributes
     */
    public function __construct(
        public array $classes = [],
        public array $attributes = [],
        public int $priority = 0,
    ) {}
}
