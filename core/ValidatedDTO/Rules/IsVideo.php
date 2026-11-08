<?php

namespace Ryra\Core\ValidatedDTO\Rules;

use Attribute;
use InvalidArgumentException;
use Symfony\Component\Mime\MimeTypes;
use Ryra\Core\Http\File\File;
use Ryra\Core\ValidatedDTO\Property;
use Ryra\Core\ValidatedDTO\Rule;
use Ryra\Core\ValidatedDTO\Traits\FileTrait;

#[Attribute(Attribute::TARGET_PROPERTY)]
class IsVideo extends Rule
{
    use FileTrait;

    /**
     * Mime types accepted for each supported video extension.
     *
     * The same container can be reported with different mime types depending on
     * the magic database used, so every known alias is listed here.
     */
    protected const VIDEO_MIME_TYPES = [
        'mp4' => ['video/mp4'],
        'm4v' => ['video/x-m4v', 'video/mp4'],
        'mov' => ['video/quicktime'],
        'webm' => ['video/webm', 'video/x-matroska'],
        'mkv' => ['video/x-matroska', 'video/webm'],
        'avi' => ['video/x-msvideo', 'video/avi', 'video/msvideo'],
        'wmv' => ['video/x-ms-wmv', 'video/x-ms-asf'],
        'asf' => ['video/x-ms-asf'],
        'flv' => ['video/x-flv'],
        'f4v' => ['video/x-f4v'],
        'mpeg' => ['video/mpeg'],
        'mpg' => ['video/mpeg'],
        'ogv' => ['video/ogg'],
        '3gp' => ['video/3gpp'],
        '3g2' => ['video/3gpp2'],
        'ts' => ['video/mp2t'],
        'm2ts' => ['video/mp2t'],
        'mts' => ['video/mp2t'],
    ];

    /**
     * Extensions the value is allowed to have.
     *
     * @var string[]
     */
    protected array $allowExtensions = [];

    /**
     * Mime types the file content is allowed to be.
     *
     * @var string[]
     */
    protected array $allowMimeTypes = [];

    /**
     * @param string[] $allow Extensions (`mp4`) or mime types (`video/mp4`), empty means every supported format
     * @param bool $strict Also require the file name extension to match the allowed formats
     */
    public function __construct(
        protected array $allow = [],
        protected bool $strict = true,
        protected string $message = 'The :attribute must be a video file of type: :allow.'
    ) {
        $this->resolveAllowFormats();
    }

    public function check(mixed &$value, array &$values, Property $property): bool
    {
        $path = $this->resolvePath($value);
        if ($path === null) {
            return false;
        }

        // The client mime type can be forged, so the check is done on the content itself.
        $mimeType = $this->detectMimeType($path);
        if ($mimeType === null || !\in_array($mimeType, $this->allowMimeTypes, true)) {
            return false;
        }

        return !$this->strict || $this->hasAllowedExtension($value, $path);
    }

    /**
     * Turn the allowed formats into the extension and the mime type lists.
     *
     * @throws InvalidArgumentException When a format is not a supported video format
     */
    protected function resolveAllowFormats(): void
    {
        $allow = $this->allow ?: \array_keys(static::VIDEO_MIME_TYPES);

        foreach ($allow as $format) {
            $format = \strtolower(\trim($format));
            $extensions = \str_contains($format, '/')
                ? $this->extensionsOfMimeType($format)
                : (isset(static::VIDEO_MIME_TYPES[$extension = \ltrim($format, '.')]) ? [$extension] : []);

            if (!$extensions) {
                throw new InvalidArgumentException(\sprintf('The format "%s" is not a supported video format.', $format));
            }

            foreach ($extensions as $extension) {
                $this->allowExtensions[] = $extension;
                $this->allowMimeTypes = \array_merge($this->allowMimeTypes, static::VIDEO_MIME_TYPES[$extension]);
            }
        }

        $this->allowExtensions = \array_values(\array_unique($this->allowExtensions));
        $this->allowMimeTypes = \array_values(\array_unique($this->allowMimeTypes));
    }

    /**
     * Get every extension the given mime type can be stored as.
     *
     * @return string[]
     */
    protected function extensionsOfMimeType(string $mimeType): array
    {
        $extensions = [];
        foreach (static::VIDEO_MIME_TYPES as $extension => $mimeTypes) {
            if (\in_array($mimeType, $mimeTypes, true)) {
                $extensions[] = $extension;
            }
        }

        return $extensions;
    }

    /**
     * Get the readable path of the value, null when there is nothing to read.
     */
    protected function resolvePath(mixed $value): ?string
    {
        if ($this->isUploadedFile($value)) {
            if ($value->getError() !== \UPLOAD_ERR_OK) {
                return null;
            }

            $path = $value->getPathname();
        } elseif ($value instanceof File) {
            $path = $value->getPathname();
        } elseif (\is_string($value) && $value !== '') {
            $path = $value;
        } else {
            return null;
        }

        return \is_file($path) && \is_readable($path) ? $path : null;
    }

    /**
     * Guess the mime type from the content of the file.
     */
    protected function detectMimeType(string $path): ?string
    {
        if (\class_exists(MimeTypes::class)) {
            $mimeType = MimeTypes::getDefault()->guessMimeType($path);
        } else {
            $mimeType = @\mime_content_type($path) ?: null;
        }

        return $mimeType ? \strtolower($mimeType) : null;
    }

    /**
     * Check the name the file is stored as is not disguising another format.
     */
    protected function hasAllowedExtension(mixed $value, string $path): bool
    {
        $name = $this->isUploadedFile($value) ? $value->getClientOriginalName() : $path;
        $extension = \strtolower(\pathinfo($name, PATHINFO_EXTENSION));

        return \in_array($extension, $this->allowExtensions, true);
    }

    protected function renderAllowText(): string
    {
        return \implode(',', $this->allowExtensions);
    }
}
