<?php

namespace Ryra\Core\AI\Tests;

use PHPUnit\Framework\TestCase;
use Ryra\Core\AI\Drivers\ClaudeDriver;
use Ryra\Core\AI\Drivers\GeminiDriver;
use Ryra\Core\AI\Drivers\OpenAIDriver;
use Ryra\Core\AI\Http\SseParser;
use Ryra\Core\AI\Prompt;
use Ryra\Core\AI\Response\FinishReason;
use Ryra\Core\AI\Tests\Fixtures\FakeTransport;

class StreamingTest extends TestCase
{
    public function test_sse_parser_gom_event_bi_cat_giua_chung(): void
    {
        $parser = new SseParser();

        // Gói tin TCP có thể cắt ở bất kỳ đâu, kể cả giữa từ "data".
        $events = [];

        foreach (["event: delta\nda", "ta: {\"a\":1}\n", "\nevent: delta\ndata: {\"a\":2}\n\n"] as $chunk) {
            foreach ($parser->push($chunk) as $event) {
                $events[] = $event;
            }
        }

        $this->assertCount(2, $events);
        $this->assertSame('delta', $events[0]->name);
        $this->assertSame(['a' => 1], $events[0]->json());
        $this->assertSame(['a' => 2], $events[1]->json());
    }

    public function test_sse_parser_gop_nhieu_dong_data(): void
    {
        $parser = new SseParser();
        $events = iterator_to_array($parser->push("data: dong 1\ndata: dong 2\n\n"));

        $this->assertSame("dong 1\ndong 2", $events[0]->data);
    }

    public function test_sse_parser_bo_qua_comment_va_nhan_dien_done(): void
    {
        $parser = new SseParser();
        $events = iterator_to_array($parser->push(": heartbeat\n\ndata: [DONE]\n\n"));

        $this->assertCount(1, $events);
        $this->assertTrue($events[0]->isDone());
    }

    public function test_claude_stream_gom_text_va_tool_call(): void
    {
        $raw = <<<SSE
        event: message_start
        data: {"type":"message_start","message":{"usage":{"input_tokens":25,"cache_read_input_tokens":100}}}

        event: content_block_delta
        data: {"type":"content_block_delta","index":0,"delta":{"type":"text_delta","text":"Để tôi "}}

        event: content_block_delta
        data: {"type":"content_block_delta","index":0,"delta":{"type":"text_delta","text":"tra cứu."}}

        event: content_block_start
        data: {"type":"content_block_start","index":1,"content_block":{"type":"tool_use","id":"toolu_1","name":"get_weather"}}

        event: content_block_delta
        data: {"type":"content_block_delta","index":1,"delta":{"type":"input_json_delta","partial_json":"{\\"city\\":"}}

        event: content_block_delta
        data: {"type":"content_block_delta","index":1,"delta":{"type":"input_json_delta","partial_json":"\\"Hue\\"}"}}

        event: message_delta
        data: {"type":"message_delta","delta":{"stop_reason":"tool_use"},"usage":{"output_tokens":42}}

        SSE;

        $driver = new ClaudeDriver(
            ['api_key' => 'k', 'model' => 'claude-opus-5'],
            new FakeTransport(FakeTransport::eventsFrom($raw)),
        );

        $text = '';
        $last = null;

        foreach ($driver->stream(Prompt::of('Thời tiết Huế?')) as $chunk) {
            $text .= $chunk->text;
            $last = $chunk;
        }

        $this->assertSame('Để tôi tra cứu.', $text);
        $this->assertTrue($last->isLast);
        $this->assertSame(FinishReason::ToolCalls, $last->finishReason);

        // Tham số tool được gửi thành nhiều mảnh JSON, phải ghép đúng.
        $this->assertCount(1, $last->toolCalls);
        $this->assertSame('get_weather', $last->toolCalls[0]->name);
        $this->assertSame(['city' => 'Hue'], $last->toolCalls[0]->arguments);

        $this->assertSame(25, $last->usage->inputTokens);
        $this->assertSame(42, $last->usage->outputTokens);
        $this->assertSame(100, $last->usage->cacheReadTokens);
    }

    public function test_openai_stream_gom_tool_call_theo_index(): void
    {
        $raw = <<<SSE
        data: {"choices":[{"delta":{"content":"Đang tra"},"index":0}]}

        data: {"choices":[{"delta":{"tool_calls":[{"index":0,"id":"call_1","function":{"name":"get_weather","arguments":"{\\"city\\""}}]},"index":0}]}

        data: {"choices":[{"delta":{"tool_calls":[{"index":0,"function":{"arguments":":\\"Hue\\"}"}}]},"index":0}]}

        data: {"choices":[{"delta":{},"finish_reason":"tool_calls","index":0}]}

        data: {"usage":{"prompt_tokens":30,"completion_tokens":12,"prompt_tokens_details":{"cached_tokens":10}},"choices":[]}

        data: [DONE]

        SSE;

        $driver = new OpenAIDriver(
            ['api_key' => 'k', 'model' => 'gpt-4o'],
            new FakeTransport(FakeTransport::eventsFrom($raw)),
        );

        $text = '';
        $last = null;

        foreach ($driver->stream(Prompt::of('Thời tiết Huế?')) as $chunk) {
            $text .= $chunk->text;
            $last = $chunk;
        }

        $this->assertSame('Đang tra', $text);
        $this->assertSame(FinishReason::ToolCalls, $last->finishReason);
        $this->assertSame('call_1', $last->toolCalls[0]->id);
        $this->assertSame(['city' => 'Hue'], $last->toolCalls[0]->arguments);

        // prompt_tokens đã gồm cached, không được đếm hai lần.
        $this->assertSame(20, $last->usage->inputTokens);
        $this->assertSame(10, $last->usage->cacheReadTokens);
        $this->assertSame(12, $last->usage->outputTokens);
    }

    public function test_gemini_stream_gom_text_va_function_call(): void
    {
        $raw = <<<SSE
        data: {"candidates":[{"content":{"parts":[{"text":"Đang "}],"role":"model"}}]}

        data: {"candidates":[{"content":{"parts":[{"text":"tra cứu."}],"role":"model"}}]}

        data: {"candidates":[{"content":{"parts":[{"functionCall":{"name":"get_weather","args":{"city":"Hue"}}}]},"finishReason":"STOP"}],"usageMetadata":{"promptTokenCount":18,"candidatesTokenCount":7}}

        SSE;

        $driver = new GeminiDriver(
            ['api_key' => 'k', 'model' => 'gemini-2.5-flash'],
            new FakeTransport(FakeTransport::eventsFrom($raw)),
        );

        $text = '';
        $last = null;

        foreach ($driver->stream(Prompt::of('Thời tiết Huế?')) as $chunk) {
            $text .= $chunk->text;
            $last = $chunk;
        }

        $this->assertSame('Đang tra cứu.', $text);
        $this->assertSame(FinishReason::Stop, $last->finishReason);
        $this->assertSame('get_weather', $last->toolCalls[0]->name);
        $this->assertSame(['city' => 'Hue'], $last->toolCalls[0]->arguments);
        $this->assertSame(18, $last->usage->inputTokens);
    }

    public function test_gemini_them_alt_sse_vao_url_khi_stream(): void
    {
        $transport = new FakeTransport([]);
        $driver = new GeminiDriver(['api_key' => 'k', 'model' => 'gemini-2.5-flash'], $transport);

        iterator_to_array($driver->stream(Prompt::of('Chào')));

        // Thiếu alt=sse thì Gemini trả về một mảng JSON lớn chứ không phải SSE.
        $this->assertStringContainsString(':streamGenerateContent?alt=sse', $transport->requests[0]['url']);
    }

    public function test_parse_phan_hoi_khong_stream_cua_ba_provider(): void
    {
        $claude = new ClaudeDriver(
            ['api_key' => 'k', 'model' => 'claude-opus-5'],
            new FakeTransport([], [
                'model' => 'claude-opus-5',
                'stop_reason' => 'end_turn',
                'content' => [
                    ['type' => 'thinking', 'thinking' => 'suy nghĩ nội bộ'],
                    ['type' => 'text', 'text' => 'Xin chào'],
                    ['type' => 'tool_use', 'id' => 't1', 'name' => 'get_weather', 'input' => ['city' => 'Hue']],
                ],
                'usage' => ['input_tokens' => 10, 'output_tokens' => 5],
            ]),
        );

        $response = $claude->call(Prompt::of('Chào'));
        // Khối thinking không được lẫn vào nội dung trả lời.
        $this->assertSame('Xin chào', $response->text);
        $this->assertSame('get_weather', $response->toolCalls[0]->name);
        $this->assertSame(FinishReason::Stop, $response->finishReason);

        $openai = new OpenAIDriver(
            ['api_key' => 'k', 'model' => 'gpt-4o'],
            new FakeTransport([], [
                'model' => 'gpt-4o',
                'choices' => [[
                    'finish_reason' => 'stop',
                    'message' => [
                        'content' => 'Xin chào',
                        'tool_calls' => [[
                            'id' => 'call_1',
                            'function' => ['name' => 'get_weather', 'arguments' => '{"city":"Hue"}'],
                        ]],
                    ],
                ]],
                'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 5],
            ]),
        );

        $response = $openai->call(Prompt::of('Chào'));
        $this->assertSame('Xin chào', $response->text);
        $this->assertSame(['city' => 'Hue'], $response->toolCalls[0]->arguments);

        $gemini = new GeminiDriver(
            ['api_key' => 'k', 'model' => 'gemini-2.5-flash'],
            new FakeTransport([], [
                'candidates' => [[
                    'finishReason' => 'MAX_TOKENS',
                    'content' => ['parts' => [['text' => 'Xin chào']]],
                ]],
                'usageMetadata' => ['promptTokenCount' => 10, 'candidatesTokenCount' => 5],
            ]),
        );

        $response = $gemini->call(Prompt::of('Chào'));
        $this->assertSame('Xin chào', $response->text);
        // Bị cắt giữa chừng phải nhận ra được để không coi là câu trả lời đủ.
        $this->assertTrue($response->finishReason->isTruncated());
    }
}
