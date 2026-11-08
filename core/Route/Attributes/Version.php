<?php

namespace Ryra\Core\Route\Attributes;

use Attribute;
use Ryra\Core\Collection\Arr;

/**
 * Khai báo version cho controller (#[Group]) hoặc cho từng action.
 *
 * Version khai báo ở method sẽ ghi đè version khai báo ở class.
 * Dùng Version::NEUTRAL để route dùng được cho mọi version.
 *
 * #[Version(1)]
 * #[Version([1, 2])]
 * #[Version(Version::NEUTRAL)]
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class Version
{
    /**
     * Route không gắn với version nào, luôn được match.
     */
    public const NEUTRAL = '__VERSION_NEUTRAL__';

    /**
     * Danh sách version, phần tử null nghĩa là version neutral.
     *
     * @var array<int, string|null>
     */
    public readonly array $versions;

    public function __construct(string|int|array $versions)
    {
        $versions = \array_map(static function (string|int $version): ?string {
            $version = \mb_trim((string) $version);

            return ($version === '' || $version === self::NEUTRAL) ? null : $version;
        }, Arr::wrap($versions));

        $this->versions = \array_values(\array_unique($versions, SORT_REGULAR));
    }

    /**
     * @return array<int, string|null>
     */
    public function getVersions(): array
    {
        return $this->versions;
    }
}
