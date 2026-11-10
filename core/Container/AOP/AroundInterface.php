<?php

namespace Ryra\Core\Container\AOP;

interface AroundInterface
{
    /**
     * Gọi $joinPoint->process() để chạy aspect kế tiếp / method gốc.
     * Không gọi thì method gốc sẽ không chạy (vd: trả kết quả từ cache).
     */
    public function process(ProceedingJoinPoint $joinPoint): mixed;
}
