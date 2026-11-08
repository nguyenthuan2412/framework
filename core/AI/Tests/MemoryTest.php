<?php

namespace Ryra\Core\AI\Tests;

use PHPUnit\Framework\TestCase;
use Ryra\Core\AI\Exceptions\AIException;
use Ryra\Core\AI\Memory\MessageWindowChatMemory;
use Ryra\Core\AI\Memory\Repository\InMemoryChatMemoryRepository;
use Ryra\Core\AI\Message\AssistantMessage;
use Ryra\Core\AI\Message\MessageFactory;
use Ryra\Core\AI\Message\Role;
use Ryra\Core\AI\Message\SystemMessage;
use Ryra\Core\AI\Message\ToolResultMessage;
use Ryra\Core\AI\Message\UserMessage;
use Ryra\Core\AI\Response\ToolCall;

class MemoryTest extends TestCase
{
    protected function memory(int $max = 20): MessageWindowChatMemory
    {
        return new MessageWindowChatMemory(new InMemoryChatMemoryRepository(), $max);
    }

    public function test_luu_va_doc_lai_lich_su(): void
    {
        $memory = $this->memory();

        $memory->add('c1', new UserMessage('Chào'), new AssistantMessage('Chào bạn'));

        $messages = $memory->messages('c1');
        $this->assertCount(2, $messages);
        $this->assertSame('Chào', $messages[0]->content);
    }

    public function test_cat_bot_luot_cu_khi_vuot_cua_so(): void
    {
        $memory = $this->memory(max: 4);

        for ($i = 1; $i <= 5; $i++) {
            $memory->add('c1', new UserMessage("hỏi {$i}"), new AssistantMessage("đáp {$i}"));
        }

        $messages = $memory->messages('c1');

        $this->assertCount(4, $messages);
        // Giữ lại phần mới nhất.
        $this->assertSame('hỏi 4', $messages[0]->content);
        $this->assertSame('đáp 5', $messages[3]->content);
    }

    public function test_luon_giu_lai_chi_dan_he_thong(): void
    {
        $memory = $this->memory(max: 3);

        $memory->add('c1', new SystemMessage('Bạn là trợ lý tour'));

        for ($i = 1; $i <= 5; $i++) {
            $memory->add('c1', new UserMessage("hỏi {$i}"), new AssistantMessage("đáp {$i}"));
        }

        $messages = $memory->messages('c1');

        // System không bị cắt vì đó là phần định nghĩa vai trò.
        $this->assertSame(Role::System, $messages[0]->role());
        $this->assertSame('Bạn là trợ lý tour', $messages[0]->content);
        $this->assertCount(3, $messages);
    }

    public function test_khong_de_lai_tool_result_mo_coi(): void
    {
        $memory = $this->memory(max: 3);

        $memory->add(
            'c1',
            new UserMessage('thời tiết?'),
            new AssistantMessage('', [new ToolCall('c1', 'get_weather', [])]),
            new ToolResultMessage('c1', 'get_weather', '30 do'),
            new AssistantMessage('30 độ'),
            new UserMessage('còn mai?'),
        );

        // Cửa sổ cắt mất lượt assistant yêu cầu gọi tool -> tool_result tương ứng
        // cũng phải bị bỏ, nếu không nhà cung cấp sẽ từ chối request.
        foreach ($memory->messages('c1') as $message) {
            $this->assertNotInstanceOf(ToolResultMessage::class, $message);
        }
    }

    public function test_xoa_lich_su(): void
    {
        $memory = $this->memory();
        $memory->add('c1', new UserMessage('Chào'));

        $memory->clear('c1');

        $this->assertSame([], $memory->messages('c1'));
    }

    public function test_moi_hoi_thoai_doc_lap(): void
    {
        $memory = $this->memory();

        $memory->add('a', new UserMessage('của A'));
        $memory->add('b', new UserMessage('của B'));

        $this->assertSame('của A', $memory->messages('a')[0]->content);
        $this->assertSame('của B', $memory->messages('b')[0]->content);
    }

    public function test_bao_loi_khi_cua_so_vo_ly(): void
    {
        $this->expectException(AIException::class);
        $this->expectExceptionMessage('maxMessages');

        new MessageWindowChatMemory(new InMemoryChatMemoryRepository(), 0);
    }

    public function test_dung_lai_message_tu_mang_da_luu(): void
    {
        $original = [
            new SystemMessage('chỉ dẫn', cacheable: true),
            new UserMessage('câu hỏi'),
            new AssistantMessage('trả lời', [new ToolCall('t1', 'get_weather', ['city' => 'Hue'])]),
            new ToolResultMessage('t1', 'get_weather', '30 do', isError: true),
        ];

        // Mô phỏng vòng đời lưu xuống DB rồi đọc lên.
        $restored = MessageFactory::fromArrayList(
            array_map(static fn ($message): array => $message->toArray(), $original),
        );

        $this->assertCount(4, $restored);
        $this->assertTrue($restored[0]->cacheable);
        $this->assertSame('Hue', $restored[2]->toolCalls[0]->argument('city'));
        $this->assertSame('t1', $restored[3]->toolCallId);
        $this->assertTrue($restored[3]->isError);
    }

    public function test_bao_loi_khi_role_khong_hop_le(): void
    {
        $this->expectException(AIException::class);
        $this->expectExceptionMessage('Unknown message role');

        MessageFactory::fromArray(['role' => 'khong-ton-tai', 'content' => 'x']);
    }
}
