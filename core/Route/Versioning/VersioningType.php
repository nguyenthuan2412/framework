<?php

namespace Ryra\Core\Route\Versioning;

enum VersioningType: string
{
    /**
     * Tắt versioning, mọi route được đăng ký như bình thường.
     */
    case None = 'none';

    /**
     * Version nằm trong url: /api/v1/tours
     */
    case Uri = 'uri';

    /**
     * Version nằm trong header: X-API-Version: 1
     */
    case Header = 'header';

    /**
     * Version nằm trong Accept header: Accept: application/json;v=1
     */
    case MediaType = 'media_type';

    /**
     * Version được lấy từ callable cấu hình trong config/routing.php
     */
    case Custom = 'custom';

    public static function from_(string|self|null $value): self
    {
        if ($value instanceof self) {
            return $value;
        }

        return self::tryFrom(\mb_strtolower((string) $value)) ?? self::None;
    }
}
