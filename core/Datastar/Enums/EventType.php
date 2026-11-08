<?php

namespace Ryra\Core\Datastar\Enums;

/**
 * Hai loại event mà client Datastar hiểu được.
 */
enum EventType: string
{
    /** Patch HTML vào DOM */
    case PatchElements = 'datastar-patch-elements';

    /** Patch dữ liệu vào signal store theo chuẩn JSON Merge Patch (RFC 7386) */
    case PatchSignals = 'datastar-patch-signals';
}
