<?php

namespace Ryra\Core\Route\Versioning;

use Ryra\Core\App;
use Ryra\Core\Collection\Arr;
use Ryra\Core\Http\AcceptHeader;
use Ryra\Core\Http\Request;

/**
 * Đọc cấu hình `config/routing.php` để biết cách lấy version của request
 * và cách gắn version vào url khi type = uri.
 */
class VersionResolver
{
    protected VersioningType $type;

    public function __construct(protected array $config = [])
    {
        $this->type = VersioningType::from_($config['type'] ?? null);
    }

    public function getType(): VersioningType
    {
        return $this->type;
    }

    public function isEnabled(): bool
    {
        return $this->type !== VersioningType::None;
    }

    public function isUri(): bool
    {
        return $this->type === VersioningType::Uri;
    }

    /**
     * Version mặc định cho route không khai báo #[Version].
     * Trả về [null] nghĩa là các route đó là version neutral.
     *
     * @return array<int, string|null>
     */
    public function getDefaultVersions(): array
    {
        if (!$this->isEnabled()) {
            return [null];
        }

        $default = $this->config['default'] ?? null;
        if (\is_string($default)) {
            $default = \explode(',', $default);
        }

        $versions = \array_values(\array_filter(
            \array_map(static fn ($version) => \mb_trim((string) $version), Arr::wrap($default ?? [])),
            static fn (string $version) => $version !== ''
        ));

        return empty($versions) ? [null] : $versions;
    }

    public function getDefaultVersion(): ?string
    {
        return $this->getDefaultVersions()[0];
    }

    /**
     * Danh sách version ứng viên của request, đã sắp theo độ ưu tiên.
     * Trả về null nghĩa là không match theo version (type none hoặc uri).
     *
     * @return array<int, string>|null
     */
    public function resolve(Request $request): ?array
    {
        if (!$this->isEnabled() || $this->isUri()) {
            return null;
        }

        $versions = match ($this->type) {
            VersioningType::Header => $this->fromHeader($request),
            VersioningType::MediaType => $this->fromMediaType($request),
            VersioningType::Custom => $this->fromExtractor($request),
            default => null,
        };

        $versions = \array_values(\array_filter(
            \array_map(static fn ($version) => \mb_trim((string) $version), Arr::wrap($versions ?? [])),
            static fn (string $version) => $version !== ''
        ));

        if (empty($versions)) {
            $versions = \array_values(\array_filter(
                $this->getDefaultVersions(),
                static fn (?string $version) => $version !== null
            ));
        }

        return $versions;
    }

    /**
     * Segment version dùng trong url: 1 => "v1".
     */
    public function getUriSegment(?string $version): string
    {
        if ($version === null || $version === '') {
            return '';
        }

        $prefix = (string) ($this->config['prefix'] ?? 'v');
        if ($prefix !== '' && \str_starts_with($version, $prefix)) {
            return $version;
        }

        return $prefix . $version;
    }

    /**
     * Chèn version vào prefix của controller.
     *
     * - Prefix có `{version}` thì thay tại đúng vị trí đó.
     * - Prefix bắt đầu bằng `base` (mặc định "api") thì chèn ngay sau: api/v1/tours.
     * - Còn lại thì đặt lên đầu: v1/tours.
     */
    public function applyUriVersion(string $prefix, ?string $version): string
    {
        $segment = $this->isUri() ? $this->getUriSegment($version) : '';

        if (\str_contains($prefix, '{version}')) {
            return $this->normalizePath(\str_replace('{version}', $segment, $prefix));
        }

        if ($segment === '') {
            return $this->normalizePath($prefix);
        }

        $path = \trim($prefix, '/');
        $base = \trim((string) ($this->config['base'] ?? ''), '/');

        if ($base !== '' && ($path === $base || \str_starts_with($path, "$base/"))) {
            $rest = \ltrim(\mb_substr($path, \mb_strlen($base)), '/');
            $path = $rest !== '' ? "$base/$segment/$rest" : "$base/$segment";
        } else {
            $path = $path !== '' ? "$segment/$path" : $segment;
        }

        return $this->normalizePath($path);
    }

    protected function fromHeader(Request $request): ?array
    {
        $header = (string) ($this->config['header'] ?? 'X-API-Version');
        $value = $request->header($header);

        return $value === null ? null : \explode(',', (string) $value);
    }

    protected function fromMediaType(Request $request): ?string
    {
        $accept = $request->header('Accept');
        if (empty($accept)) {
            return null;
        }

        $key = (string) ($this->config['key'] ?? 'v');
        foreach (AcceptHeader::fromString($accept)->all() as $item) {
            if ($item->hasAttribute($key)) {
                return (string) $item->getAttribute($key);
            }
        }

        return null;
    }

    protected function fromExtractor(Request $request): string|array|null
    {
        $extractor = $this->config['extractor'] ?? null;
        if (empty($extractor)) {
            return null;
        }

        if (\is_string($extractor) && \class_exists($extractor)) {
            $extractor = App::make($extractor);
        }

        return \is_callable($extractor) ? $extractor($request) : null;
    }

    protected function normalizePath(string $path): string
    {
        $path = \preg_replace('#/+#', '/', $path);
        $path = \trim((string) $path, '/');

        return $path === '' ? '' : "/$path";
    }
}
