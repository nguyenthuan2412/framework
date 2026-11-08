<?php

namespace Ryra\Core\AI\Template;

use Ryra\Core\AI\Exceptions\AIException;
use Ryra\Core\Support\Arrayable;
use Stringable;

/**
 * Mẫu prompt có biến, thay cho việc nối chuỗi rải rác trong code nghiệp vụ.
 *
 *   $template = PromptTemplate::make('Tóm tắt tour {ten_tour} trong {so_cau} câu.');
 *   $template->render(['ten_tour' => 'Hạ Long 3N2Đ', 'so_cau' => 3]);
 *
 * Biến thiếu sẽ báo lỗi ngay thay vì lặng lẽ gửi "{ten_tour}" lên model.
 */
class PromptTemplate implements Stringable
{
    /**
     * @param array<string, mixed> $defaults Giá trị mặc định cho biến.
     */
    public function __construct(
        protected string $template,
        protected array $defaults = [],
    ) {}

    /**
     * @param array<string, mixed> $defaults
     */
    public static function make(string $template, array $defaults = []): static
    {
        return new static($template, $defaults);
    }

    /**
     * Nạp mẫu từ file, tiện khi prompt dài và cần review qua git.
     */
    public static function fromFile(string $path, array $defaults = []): static
    {
        if (!is_file($path)) {
            throw new AIException(sprintf('Prompt template file not found: "%s".', $path));
        }

        return new static((string) file_get_contents($path), $defaults);
    }

    /**
     * @param array<string, mixed> $variables
     */
    public function with(array $variables): static
    {
        return new static($this->template, array_merge($this->defaults, $variables));
    }

    /**
     * @param array<string, mixed> $variables
     */
    public function render(array $variables = []): string
    {
        $variables = array_merge($this->defaults, $variables);
        $missing = array_diff($this->variables(), array_keys($variables));

        if ($missing !== []) {
            throw new AIException(sprintf(
                'Missing prompt variables: %s. Template: "%s".',
                implode(', ', $missing),
                mb_strimwidth($this->template, 0, 80, '...'),
            ));
        }

        $replacements = [];

        foreach ($variables as $key => $value) {
            $replacements['{' . $key . '}'] = $this->stringify($value);
        }

        return strtr($this->template, $replacements);
    }

    /**
     * Danh sách biến có trong mẫu.
     *
     * @return array<int, string>
     */
    public function variables(): array
    {
        preg_match_all('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/', $this->template, $matches);

        return array_values(array_unique($matches[1]));
    }

    public function raw(): string
    {
        return $this->template;
    }

    protected function stringify(mixed $value): string
    {
        if ($value instanceof Arrayable) {
            $value = $value->toArray();
        }

        if (is_array($value)) {
            // Mảng tuần tự trình bày dạng gạch đầu dòng cho model dễ đọc.
            if (array_is_list($value)) {
                return implode(PHP_EOL, array_map(
                    fn (mixed $item): string => '- ' . $this->stringify($item),
                    $value,
                ));
            }

            return (string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if ($value === null) {
            return '';
        }

        return (string) $value;
    }

    public function __toString(): string
    {
        return $this->render();
    }
}
