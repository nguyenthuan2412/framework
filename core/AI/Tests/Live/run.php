<?php

/**
 * Bộ kiểm thử gọi API thật.
 *
 * Chạy:
 *   export GEMINI_API_KEY=...
 *   php core/AI/Tests/Live/run.php
 *
 * Không nằm trong PHPUnit vì core/HttpClient dùng Swoole\Coroutine\Http\Client,
 * mà client này bắt buộc chạy trong coroutine. Bộ này kiểm những thứ chỉ lộ ra
 * khi gọi thật: payload có bị nhà cung cấp từ chối không, SSE thật có parse
 * đúng không, số chiều vector có khớp config không.
 *
 * Chỉ dùng dữ liệu bịa, không đụng tới cơ sở dữ liệu của ứng dụng.
 */

$base = dirname(__DIR__, 4);

require "{$base}/vendor/autoload.php";
require "{$base}/core/Collection/functions.php";

use Ryra\Core\AI\ChatOptions;
use Ryra\Core\AI\Document\Ingestor;
use Ryra\Core\AI\Document\Reader\TextReader;
use Ryra\Core\AI\Drivers\ClaudeDriver;
use Ryra\Core\AI\Drivers\GeminiDriver;
use Ryra\Core\AI\Drivers\OpenAIDriver;
use Ryra\Core\AI\Embedding\Drivers\GeminiEmbeddingDriver;
use Ryra\Core\AI\Embedding\Drivers\OpenAIEmbeddingDriver;
use Ryra\Core\AI\Exceptions\ProviderException;
use Ryra\Core\AI\Advisor\RetrievalAdvisor;
use Ryra\Core\AI\ChatClient;
use Ryra\Core\AI\Prompt;
use Ryra\Core\AI\Response\FinishReason;
use Ryra\Core\AI\Tests\Fixtures\WeatherTools;
use Ryra\Core\AI\VectorStore\Drivers\MemoryDriver;
use Ryra\Core\Contracts\AI\ChatModel;
use Ryra\Core\Contracts\AI\EmbeddingModel;
use Swoole\Coroutine;

Coroutine::set(['hook_flags' => SWOOLE_HOOK_ALL]);

/**
 * Bộ đếm và in kết quả cho gọn.
 */
final class Report
{
    public int $passed = 0;

    public int $failed = 0;

    public int $skipped = 0;

    public function section(string $name): void
    {
        printf("\n\033[1m%s\033[0m\n", $name);
    }

    public function check(string $label, bool $ok, string $detail = ''): void
    {
        $ok ? $this->passed++ : $this->failed++;

        printf(
            "  %s %s%s\n",
            $ok ? "\033[32mOK  \033[0m" : "\033[31mFAIL\033[0m",
            $label,
            $detail === '' ? '' : "  \033[90m{$detail}\033[0m",
        );
    }

    public function skip(string $label, string $reason): void
    {
        $this->skipped++;
        printf("  \033[33mSKIP\033[0m %s  \033[90m%s\033[0m\n", $label, $reason);
    }

    public function fail(string $label, Throwable $e): void
    {
        $this->failed++;
        printf("  \033[31mFAIL\033[0m %s\n       \033[90m%s\033[0m\n", $label, $e->getMessage());
    }

    public function summary(): int
    {
        printf(
            "\n\033[1mTong: %d dat, %d hong, %d bo qua\033[0m\n",
            $this->passed,
            $this->failed,
            $this->skipped,
        );

        return $this->failed === 0 ? 0 : 1;
    }
}

$report = new Report();

Coroutine\run(function () use ($report): void {
    $providers = [
        'gemini' => [
            'env' => 'GEMINI_API_KEY',
            'key' => getenv('GEMINI_API_KEY') ?: null,
            'chat' => fn (array $c): ChatModel => new GeminiDriver($c + ['model' => 'gemini-3.6-flash']),
            'embedding' => fn (array $c): EmbeddingModel => new GeminiEmbeddingDriver(
                $c + ['model' => 'gemini-embedding-001', 'dimensions' => 1536],
            ),
        ],
        'claude' => [
            'env' => 'ANTHROPIC_API_KEY',
            'key' => getenv('ANTHROPIC_API_KEY') ?: null,
            'chat' => fn (array $c): ChatModel => new ClaudeDriver($c + ['model' => 'claude-haiku-4-5']),
            'embedding' => null,
        ],
        'openai' => [
            'env' => 'OPENAI_API_KEY',
            'key' => getenv('OPENAI_API_KEY') ?: null,
            'chat' => fn (array $c): ChatModel => new OpenAIDriver($c + ['model' => 'gpt-4o-mini']),
            'embedding' => fn (array $c): EmbeddingModel => new OpenAIEmbeddingDriver(
                $c + ['model' => 'text-embedding-3-small', 'dimensions' => 1536],
            ),
        ],
    ];

    foreach ($providers as $name => $provider) {
        $report->section(strtoupper($name));

        if ($provider['key'] === null) {
            $report->skip('tat ca', $provider['env'] . ' chua duoc dat');
            continue;
        }

        $config = ['api_key' => $provider['key']];
        $model = ($provider['chat'])($config);

        runChatChecks($report, $model);

        if ($provider['embedding'] !== null) {
            $embeddings = ($provider['embedding'])($config);
            runEmbeddingChecks($report, $model, $embeddings);
        } else {
            $report->skip('embedding', 'nha cung cap khong co API embedding');
        }
    }
});

exit($report->summary());

function runChatChecks(Report $report, ChatModel $model): void
{
    // 1. Gọi cơ bản: payload có được nhà cung cấp chấp nhận không.
    try {
        $response = $model->call(
            Prompt::of('Tra loi dung mot tu: thu do cua Viet Nam ten gi?'),
            ChatOptions::make()->maxTokens(2000)->thinking(false),
        );

        $report->check(
            'chat co ban',
            stripos($response->text, 'noi') !== false || stripos($response->text, 'nội') !== false,
            sprintf('"%s" | %d token', trim($response->text), $response->usage->totalTokens()),
        );

        $report->check(
            'usage co so lieu that',
            $response->usage->inputTokens > 0 && $response->usage->outputTokens > 0,
            sprintf('in=%d out=%d', $response->usage->inputTokens, $response->usage->outputTokens),
        );

        $report->check('finish reason chuan hoa', $response->finishReason === FinishReason::Stop, $response->finishReason->value);
    } catch (Throwable $e) {
        $report->fail('chat co ban', $e);
    }

    // 2. Streaming: token phải về dần chứ không dồn một cục.
    try {
        $chunks = 0;
        $text = '';
        $first = null;
        $startedAt = microtime(true);
        $last = null;

        foreach ($model->stream(
            Prompt::of('Liet ke 5 tinh mien Trung Viet Nam, moi tinh mot dong.'),
            ChatOptions::make()->maxTokens(2000)->thinking(false),
        ) as $chunk) {
            if ($chunk->text !== '') {
                $chunks++;
                $first ??= microtime(true) - $startedAt;
            }

            $text .= $chunk->text;
            $last = $chunk;
        }

        $total = microtime(true) - $startedAt;

        $report->check('streaming nhan duoc noi dung', trim($text) !== '', sprintf('%d ky tu', mb_strlen($text)));
        $report->check(
            'streaming chia nhieu manh',
            $chunks > 1,
            sprintf('%d manh, chunk dau sau %.2fs, tong %.2fs', $chunks, $first ?? 0, $total),
        );
        $report->check('chunk cuoi co usage', ($last?->usage?->outputTokens ?? 0) > 0);
    } catch (Throwable $e) {
        $report->fail('streaming', $e);
    }

    // 3. Tool calling trọn vòng qua ChatClient.
    try {
        $tools = new WeatherTools();

        $answer = (new ChatClient($model))
            ->user('Nhiet do o Da Nang bao nhieu? Dung cong cu de tra cuu.')
            ->tools([$tools])
            ->maxTokens(2000)
            ->thinking(false)
            ->text();

        $report->check(
            'tool calling tron vong',
            $tools->invocations > 0,
            sprintf('goi tool %d lan | "%s"', $tools->invocations, mb_strimwidth(trim($answer), 0, 60, '...')),
        );
    } catch (Throwable $e) {
        $report->fail('tool calling', $e);
    }

    // 4. Structured output: JSON đúng schema.
    try {
        $data = (new ChatClient($model))
            ->user('Trich xuat: "An trua 500000 dong cho doan 12 khach".')
            ->schema([
                'type' => 'object',
                'properties' => [
                    'so_tien' => ['type' => 'integer'],
                    'so_khach' => ['type' => 'integer'],
                ],
                'required' => ['so_tien', 'so_khach'],
                'additionalProperties' => false,
            ])
            ->maxTokens(2000)
            ->thinking(false)
            ->json();

        $report->check(
            'structured output',
            ($data['so_tien'] ?? null) === 500000 && ($data['so_khach'] ?? null) === 12,
            json_encode($data, JSON_UNESCAPED_UNICODE),
        );
    } catch (Throwable $e) {
        $report->fail('structured output', $e);
    }

    // 5. Lỗi phải thành ProviderException đọc được, không phải lỗi PHP khó hiểu.
    try {
        $model->call(Prompt::of('xin chao'), ChatOptions::make()->model('model-khong-ton-tai-abc'));
        $report->check('loi model sai nem ProviderException', false, 'khong nem loi');
    } catch (ProviderException $e) {
        $report->check(
            'loi model sai nem ProviderException',
            $e->status >= 400,
            sprintf('HTTP %d: %s', $e->status, mb_strimwidth($e->getMessage(), 0, 70, '...')),
        );
    } catch (Throwable $e) {
        $report->fail('loi model sai nem ProviderException', $e);
    }
}

function runEmbeddingChecks(Report $report, ChatModel $model, EmbeddingModel $embeddings): void
{
    // 6. Số chiều thật phải khớp config, lệch là VectorStore sai âm thầm.
    try {
        $vector = $embeddings->embed('chi phi an uong cua doan khach');

        $report->check(
            'so chieu vector khop config',
            count($vector) === $embeddings->dimensions(),
            sprintf('that=%d, config=%d', count($vector), $embeddings->dimensions()),
        );

        $batch = $embeddings->embedBatch(['mot', 'hai', 'ba']);
        $report->check('nhung ca lo', count($batch) === 3, sprintf('%d vector', count($batch)));
    } catch (Throwable $e) {
        $report->fail('embedding', $e);
    }

    // 7. RAG đầu cuối: nạp tài liệu -> tìm -> trả lời đúng số liệu trong tài liệu.
    try {
        $file = sys_get_temp_dir() . '/ryra_live_' . uniqid() . '.txt';
        file_put_contents($file, implode("\n\n", [
            'Tour Ha Long 3 ngay 2 dem. Chi phi an uong cua doan la 7.500.000 dong.',
            'Khach san Muong Thanh Ha Long da duoc dat truoc cho 12 khach.',
            'Huong dan vien phu trach doan la anh Nguyen Van Thuan.',
        ]));

        $store = new MemoryDriver($embeddings);

        (new Ingestor($store))
            ->read(new TextReader($file))
            ->split(chunkSize: 200, chunkOverlap: 40)
            ->run();

        unlink($file);

        $answer = (new ChatClient($model))
            ->user('Chi phi an uong cua doan la bao nhieu?')
            ->advisors(new RetrievalAdvisor($store, topK: 2))
            ->maxTokens(2000)
            ->thinking(false)
            ->text();

        $report->check(
            'RAG dau cuoi',
            str_contains(str_replace(['.', ',', ' '], '', $answer), '7500000'),
            sprintf('"%s"', mb_strimwidth(trim($answer), 0, 70, '...')),
        );
    } catch (Throwable $e) {
        $report->fail('RAG dau cuoi', $e);
    }
}
