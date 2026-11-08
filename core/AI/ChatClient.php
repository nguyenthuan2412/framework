<?php

namespace Ryra\Core\AI;

use Closure;
use Generator;
use Ryra\Core\AI\Advisor\ChatRequest;
use Ryra\Core\AI\Exceptions\AIException;
use Ryra\Core\AI\Message\Media;
use Ryra\Core\AI\Message\Message;
use Ryra\Core\AI\Message\Role;
use Ryra\Core\AI\Response\ChatResponse;
use Ryra\Core\AI\Response\FinishReason;
use Ryra\Core\AI\Response\StreamChunk;
use Ryra\Core\AI\Response\Usage;
use Ryra\Core\AI\Template\PromptTemplate;
use Ryra\Core\AI\Tool\ToolDefinition;
use Ryra\Core\AI\Tool\Toolbox;
use Ryra\Core\Contracts\AI\Advisor;
use Ryra\Core\Contracts\AI\ChatModel;
use Ryra\Core\Contracts\AI\StreamingChatModel;
use Ryra\Core\Macro\Macroable;
use Ryra\Core\Pipeline\Pipeline;

/**
 * API dạng chuỗi để dựng và gửi một lượt hội thoại.
 *
 * Mỗi lần gọi AI::chat() sẽ tạo một instance mới nên có thể dùng thoải mái
 * trong môi trường coroutine mà không sợ lẫn state giữa các request.
 */
class ChatClient
{
    use Macroable;

    protected Prompt $prompt;

    protected ChatOptions $options;

    /**
     * Số vòng tối đa cho phép model gọi tool rồi nhận kết quả và gọi tiếp.
     * Chặn trường hợp model lặp vô hạn.
     */
    protected int $maxToolIterations = 5;

    /**
     * @var array<int, Advisor>
     */
    protected array $advisors = [];

    /**
     * Dữ liệu dùng chung giữa các advisor trong một lần gọi.
     *
     * @var array<string, mixed>
     */
    protected array $context = [];

    public function __construct(protected ChatModel $model)
    {
        $this->prompt = new Prompt();
        $this->options = ChatOptions::make();
    }

    public function system(string $content, bool $cacheable = false): static
    {
        $this->prompt->system($content, $cacheable);
        return $this;
    }

    /**
     * @param array<int, Media> $media
     */
    public function user(string $content, array $media = []): static
    {
        $this->prompt->user($content, $media);
        return $this;
    }

    public function assistant(string $content): static
    {
        $this->prompt->assistant($content);
        return $this;
    }

    /**
     * Dựng lượt nói từ mẫu có biến.
     *
     * @param array<string, mixed> $variables
     */
    public function template(PromptTemplate|string $template, array $variables = [], Role $role = Role::User): static
    {
        $template = $template instanceof PromptTemplate ? $template : PromptTemplate::make($template);
        $content = $template->render($variables);

        return match ($role) {
            Role::System => $this->system($content),
            Role::Assistant => $this->assistant($content),
            default => $this->user($content),
        };
    }

    /**
     * Gắn dữ liệu tuỳ ý cho advisor dùng trong lần gọi này.
     */
    public function context(string $key, mixed $value): static
    {
        $this->context[$key] = $value;
        return $this;
    }

    public function message(Message ...$messages): static
    {
        $this->prompt->add(...$messages);
        return $this;
    }

    /**
     * Nạp sẵn lịch sử hội thoại (ví dụ lấy từ DB).
     *
     * @param array<int, Message> $messages
     */
    public function history(array $messages): static
    {
        return $this->message(...$messages);
    }

    public function prompt(Prompt $prompt): static
    {
        $this->prompt = $prompt;
        return $this;
    }

    public function model(string $model): static
    {
        $this->options->model($model);
        return $this;
    }

    public function maxTokens(int $maxTokens): static
    {
        $this->options->maxTokens($maxTokens);
        return $this;
    }

    public function temperature(float $temperature): static
    {
        $this->options->temperature($temperature);
        return $this;
    }

    public function topP(float $topP): static
    {
        $this->options->topP($topP);
        return $this;
    }

    /**
     * @param array<int, string> $stopSequences
     */
    public function stopSequences(array $stopSequences): static
    {
        $this->options->stopSequences($stopSequences);
        return $this;
    }

    public function effort(string $effort): static
    {
        $this->options->effort($effort);
        return $this;
    }

    public function thinking(bool $thinking = true): static
    {
        $this->options->thinking($thinking);
        return $this;
    }

    /**
     * @param array<int, ToolDefinition|object>|Toolbox $tools
     */
    public function tools(Toolbox|array $tools): static
    {
        $this->options->tools($tools);
        return $this;
    }

    public function toolChoice(string $choice): static
    {
        $this->options->toolChoice($choice);
        return $this;
    }

    /**
     * Buộc model trả về JSON đúng theo schema.
     *
     * @param array<string, mixed> $schema
     */
    public function schema(array $schema, string $name = 'response'): static
    {
        $this->options->responseSchema($schema, $name);
        return $this;
    }

    /**
     * @param array<string, mixed> $extra Tham số thô riêng của nhà cung cấp.
     */
    public function extra(array $extra): static
    {
        $this->options->extra($extra);
        return $this;
    }

    public function options(ChatOptions $options): static
    {
        $this->options = $options;
        return $this;
    }

    public function maxToolIterations(int $iterations): static
    {
        if ($iterations < 1) {
            throw new AIException("maxToolIterations must be at least 1, {$iterations} given.");
        }

        $this->maxToolIterations = $iterations;
        return $this;
    }

    /**
     * Bọc lần gọi này bằng các advisor: nhớ hội thoại, chèn ngữ cảnh RAG,
     * ghi log, chặn nội dung cấm...
     */
    public function advisors(Advisor ...$advisors): static
    {
        array_push($this->advisors, ...$advisors);

        return $this;
    }

    /**
     * Gửi prompt. Nếu model yêu cầu gọi tool và tool có handler chạy được thì
     * tự động thực thi rồi gửi kết quả lại cho tới khi model đưa ra câu trả lời.
     */
    public function call(): ChatResponse
    {
        return $this->through(fn (ChatRequest $request): ChatResponse => $this->execute($request));
    }

    /**
     * Chạy chuỗi advisor rồi tới đích cuối cùng.
     *
     * @param Closure(ChatRequest): ChatResponse $destination
     */
    protected function through(Closure $destination): ChatResponse
    {
        $request = new ChatRequest($this->prompt, $this->options, $this->context);

        if ($this->advisors === []) {
            return $destination($request);
        }

        $advisors = $this->advisors;
        usort($advisors, static fn (Advisor $a, Advisor $b): int => $a->order() <=> $b->order());

        return (new Pipeline())
            ->send($request)
            ->through($advisors)
            ->via('advise')
            ->then($destination);
    }

    /**
     * Gọi model kèm vòng lặp tool.
     */
    protected function execute(ChatRequest $request): ChatResponse
    {
        $toolbox = $request->options->getTools();

        if ($toolbox === null || !$toolbox->hasExecutable()) {
            return $this->model->call($request->prompt, $request->options);
        }

        $prompt = $request->prompt->clone();
        $usage = new Usage();

        for ($iteration = 0; $iteration < $this->maxToolIterations; $iteration++) {
            $response = $this->model->call($prompt, $request->options);
            $usage = $usage->add($response->usage);

            if (!$response->hasToolCalls()) {
                return $this->withUsage($response, $usage);
            }

            $prompt->add($response->toMessage());

            foreach ($response->toolCalls as $call) {
                $prompt->add($toolbox->execute($call));
            }
        }

        throw new AIException(
            sprintf('Tool loop exceeded %d iterations without a final answer.', $this->maxToolIterations)
        );
    }

    public function text(): string
    {
        return $this->call()->text;
    }

    /**
     * @return array<string, mixed>
     */
    public function json(): array
    {
        return $this->call()->json();
    }

    /**
     * @return Generator<int, StreamChunk>
     */
    public function stream(): Generator
    {
        if (!$this->model instanceof StreamingChatModel) {
            throw new AIException(
                sprintf('Driver "%s" does not support streaming.', $this->model->name())
            );
        }

        // Generator không đi qua được chuỗi advisor vì advisor cần một
        // ChatResponse hoàn chỉnh để xử lý sau khi gọi. Báo lỗi rõ thay vì
        // lặng lẽ bỏ qua advisor mà người dùng đã đăng ký.
        if ($this->advisors !== []) {
            throw new AIException(
                'stream() không chạy được advisor. Dùng streamTo() nếu cần cả streaming lẫn advisor.'
            );
        }

        return $this->model->stream($this->prompt, $this->options);
    }

    /**
     * Stream nhưng vẫn trả về kết quả gộp ở cuối - tiện khi vừa đẩy token ra
     * WebSocket vừa cần lưu lại toàn bộ câu trả lời.
     *
     * @param callable(StreamChunk):void $onChunk
     */
    public function streamTo(callable $onChunk): ChatResponse
    {
        return $this->through(function (ChatRequest $request) use ($onChunk): ChatResponse {
            if (!$this->model instanceof StreamingChatModel) {
                throw new AIException(
                    sprintf('Driver "%s" does not support streaming.', $this->model->name())
                );
            }

            $text = '';
            $last = null;

            foreach ($this->model->stream($request->prompt, $request->options) as $chunk) {
                $text .= $chunk->text;
                $last = $chunk;
                $onChunk($chunk);
            }

            return new ChatResponse(
                $text,
                $last?->toolCalls ?? [],
                $last?->usage ?? new Usage(),
                $last?->finishReason ?? FinishReason::Unknown,
                $request->options->getModel() ?? $this->model->defaultModel(),
                $this->model->name(),
            );
        });
    }

    public function getPrompt(): Prompt
    {
        return $this->prompt;
    }

    public function getOptions(): ChatOptions
    {
        return $this->options;
    }

    protected function withUsage(ChatResponse $response, Usage $usage): ChatResponse
    {
        return new ChatResponse(
            $response->text,
            $response->toolCalls,
            $usage,
            $response->finishReason,
            $response->model,
            $response->provider,
            $response->raw,
        );
    }
}
