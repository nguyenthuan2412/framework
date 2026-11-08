<?php

namespace Ryra\Core\Datastar;

use Ryra\Core\App;
use Ryra\Core\Datastar\Stream\BufferStreamWriter;
use Ryra\Core\Http\Request;
use Ryra\Core\Http\Response;
use Ryra\Core\Twig\TwigEnvironment;

/**
 * Điểm vào của component Datastar: mở luồng SSE, đọc signals từ request và
 * render view Twig thành fragment để patch.
 *
 * Service `datastar` được bind dạng scoped nên mỗi request có một manager riêng,
 * signals đã đọc được cache lại trong phạm vi request đó.
 */
class DatastarManager
{
    private ?Signals $signals = null;

    /**
     * @param array<string, mixed> $config Nội dung config/datastar.php
     */
    public function __construct(protected App $app, protected array $config = []) {}

    /**
     * Mở luồng SSE. Callback nhận `ServerSentEventGenerator` để patch element/signal.
     *
     * ```php
     * return Datastar::stream(function (ServerSentEventGenerator $sse) {
     *     $sse->patchSignals(['saving' => true]);
     *     $sse->patchView('@guide/todos/index.twig', ['todos' => $todos], block: 'list');
     * });
     * ```
     *
     * @param callable(ServerSentEventGenerator, SseResponse): void $handler
     * @param array<string, string> $headers Header ghi đè cho response
     * @param array{retry_duration?: int, use_view_transition?: bool, keep_alive?: int} $options
     */
    public function stream(callable $handler, int $status = 200, array $headers = [], array $options = []): SseResponse
    {
        return new SseResponse(
            \Closure::fromCallable($handler),
            $status,
            \array_merge($this->config['sse']['headers'] ?? [], $headers),
            \array_merge($this->sseOptions(), $options),
            // Generator render view qua chính manager này, không phải tra lại container
            fn (string $view, array $context, ?string $block): string => $this->render($view, $context, $block),
        );
    }

    /**
     * Rút gọn cho trường hợp chỉ patch một lần rồi đóng luồng.
     *
     * @param string|iterable<string> $elements
     */
    public function patchElements(string|iterable $elements, mixed ...$arguments): SseResponse
    {
        return $this->stream(static fn (ServerSentEventGenerator $sse) => $sse->patchElements($elements, ...$arguments));
    }

    /**
     * Rút gọn cho trường hợp chỉ patch signals một lần rồi đóng luồng.
     *
     * @param array<string, mixed>|string $signals
     */
    public function patchSignals(array|string $signals, mixed ...$arguments): SseResponse
    {
        return $this->stream(static fn (ServerSentEventGenerator $sse) => $sse->patchSignals($signals, ...$arguments));
    }

    /**
     * Render view rồi patch luôn, dùng nhiều nhất khi trả kết quả cho một action.
     */
    public function patchView(string $view, array $context = [], ?string $block = null, mixed ...$arguments): SseResponse
    {
        return $this->stream(
            fn (ServerSentEventGenerator $sse) => $sse->patchView($view, $context, $block, ...$arguments)
        );
    }

    /**
     * Trả về trang đầy đủ hay chỉ những mảnh mà client báo là sẽ thay đổi.
     *
     * - Vào trang lần đầu (gõ url, F5, bot): render cả layout kèm header/footer.
     * - Điều hướng bằng Datastar: client gửi kèm header `Datastar-Fragments`
     *   liệt kê các block cần cập nhật, server chỉ render đúng những block đó.
     * - Client không liệt kê gì thì render fragment đầu tiên trong khai báo.
     *
     * ```php
     * return Datastar::page('todos/index.twig',
     *     context: ['title' => 'Việc cần làm'],
     *     fragments: [
     *         'content'    => null,                                  // cả vùng nội dung
     *         'todo_list'  => ['selector' => '#todo-list', 'mode' => 'inner',
     *                          'context' => fn () => ['todos' => Todo::query()->get()]],
     *         'pagination' => '#pagination',
     *     ],
     *     url: route('todo.index'),
     * );
     * ```
     *
     * @param string|array<int|string, mixed> $fragments Khai báo các mảnh có thể render riêng
     * @param string|null $url Url đẩy lên thanh địa chỉ, null thì giữ nguyên
     */
    public function page(
        string $view,
        array $context = [],
        string|array $fragments = 'content',
        ?string $url = null,
        ?string $title = null,
    ): Response {
        if (!$this->isDatastarRequest()) {
            return Response::view($view, $this->resolveContext($context));
        }

        $declared = Fragment::collect($fragments);
        $requested = $this->resolveRequestedFragments($declared);

        return $this->stream(function (ServerSentEventGenerator $sse) use ($view, $context, $requested, $url, $title) {
            $shared = $this->resolveContext($context);

            foreach ($requested as $fragment) {
                $sse->patchView(
                    view: $view,
                    context: $fragment->context($shared),
                    block: $fragment->name,
                    selector: $fragment->selector,
                    mode: $fragment->mode,
                );
            }

            if ($title !== null) {
                $sse->title($title);
            }

            if ($url !== null) {
                $sse->pushUrl($url);
            }
        });
    }

    /**
     * Danh sách fragment client yêu cầu, lấy từ header `Datastar-Fragments`
     * hoặc query param `_fragments`.
     *
     * @return string[]
     */
    public function requestedFragments(?Request $request = null): array
    {
        $request ??= $this->request();

        $raw = $request->header($this->config['fragments']['header'] ?? Consts::FRAGMENTS_HEADER);

        if ($raw === null || $raw === '') {
            $raw = $request->query->get($this->config['fragments']['param'] ?? Consts::FRAGMENTS_PARAM);
        }

        return Fragment::parseRequested(\is_string($raw) ? $raw : null);
    }

    /**
     * Giao giữa danh sách client yêu cầu và danh sách server khai báo.
     *
     * Chỉ render những gì đã khai báo sẵn: client không thể ép server render
     * một block bất kỳ, và tên gõ sai thì bị bỏ qua thay vì ném lỗi giữa stream.
     *
     * @param array<string, Fragment> $declared
     * @return Fragment[]
     */
    protected function resolveRequestedFragments(array $declared): array
    {
        if ($declared === []) {
            return [];
        }

        $requested = [];

        foreach ($this->requestedFragments() as $name) {
            if (isset($declared[$name])) {
                $requested[$name] = $declared[$name];
            }
        }

        // Client không yêu cầu gì thì trả về mảnh đầu tiên (thường là 'content')
        return $requested === [] ? [\reset($declared)] : \array_values($requested);
    }

    /**
     * Cho phép truyền closure vào context để dữ liệu nặng chỉ được tính khi
     * thực sự có mảnh cần đến nó.
     *
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    protected function resolveContext(array $context): array
    {
        foreach ($context as $key => $value) {
            if ($value instanceof \Closure) {
                $context[$key] = $value();
            }
        }

        return $context;
    }

    /**
     * Điều hướng client sang url khác qua SSE.
     */
    public function redirect(string $url): SseResponse
    {
        return $this->stream(static fn (ServerSentEventGenerator $sse) => $sse->redirect($url));
    }

    /**
     * Tạo generator ghi ra bộ nhớ, dùng khi cần chuỗi SSE mà không gắn với response.
     */
    public function buffer(): ServerSentEventGenerator
    {
        return new ServerSentEventGenerator(new BufferStreamWriter(), $this->sseOptions());
    }

    /**
     * Signals client gửi lên trong request hiện tại.
     *
     * Gọi không tham số để lấy cả object, truyền key để lấy nhanh một giá trị.
     */
    public function signals(?string $key = null, mixed $default = null): mixed
    {
        $this->signals ??= Signals::fromRequest($this->request(), $this->config['signals']['param'] ?? Consts::DATASTAR_KEY);

        return $key === null ? $this->signals : $this->signals->get($key, $default);
    }

    /**
     * Request hiện tại có phải do Datastar phát ra hay không.
     */
    public function isDatastarRequest(?Request $request = null): bool
    {
        $request ??= $this->request();

        if ($request->hasHeader(Consts::DATASTAR_REQUEST_HEADER)) {
            return true;
        }

        return \str_contains($request->header('accept') ?? '', 'text/event-stream');
    }

    /**
     * Render view Twig thành HTML để patch.
     *
     * Truyền `$block` để chỉ render một block trong view, nhờ đó trang đầy đủ và
     * fragment dùng chung một template.
     */
    public function render(string $view, array $context = [], ?string $block = null): string
    {
        $twig = $this->app->get('twig.environment');
        \assert($twig instanceof TwigEnvironment);

        $template = $twig->load($this->resolveView($view));

        return $block === null
            ? $template->render($context)
            : $template->renderBlock($block, $context);
    }

    /**
     * Thẻ script nạp Datastar ở client.
     *
     * @param array<string, string|bool> $attributes
     */
    public function scriptTag(array $attributes = []): string
    {
        $attributes = \array_merge(
            ['type' => 'module', 'src' => $this->scriptUrl()],
            \array_filter(['nonce' => $this->config['script']['nonce'] ?? null], static fn ($value) => $value !== null),
            (array) ($this->config['script']['attributes'] ?? []),
            $attributes,
        );

        $rendered = [];

        foreach ($attributes as $name => $value) {
            if ($value === false || $value === null) {
                continue;
            }

            $rendered[] = $value === true
                ? $name
                : $name . '="' . \htmlspecialchars((string) $value, \ENT_QUOTES, 'UTF-8') . '"';
        }

        return '<script ' . \implode(' ', $rendered) . '></script>';
    }

    /**
     * Url file datastar.js, mặc định lấy trên CDN theo version trong config.
     */
    public function scriptUrl(): string
    {
        if (!empty($this->config['script']['url'])) {
            return $this->config['script']['url'];
        }

        $version = \ltrim((string) ($this->config['script']['version'] ?? '1.0.2'), 'v');

        return "https://cdn.jsdelivr.net/gh/starfederation/datastar@v{$version}/bundles/datastar.js";
    }

    /**
     * @return array{retry_duration: int, use_view_transition: bool, keep_alive: int}
     */
    public function sseOptions(): array
    {
        return [
            'retry_duration' => (int) ($this->config['sse']['retry_duration'] ?? Consts::DEFAULT_SSE_RETRY_DURATION),
            'use_view_transition' => (bool) ($this->config['sse']['use_view_transition'] ?? Consts::DEFAULT_ELEMENTS_USE_VIEW_TRANSITIONS),
            'keep_alive' => (int) ($this->config['sse']['keep_alive'] ?? 0),
        ];
    }

    /**
     * Thêm tiền tố module cho view giống `Response::view()`.
     */
    protected function resolveView(string $view): string
    {
        if (\str_starts_with($view, '@')) {
            return $view;
        }

        $module = $this->request()->getRoute()?->getModule();

        return $module === null ? $view : "@{$module->getId()}/$view";
    }

    protected function request(): Request
    {
        return $this->app->get('request');
    }
}
