<?php

namespace Ryra\Core\AI\Tests;

use PHPUnit\Framework\TestCase;
use Ryra\Core\AI\ChatClient;
use Ryra\Core\AI\Exceptions\AIException;
use Ryra\Core\AI\Message\Role;
use Ryra\Core\AI\Message\ToolResultMessage;
use Ryra\Core\AI\Response\ChatResponse;
use Ryra\Core\AI\Response\FinishReason;
use Ryra\Core\AI\Response\ToolCall;
use Ryra\Core\AI\Response\Usage;
use Ryra\Core\AI\Tests\Fixtures\FakeChatModel;
use Ryra\Core\AI\Tests\Fixtures\WeatherTools;
use Ryra\Core\AI\Tool\ToolDefinition;

class ChatClientTest extends TestCase
{
    public function test_gui_prompt_don_gian(): void
    {
        $model = new FakeChatModel([new ChatResponse('Xin chào')]);

        $text = (new ChatClient($model))
            ->system('Bạn là trợ lý tour')
            ->user('Chào bạn')
            ->text();

        $this->assertSame('Xin chào', $text);
        $this->assertCount(2, $model->received[0]->messages());
        $this->assertSame(Role::System, $model->received[0]->messages()[0]->role());
    }

    public function test_tu_dong_chay_tool_roi_hoi_lai_model(): void
    {
        $tools = new WeatherTools();

        $model = new FakeChatModel([
            new ChatResponse(
                '',
                [new ToolCall('call_1', 'get_weather', ['city' => 'Da Nang', 'unit' => 'c'])],
                new Usage(10, 5),
                FinishReason::ToolCalls,
            ),
            new ChatResponse('Đà Nẵng đang 30 độ C', usage: new Usage(20, 8)),
        ]);

        $response = (new ChatClient($model))
            ->user('Thời tiết Đà Nẵng thế nào?')
            ->tools([$tools])
            ->call();

        $this->assertSame('Đà Nẵng đang 30 độ C', $response->text);
        $this->assertSame(1, $tools->invocations);

        // Lượt gọi thứ hai phải kèm kết quả tool.
        $second = $model->received[1]->messages();
        $toolResult = end($second);
        $this->assertInstanceOf(ToolResultMessage::class, $toolResult);
        $this->assertSame('call_1', $toolResult->toolCallId);
        $this->assertStringContainsString('Da Nang: 30 do c', $toolResult->content);

        // Token của cả hai lượt phải được cộng dồn.
        $this->assertSame(30, $response->usage->inputTokens);
        $this->assertSame(13, $response->usage->outputTokens);
    }

    public function test_loi_trong_tool_duoc_tra_ve_cho_model_thay_vi_nem_ra_ngoai(): void
    {
        $model = new FakeChatModel([
            new ChatResponse('', [new ToolCall('call_1', 'broken_tool', [])], finishReason: FinishReason::ToolCalls),
            new ChatResponse('Xin lỗi, dịch vụ đang lỗi'),
        ]);

        $response = (new ChatClient($model))
            ->user('Thời tiết?')
            ->tools([new WeatherTools()])
            ->call();

        $this->assertSame('Xin lỗi, dịch vụ đang lỗi', $response->text);

        $second = $model->received[1]->messages();
        $toolResult = end($second);
        $this->assertTrue($toolResult->isError);
        $this->assertStringContainsString('dịch vụ thời tiết đang lỗi', $toolResult->content);
    }

    public function test_chan_vong_lap_tool_vo_han(): void
    {
        $looping = array_fill(0, 10, new ChatResponse(
            '',
            [new ToolCall('call_1', 'get_weather', ['city' => 'Hue'])],
            finishReason: FinishReason::ToolCalls,
        ));

        $client = (new ChatClient(new FakeChatModel($looping)))
            ->user('Thời tiết?')
            ->tools([new WeatherTools()])
            ->maxToolIterations(3);

        $this->expectException(AIException::class);
        $this->expectExceptionMessage('Tool loop exceeded 3 iterations');

        $client->call();
    }

    public function test_khong_chay_vong_lap_khi_tool_khong_co_handler(): void
    {
        // Tool do client tự xử lý: lib phải trả nguyên tool call ra ngoài.
        $model = new FakeChatModel([
            new ChatResponse('', [new ToolCall('call_1', 'ui_confirm', [])], finishReason: FinishReason::ToolCalls),
        ]);

        $response = (new ChatClient($model))
            ->user('Xoá chi phí này')
            ->tools([ToolDefinition::make('ui_confirm', 'Hỏi xác nhận trên giao diện')])
            ->call();

        $this->assertTrue($response->hasToolCalls());
        $this->assertCount(1, $model->received);
    }

    public function test_giai_ma_json_cua_structured_output(): void
    {
        $model = new FakeChatModel([new ChatResponse('```json' . PHP_EOL . '{"total": 15000}' . PHP_EOL . '```')]);

        $data = (new ChatClient($model))
            ->user('Tổng chi phí?')
            ->schema(['type' => 'object', 'properties' => ['total' => ['type' => 'integer']]])
            ->json();

        // Model đôi khi vẫn bọc markdown, lib phải bóc được.
        $this->assertSame(['total' => 15000], $data);
    }
}
