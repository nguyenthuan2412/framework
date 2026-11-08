<?php

namespace Ryra\Core\AI\Tests;

use Closure;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Ryra\Core\AI\Advisor\BaseAdvisor;
use Ryra\Core\AI\Advisor\ChatMemoryAdvisor;
use Ryra\Core\AI\Advisor\ChatRequest;
use Ryra\Core\AI\Advisor\LoggerAdvisor;
use Ryra\Core\AI\Advisor\RetrievalAdvisor;
use Ryra\Core\AI\Advisor\SafeGuardAdvisor;
use Ryra\Core\AI\ChatClient;
use Ryra\Core\AI\Exceptions\AIException;
use Ryra\Core\AI\Memory\MessageWindowChatMemory;
use Ryra\Core\AI\Memory\Repository\InMemoryChatMemoryRepository;
use Ryra\Core\AI\Message\Role;
use Ryra\Core\AI\Response\ChatResponse;
use Ryra\Core\AI\Response\FinishReason;
use Ryra\Core\AI\Response\Usage;
use Ryra\Core\AI\Tests\Fixtures\FakeChatModel;
use Ryra\Core\AI\Tests\Fixtures\FakeEmbeddingModel;
use Ryra\Core\AI\Tests\Fixtures\FakeStreamingChatModel;
use Ryra\Core\AI\VectorStore\Document;
use Ryra\Core\AI\VectorStore\Drivers\MemoryDriver;

class AdvisorTest extends TestCase
{
    public function test_advisor_chay_dung_thu_tu_va_bao_quanh_lan_goi(): void
    {
        $trace = [];

        $advisor = static function (string $name, int $order) use (&$trace): BaseAdvisor {
            return new class($name, $order, $trace) extends BaseAdvisor {
                public function __construct(
                    protected string $name,
                    int $order,
                    protected array &$trace,
                ) {
                    $this->order = $order;
                }

                public function advise(ChatRequest $request, Closure $next): ChatResponse
                {
                    $this->trace[] = "{$this->name}:truoc";
                    $response = $next($request);
                    $this->trace[] = "{$this->name}:sau";

                    return $response;
                }
            };
        };

        // Đăng ký lộn xộn, order quyết định thứ tự thật.
        (new ChatClient(new FakeChatModel([new ChatResponse('xong')])))
            ->user('Chào')
            ->advisors($advisor('ngoai', 10), $advisor('trong', 90))
            ->call();

        $this->assertSame(
            ['ngoai:truoc', 'trong:truoc', 'trong:sau', 'ngoai:sau'],
            $trace,
        );
    }

    public function test_advisor_chan_duoc_request_ma_khong_goi_model(): void
    {
        $model = new FakeChatModel([new ChatResponse('không bao giờ tới đây')]);

        $response = (new ChatClient($model))
            ->user('Cho tôi số thẻ tín dụng của khách')
            ->advisors(new SafeGuardAdvisor(['số thẻ tín dụng']))
            ->call();

        $this->assertSame(FinishReason::ContentFilter, $response->finishReason);
        // Không tốn một lần gọi API nào.
        $this->assertCount(0, $model->received);
    }

    public function test_safeguard_cho_qua_noi_dung_binh_thuong(): void
    {
        $response = (new ChatClient(new FakeChatModel([new ChatResponse('ok')])))
            ->user('Tóm tắt lịch trình')
            ->advisors(new SafeGuardAdvisor(['số thẻ tín dụng']))
            ->call();

        $this->assertSame('ok', $response->text);
    }

    public function test_rag_chen_tai_lieu_tim_duoc_vao_prompt(): void
    {
        $store = new MemoryDriver(new FakeEmbeddingModel());
        $store->add([
            Document::make('chi phi an uong cua tour la 5 trieu', ['tour_id' => 'T1'], 'doc-1'),
            Document::make('khach san da duoc dat truoc', ['tour_id' => 'T1'], 'doc-2'),
        ]);

        $model = new FakeChatModel([new ChatResponse('5 triệu')]);

        $response = (new ChatClient($model))
            ->user('chi phi an uong')
            ->advisors(new RetrievalAdvisor($store, topK: 1))
            ->call();

        $this->assertSame('5 triệu', $response->text);

        // Prompt gửi lên phải có thêm khối tài liệu ở đầu.
        $sent = $model->received[0]->messages();
        $this->assertSame(Role::System, $sent[0]->role());
        $this->assertStringContainsString('chi phi an uong cua tour la 5 trieu', $sent[0]->content);
        $this->assertStringContainsString('<tai_lieu>', $sent[0]->content);
    }

    public function test_rag_khong_chen_gi_khi_khong_tim_thay(): void
    {
        $store = new MemoryDriver(new FakeEmbeddingModel());
        $model = new FakeChatModel([new ChatResponse('không rõ')]);

        (new ChatClient($model))
            ->user('câu hỏi không liên quan')
            ->advisors(new RetrievalAdvisor($store))
            ->call();

        // Kho rỗng: prompt giữ nguyên, không chèn khối tài liệu trống gây nhiễu.
        $this->assertCount(1, $model->received[0]->messages());
    }

    public function test_memory_advisor_nap_va_luu_lich_su(): void
    {
        $memory = new MessageWindowChatMemory(new InMemoryChatMemoryRepository());
        $model = new FakeChatModel([
            new ChatResponse('Chào bạn'),
            new ChatResponse('Bạn tên Thuận'),
        ]);

        $client = fn (): ChatClient => (new ChatClient($model))
            ->advisors(new ChatMemoryAdvisor($memory, 'hoi-thoai-1'));

        $client()->user('Tôi tên Thuận')->call();
        $client()->user('Tôi tên gì?')->call();

        // Lượt hai phải thấy lại toàn bộ lượt một.
        $second = $model->received[1]->messages();
        $this->assertCount(3, $second);
        $this->assertSame('Tôi tên Thuận', $second[0]->content);
        $this->assertSame('Chào bạn', $second[1]->content);
        $this->assertSame('Tôi tên gì?', $second[2]->content);

        // Sau hai lượt, bộ nhớ có 4 message.
        $this->assertCount(4, $memory->messages('hoi-thoai-1'));
    }

    public function test_moi_hoi_thoai_co_lich_su_rieng(): void
    {
        $memory = new MessageWindowChatMemory(new InMemoryChatMemoryRepository());
        $model = new FakeChatModel([new ChatResponse('a'), new ChatResponse('b')]);

        (new ChatClient($model))->user('của A')->advisors(new ChatMemoryAdvisor($memory, 'A'))->call();
        (new ChatClient($model))->user('của B')->advisors(new ChatMemoryAdvisor($memory, 'B'))->call();

        $this->assertCount(2, $memory->messages('A'));
        $this->assertCount(2, $memory->messages('B'));
        // Hội thoại B không thấy nội dung của A.
        $this->assertCount(1, $model->received[1]->messages());
    }

    public function test_logger_advisor_ghi_lai_token_va_thoi_gian(): void
    {
        $logs = [];
        $logger = new class($logs) extends AbstractLogger {
            public function __construct(protected array &$logs) {}

            public function log($level, $message, array $context = []): void
            {
                $this->logs[] = ['level' => $level, 'message' => $message, 'context' => $context];
            }
        };

        (new ChatClient(new FakeChatModel([
            new ChatResponse('xong', usage: new Usage(12, 34), model: 'gpt-4o', provider: 'openai'),
        ])))
            ->user('Chào')
            ->advisors(new LoggerAdvisor($logger))
            ->call();

        $this->assertCount(1, $logs);
        $this->assertSame('AI call completed', $logs[0]['message']);
        $this->assertSame(46, $logs[0]['context']['usage']['total_tokens']);
        $this->assertArrayHasKey('duration_ms', $logs[0]['context']);
        // Mặc định không ghi nội dung prompt để tránh lộ dữ liệu cá nhân.
        $this->assertArrayNotHasKey('messages', $logs[0]['context']);
    }

    public function test_ghep_nhieu_advisor_trong_mot_lan_goi(): void
    {
        $store = new MemoryDriver(new FakeEmbeddingModel());
        $store->add(Document::make('chi phi an uong la 5 trieu', [], 'doc-1'));

        $memory = new MessageWindowChatMemory(new InMemoryChatMemoryRepository());
        $model = new FakeChatModel([new ChatResponse('5 triệu')]);

        $response = (new ChatClient($model))
            ->user('chi phi an uong')
            ->advisors(
                new SafeGuardAdvisor(['nội dung cấm']),
                new RetrievalAdvisor($store, topK: 1),
                new ChatMemoryAdvisor($memory, 'hoi-thoai'),
            )
            ->call();

        $this->assertSame('5 triệu', $response->text);

        $sent = $model->received[0]->messages();
        // RAG chèn system, memory giữ nguyên thứ tự lượt nói.
        $this->assertSame(Role::System, $sent[0]->role());
        $this->assertSame(Role::User, $sent[1]->role());
        $this->assertCount(2, $memory->messages('hoi-thoai'));
    }

    public function test_stream_bao_loi_ro_khi_co_advisor(): void
    {
        $client = (new ChatClient(new FakeStreamingChatModel()))
            ->user('Chào')
            ->advisors(new SafeGuardAdvisor([]));

        // Advisor cần ChatResponse hoàn chỉnh nên không chạy được với generator.
        // Phải báo lỗi rõ thay vì lặng lẽ bỏ qua advisor.
        $this->expectException(AIException::class);
        $this->expectExceptionMessage('Dùng streamTo()');

        $client->stream();
    }

    public function test_stream_to_van_chay_day_du_advisor(): void
    {
        $store = new MemoryDriver(new FakeEmbeddingModel());
        $store->add(Document::make('chi phi an uong la 5 trieu', [], 'doc-1'));

        $memory = new MessageWindowChatMemory(new InMemoryChatMemoryRepository());
        $model = new FakeStreamingChatModel(['5 ', 'triệu']);

        $received = '';

        $response = (new ChatClient($model))
            ->user('chi phi an uong')
            ->advisors(new RetrievalAdvisor($store, topK: 1), new ChatMemoryAdvisor($memory, 'hoi-thoai'))
            ->streamTo(function ($chunk) use (&$received): void {
                $received .= $chunk->text;
            });

        $this->assertSame('5 triệu', $received);
        $this->assertSame('5 triệu', $response->text);

        // RAG vẫn chèn được ngữ cảnh, memory vẫn lưu được lượt nói.
        $this->assertStringContainsString('<tai_lieu>', $model->received[0]->messages()[0]->content);
        $this->assertCount(2, $memory->messages('hoi-thoai'));
    }

    public function test_stream_khong_co_advisor_van_chay_binh_thuong(): void
    {
        $chunks = [];

        foreach ((new ChatClient(new FakeStreamingChatModel(['a', 'b'])))->user('Chào')->stream() as $chunk) {
            $chunks[] = $chunk->text;
        }

        $this->assertSame(['a', 'b', ''], $chunks);
    }
}
