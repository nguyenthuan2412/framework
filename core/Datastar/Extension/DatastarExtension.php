<?php

namespace Ryra\Core\Datastar\Extension;

use Ryra\Core\App;
use Ryra\Core\Datastar\Consts;
use Ryra\Core\Datastar\DatastarManager;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

/**
 * Cầu nối giữa Twig và Datastar.
 *
 * Thay vì viết tay `data-*` trong template (dễ sai quote khi nhúng JSON), các
 * hàm ở đây sinh attribute đã escape đúng và tự chuyển tên key sang dạng
 * kebab-case của Datastar.
 *
 * ```twig
 * {{ datastar_script() }}
 *
 * <div {{ ds_signals({ count: 0 }) }}>
 *     <span {{ ds({ text: '$count' }) }}></span>
 *     <button {{ ds({ on_click: ds_post(route('todo.increase')), indicator: 'loading' }) }}>Tăng</button>
 * </div>
 * ```
 */
class DatastarExtension extends AbstractExtension
{
    /**
     * Manager để null thì lấy từ container khi cần, nhờ vậy các hàm sinh
     * attribute dùng được cả ngoài phạm vi request.
     */
    public function __construct(protected ?DatastarManager $datastar = null) {}

    public function getFunctions(): array
    {
        $htmlSafe = ['is_safe' => ['html']];

        return [
            new TwigFunction('datastar_script', [$this, 'scriptTag'], $htmlSafe),
            new TwigFunction('datastar_script_url', [$this, 'scriptUrl']),
            new TwigFunction('ds', [$this, 'attributes'], $htmlSafe),
            new TwigFunction('ds_signals', [$this, 'signalsAttribute'], $htmlSafe),
            new TwigFunction('ds_signal', [$this, 'signal']),
            new TwigFunction('ds_fragments', [$this, 'fragmentsOption']),
            new TwigFunction('ds_get', [$this, 'actionGet']),
            new TwigFunction('ds_post', [$this, 'actionPost']),
            new TwigFunction('ds_put', [$this, 'actionPut']),
            new TwigFunction('ds_patch', [$this, 'actionPatch']),
            new TwigFunction('ds_delete', [$this, 'actionDelete']),
        ];
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('ds_json', [$this, 'json'], ['is_safe' => ['html']]),
            new TwigFilter('ds', [$this, 'attributes'], ['is_safe' => ['html']]),
        ];
    }

    /**
     * Thẻ <script type="module"> nạp datastar.js.
     *
     * @param array<string, string|bool> $attributes
     */
    public function scriptTag(array $attributes = []): string
    {
        return $this->manager()->scriptTag($attributes);
    }

    public function scriptUrl(): string
    {
        return $this->manager()->scriptUrl();
    }

    /**
     * Sinh danh sách attribute `data-*` từ một mảng.
     *
     * - `on_click` → `data-on-click`, `on_click__debounce.500ms` giữ nguyên phần modifier
     * - giá trị `true` cho ra attribute không có value, `false`/`null` thì bỏ qua
     * - giá trị mảng được encode thành JSON
     *
     * @param array<string, mixed> $attributes
     */
    public function attributes(array $attributes): string
    {
        $rendered = [];

        foreach ($attributes as $key => $value) {
            if ($value === false || $value === null) {
                continue;
            }

            $name = $this->normalizeName((string) $key);

            if ($value === true) {
                $rendered[] = $name;
                continue;
            }

            $rendered[] = $name . '="' . $this->escape($value) . '"';
        }

        return \implode(' ', $rendered);
    }

    /**
     * Attribute `data-signals` khai báo state khởi tạo cho vùng DOM.
     *
     * @param array<string, mixed>|string $signals
     */
    public function signalsAttribute(array|string $signals, bool $ifMissing = false): string
    {
        $name = $ifMissing ? 'data-signals__ifmissing' : 'data-signals';

        return $name . '="' . $this->escape($signals) . '"';
    }

    /**
     * Tên signal dùng trong biểu thức Datastar: `ds_signal('user.name')` → `$user.name`.
     */
    public function signal(string $name): string
    {
        return '$' . \ltrim($name, '$');
    }

    /**
     * @param array<string, mixed> $options Tuỳ chọn của action, ví dụ `{ contentType: 'form' }`
     * @param string[] $fragments Các mảnh giao diện sẽ thay đổi, server chỉ render đúng những mảnh này
     */
    public function actionGet(string $url, array $options = [], array $fragments = []): string
    {
        return $this->action('get', $url, $options, $fragments);
    }

    public function actionPost(string $url, array $options = [], array $fragments = []): string
    {
        return $this->action('post', $url, $options, $fragments);
    }

    public function actionPut(string $url, array $options = [], array $fragments = []): string
    {
        return $this->action('put', $url, $options, $fragments);
    }

    public function actionPatch(string $url, array $options = [], array $fragments = []): string
    {
        return $this->action('patch', $url, $options, $fragments);
    }

    public function actionDelete(string $url, array $options = [], array $fragments = []): string
    {
        return $this->action('delete', $url, $options, $fragments);
    }

    /**
     * Options khai báo fragment, dùng khi muốn tự ghép với option khác.
     *
     * @param string[] $fragments
     * @return array<string, mixed>
     */
    public function fragmentsOption(array $fragments): array
    {
        return $fragments === []
            ? []
            : ['headers' => [Consts::FRAGMENTS_HEADER => \implode(',', $fragments)]];
    }

    /**
     * Encode giá trị thành JSON để nhúng vào biểu thức Datastar.
     */
    public function json(mixed $value): string
    {
        return \json_encode($value, \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR);
    }

    /**
     * Dựng lời gọi action `@post('/url', {...})`.
     *
     * @param array<string, mixed> $options
     * @param string[] $fragments
     */
    protected function action(string $method, string $url, array $options = [], array $fragments = []): string
    {
        $arguments = "'" . \str_replace("'", "\\'", $url) . "'";

        if ($fragments !== []) {
            $fragmentOption = $this->fragmentsOption($fragments);
            $options['headers'] = \array_merge($options['headers'] ?? [], $fragmentOption['headers']);
        }

        if ($options !== []) {
            $arguments .= ', ' . $this->json($options);
        }

        return "@{$method}({$arguments})";
    }

    /**
     * `on_click` → `data-on-click`, giữ nguyên phần modifier sau `__`.
     */
    protected function normalizeName(string $key): string
    {
        [$name, $modifiers] = \array_pad(\explode('__', $key, 2), 2, null);

        $name = \strtolower(\preg_replace('/(?<!^)[A-Z]/', '-$0', $name));
        $name = \str_replace('_', '-', $name);

        if (!\str_starts_with($name, 'data-')) {
            $name = 'data-' . $name;
        }

        return $modifiers === null ? $name : $name . '__' . $modifiers;
    }

    protected function manager(): DatastarManager
    {
        return $this->datastar ??= App::service('datastar');
    }

    protected function escape(mixed $value): string
    {
        $value = \is_array($value) || \is_object($value) ? $this->json($value) : (string) $value;

        return \htmlspecialchars($value, \ENT_QUOTES, 'UTF-8');
    }
}
