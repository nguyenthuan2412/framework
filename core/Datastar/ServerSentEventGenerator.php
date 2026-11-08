<?php

namespace Ryra\Core\Datastar;

use Ryra\Core\App;
use Ryra\Core\Datastar\Enums\ElementPatchMode;
use Ryra\Core\Datastar\Enums\EventType;
use Ryra\Core\Datastar\Stream\StreamWriter;

/**
 * Sinh event SSE đúng định dạng Datastar quy định trong SDK spec.
 *
 * Mỗi event có dạng:
 *
 *     event: <event type>\n
 *     id: <event id>\n            (chỉ khi có id)
 *     retry: <ms>\n               (chỉ khi khác 1000ms)
 *     data: <key> <value>\n       (lặp lại cho từng dòng)
 *     \n
 *
 * Các tuỳ chọn mang giá trị mặc định sẽ không được ghi ra để tiết kiệm băng thông,
 * đúng như yêu cầu của spec.
 *
 * @see https://data-star.dev/reference/sse_events
 */
class ServerSentEventGenerator
{
    protected int $retryDuration;

    protected bool $useViewTransition;

    /**
     * @param StreamWriter $writer Nơi ghi dữ liệu ra (Swoole response, buffer...)
     * @param array{retry_duration?: int, use_view_transition?: bool} $options
     * @param \Closure|null $viewRenderer Hàm render view, mặc định dùng Twig của framework
     */
    public function __construct(
        protected StreamWriter $writer,
        array $options = [],
        protected ?\Closure $viewRenderer = null,
    ) {
        $this->retryDuration = (int) ($options['retry_duration'] ?? Consts::DEFAULT_SSE_RETRY_DURATION);
        $this->useViewTransition = (bool) ($options['use_view_transition'] ?? Consts::DEFAULT_ELEMENTS_USE_VIEW_TRANSITIONS);
    }

    /**
     * Patch HTML vào DOM.
     *
     * Element truyền vào phải là HTML hoàn chỉnh (có thẻ mở và thẻ đóng). Khi
     * không chỉ định selector, Datastar dựa vào thuộc tính id của element để
     * tìm vị trí patch.
     *
     * @param string|iterable<string> $elements Một hoặc nhiều element
     */
    public function patchElements(
        string|iterable $elements,
        ?string $selector = null,
        ElementPatchMode|string|null $mode = null,
        ?bool $useViewTransition = null,
        ?string $viewTransitionSelector = null,
        ?string $namespace = null,
        ?string $eventId = null,
        ?int $retryDuration = null,
    ): static {
        $mode = ElementPatchMode::make($mode ?? Consts::DEFAULT_ELEMENT_PATCH_MODE);
        $useViewTransition ??= $this->useViewTransition;

        $dataLines = [];

        if ($selector !== null && $selector !== '') {
            $dataLines[] = Consts::SELECTOR_DATALINE_LITERAL . ' ' . $selector;
        }

        if (!$mode->isDefault()) {
            $dataLines[] = Consts::MODE_DATALINE_LITERAL . ' ' . $mode->value;
        }

        if ($namespace !== null && $namespace !== '') {
            $dataLines[] = Consts::NAMESPACE_DATALINE_LITERAL . ' ' . $namespace;
        }

        if ($useViewTransition !== Consts::DEFAULT_ELEMENTS_USE_VIEW_TRANSITIONS) {
            $dataLines[] = Consts::USE_VIEW_TRANSITION_DATALINE_LITERAL . ' ' . ($useViewTransition ? 'true' : 'false');
        }

        if ($viewTransitionSelector !== null && $viewTransitionSelector !== '') {
            $dataLines[] = Consts::VIEW_TRANSITION_SELECTOR_DATALINE_LITERAL . ' ' . $viewTransitionSelector;
        }

        foreach ($this->splitLines($elements) as $line) {
            $dataLines[] = Consts::ELEMENTS_DATALINE_LITERAL . ' ' . $line;
        }

        return $this->send(EventType::PatchElements, $dataLines, $eventId, $retryDuration);
    }

    /**
     * Render view Twig rồi patch kết quả vào DOM.
     *
     * Truyền `block` để chỉ render một block trong view, nhờ đó trang đầy đủ và
     * fragment dùng chung một file template.
     *
     * ```php
     * $sse->patchView('@guide/todos/index.twig', ['todos' => $todos], block: 'todo_list');
     * ```
     */
    public function patchView(
        string $view,
        array $context = [],
        ?string $block = null,
        ?string $selector = null,
        ElementPatchMode|string|null $mode = null,
        ?bool $useViewTransition = null,
        ?string $viewTransitionSelector = null,
        ?string $namespace = null,
        ?string $eventId = null,
        ?int $retryDuration = null,
    ): static {
        $renderer = $this->viewRenderer ?? static fn (string $view, array $context, ?string $block): string
            => App::service('datastar')->render($view, $context, $block);

        return $this->patchElements(
            elements: $renderer($view, $context, $block),
            selector: $selector,
            mode: $mode,
            useViewTransition: $useViewTransition,
            viewTransitionSelector: $viewTransitionSelector,
            namespace: $namespace,
            eventId: $eventId,
            retryDuration: $retryDuration,
        );
    }

    /**
     * Gỡ các element khớp selector khỏi DOM.
     */
    public function removeElements(
        string $selector,
        ?bool $useViewTransition = null,
        ?string $viewTransitionSelector = null,
        ?string $eventId = null,
        ?int $retryDuration = null,
    ): static {
        return $this->patchElements(
            elements: '',
            selector: $selector,
            mode: ElementPatchMode::Remove,
            useViewTransition: $useViewTransition,
            viewTransitionSelector: $viewTransitionSelector,
            eventId: $eventId,
            retryDuration: $retryDuration,
        );
    }

    /**
     * Patch signals vào store của client theo JSON Merge Patch (RFC 7386).
     *
     * Gán giá trị null cho một signal đồng nghĩa với xoá signal đó.
     *
     * @param array<string, mixed>|string $signals Mảng hoặc chuỗi JSON đã dựng sẵn
     * @param bool $onlyIfMissing Chỉ ghi khi signal chưa tồn tại ở client
     */
    public function patchSignals(
        array|string $signals,
        bool $onlyIfMissing = false,
        ?string $eventId = null,
        ?int $retryDuration = null,
    ): static {
        $json = \is_array($signals) ? $this->encodeJson($signals) : $signals;

        $dataLines = [];

        if ($onlyIfMissing !== Consts::DEFAULT_PATCH_SIGNALS_ONLY_IF_MISSING) {
            $dataLines[] = Consts::ONLY_IF_MISSING_DATALINE_LITERAL . ' ' . ($onlyIfMissing ? 'true' : 'false');
        }

        foreach ($this->splitLines($json) as $line) {
            $dataLines[] = Consts::SIGNALS_DATALINE_LITERAL . ' ' . $line;
        }

        return $this->send(EventType::PatchSignals, $dataLines, $eventId, $retryDuration);
    }

    /**
     * Xoá signal khỏi store, nhận đường dẫn dạng chấm: `user.name`.
     *
     * @param string|string[] $paths
     */
    public function removeSignals(string|array $paths, ?string $eventId = null, ?int $retryDuration = null): static
    {
        $signals = [];

        foreach ((array) $paths as $path) {
            $signals = \array_replace_recursive($signals, $this->expandPath($path));
        }

        return $this->patchSignals($signals, eventId: $eventId, retryDuration: $retryDuration);
    }

    /**
     * Chạy một đoạn JavaScript ở client.
     *
     * Datastar không có event riêng cho việc này: script được append vào body
     * dưới dạng thẻ <script>, kèm `data-effect="el.remove()"` để tự dọn sau khi chạy.
     *
     * @param array<string, string|bool> $attributes Thuộc tính thêm cho thẻ script
     */
    public function executeScript(
        string $script,
        array $attributes = [],
        bool $autoRemove = true,
        ?string $eventId = null,
        ?int $retryDuration = null,
    ): static {
        $rendered = [];

        foreach ($attributes as $name => $value) {
            if ($value === false || $value === null) {
                continue;
            }

            $rendered[] = $value === true
                ? $name
                : $name . '="' . \htmlspecialchars((string) $value, \ENT_QUOTES, 'UTF-8') . '"';
        }

        if ($autoRemove) {
            $rendered[] = Consts::DEFAULT_EXECUTE_SCRIPT_AUTO_REMOVE_ATTRIBUTE;
        }

        $openTag = '<script' . ($rendered ? ' ' . \implode(' ', $rendered) : '') . '>';

        return $this->patchElements(
            elements: $openTag . $script . '</script>',
            selector: Consts::DEFAULT_EXECUTE_SCRIPT_SELECTOR,
            mode: ElementPatchMode::Append,
            eventId: $eventId,
            retryDuration: $retryDuration,
        );
    }

    /**
     * Điều hướng trình duyệt sang url khác.
     */
    public function redirect(string $url, ?string $eventId = null): static
    {
        return $this->executeScript(
            \sprintf('setTimeout(() => window.location.href = %s)', $this->encodeJson($url)),
            eventId: $eventId,
        );
    }

    /**
     * Đổi url trên thanh địa chỉ mà không tải lại trang.
     *
     * Dùng khi điều hướng kiểu SPA: server chỉ trả về phần nội dung, url vẫn
     * phải khớp để nút back/refresh hoạt động đúng.
     */
    public function pushUrl(string $url, bool $replace = false, ?string $eventId = null): static
    {
        return $this->executeScript(
            \sprintf('history.%s({}, "", %s)', $replace ? 'replaceState' : 'pushState', $this->encodeJson($url)),
            eventId: $eventId,
        );
    }

    /**
     * Đổi tiêu đề tài liệu, thường đi kèm pushUrl khi chuyển trang.
     */
    public function title(string $title, ?string $eventId = null): static
    {
        return $this->executeScript(
            \sprintf('document.title = %s', $this->encodeJson($title)),
            eventId: $eventId,
        );
    }

    /**
     * Gửi comment SSE, dùng để giữ kết nối qua các proxy hay đóng kết nối idle.
     */
    public function comment(string $text = ''): static
    {
        $this->writer->write(': ' . \str_replace(["\r\n", "\n", "\r"], ' ', $text) . "\n\n");

        return $this;
    }

    /**
     * Kết nối tới client còn sống hay không, dùng làm điều kiện cho vòng lặp stream.
     */
    public function isConnected(): bool
    {
        return $this->writer->isWritable();
    }

    /**
     * Chủ động kết thúc luồng SSE.
     */
    public function close(): void
    {
        $this->writer->close();
    }

    public function getWriter(): StreamWriter
    {
        return $this->writer;
    }

    /**
     * Dựng và ghi một event hoàn chỉnh.
     *
     * @param string[] $dataLines
     */
    protected function send(EventType $event, array $dataLines, ?string $eventId = null, ?int $retryDuration = null): static
    {
        $retryDuration ??= $this->retryDuration;

        $output = 'event: ' . $event->value . "\n";

        if ($eventId !== null && $eventId !== '') {
            $output .= 'id: ' . $eventId . "\n";
        }

        if ($retryDuration !== Consts::DEFAULT_SSE_RETRY_DURATION) {
            $output .= 'retry: ' . $retryDuration . "\n";
        }

        foreach ($dataLines as $line) {
            $output .= 'data: ' . $line . "\n";
        }

        $this->writer->write($output . "\n");

        return $this;
    }

    /**
     * Tách nội dung thành từng dòng vì mỗi dòng phải là một dòng `data:` riêng.
     *
     * @param string|iterable<string> $content
     * @return string[]
     */
    protected function splitLines(string|iterable $content): array
    {
        if (!\is_string($content)) {
            $content = \implode("\n", \is_array($content) ? $content : \iterator_to_array($content));
        }

        $content = \trim($content);

        return $content === '' ? [] : \preg_split('/\r\n|\r|\n/', $content);
    }

    /**
     * Chuyển `user.name` thành `['user' => ['name' => null]]` để xoá signal.
     *
     * @return array<string, mixed>
     */
    protected function expandPath(string $path): array
    {
        $signals = null;

        foreach (\array_reverse(\explode('.', $path)) as $segment) {
            $signals = [$segment => $signals];
        }

        return $signals ?? [];
    }

    protected function encodeJson(mixed $value): string
    {
        return \json_encode($value, \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR);
    }
}
