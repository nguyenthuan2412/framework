<?php

namespace Ryra\Core\AI\Tests;

use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Ryra\Core\AI\ChatOptions;
use Ryra\Core\AI\Drivers\ClaudeDriver;
use Ryra\Core\AI\Drivers\GeminiDriver;
use Ryra\Core\AI\Drivers\OpenAIDriver;
use Ryra\Core\AI\Message\Media;
use Ryra\Core\AI\Prompt;
use Ryra\Core\AI\Response\ToolCall;
use Ryra\Core\AI\Tests\Fixtures\WeatherTools;
use Ryra\Core\AI\Tool\ToolDefinition;

class DriverPayloadTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    protected function payload(object $driver, Prompt $prompt, ChatOptions $options, bool $stream = false): array
    {
        $resolve = new ReflectionMethod($driver, 'resolveOptions');
        $build = new ReflectionMethod($driver, 'buildPayload');

        return $build->invoke($driver, $prompt, $resolve->invoke($driver, $options), $stream);
    }

    public function test_claude_bo_temperature_voi_dong_model_khong_ho_tro(): void
    {
        $driver = new ClaudeDriver(['api_key' => 'k', 'model' => 'claude-opus-5']);

        $payload = $this->payload(
            $driver,
            Prompt::of('Chào'),
            ChatOptions::make()->temperature(0.7)->topP(0.9),
        );

        // Opus 5 trả về 400 nếu nhận temperature/top_p nên driver phải tự loại bỏ.
        $this->assertArrayNotHasKey('temperature', $payload);
        $this->assertArrayNotHasKey('top_p', $payload);
    }

    public function test_claude_van_gui_temperature_voi_model_cu(): void
    {
        $driver = new ClaudeDriver(['api_key' => 'k', 'model' => 'claude-3-5-haiku-20241022']);

        $payload = $this->payload($driver, Prompt::of('Chào'), ChatOptions::make()->temperature(0.7));

        $this->assertSame(0.7, $payload['temperature']);
    }

    public function test_claude_luon_co_max_tokens(): void
    {
        $driver = new ClaudeDriver(['api_key' => 'k', 'model' => 'claude-opus-5']);

        // Anthropic bắt buộc max_tokens, thiếu là lỗi 400.
        $this->assertArrayHasKey('max_tokens', $this->payload($driver, Prompt::of('Chào'), ChatOptions::make()));
    }

    public function test_claude_tach_system_va_danh_dau_cache(): void
    {
        $driver = new ClaudeDriver(['api_key' => 'k', 'model' => 'claude-opus-5']);

        $prompt = Prompt::make()
            ->system('Bạn là trợ lý tour', cacheable: true)
            ->user('Chào');

        $payload = $this->payload($driver, $prompt, ChatOptions::make());

        $this->assertSame('Bạn là trợ lý tour', $payload['system'][0]['text']);
        $this->assertSame(['type' => 'ephemeral'], $payload['system'][0]['cache_control']);
        // System không được lẫn vào mảng messages.
        $this->assertCount(1, $payload['messages']);
        $this->assertSame('user', $payload['messages'][0]['role']);
    }

    public function test_claude_gop_nhieu_tool_result_vao_mot_message(): void
    {
        $driver = new ClaudeDriver(['api_key' => 'k', 'model' => 'claude-opus-5']);

        $prompt = Prompt::make()
            ->user('Thời tiết hai nơi?')
            ->assistant('', [
                new ToolCall('c1', 'get_weather', ['city' => 'Hue']),
                new ToolCall('c2', 'get_weather', ['city' => 'Da Nang']),
            ])
            ->toolResult('c1', 'get_weather', '30 do')
            ->toolResult('c2', 'get_weather', '32 do');

        $payload = $this->payload($driver, $prompt, ChatOptions::make());

        // user / assistant / user(gộp 2 tool_result) - tách ra sẽ làm model
        // ngừng gọi tool song song.
        $this->assertCount(3, $payload['messages']);
        $this->assertCount(2, $payload['messages'][2]['content']);
        $this->assertSame('tool_result', $payload['messages'][2]['content'][0]['type']);
    }

    public function test_claude_map_tool_va_structured_output(): void
    {
        $driver = new ClaudeDriver(['api_key' => 'k', 'model' => 'claude-opus-5']);

        $payload = $this->payload(
            $driver,
            Prompt::of('Chào'),
            ChatOptions::make()
                ->tools([new WeatherTools()])
                ->toolChoice('any')
                ->effort('xhigh')
                ->responseSchema(['type' => 'object']),
        );

        $this->assertSame('get_weather', $payload['tools'][0]['name']);
        $this->assertArrayHasKey('input_schema', $payload['tools'][0]);
        $this->assertSame(['type' => 'any'], $payload['tool_choice']);
        $this->assertSame('xhigh', $payload['output_config']['effort']);
        $this->assertSame('json_schema', $payload['output_config']['format']['type']);
    }

    public function test_openai_dung_max_completion_tokens_va_cau_truc_tool_rieng(): void
    {
        $driver = new OpenAIDriver(['api_key' => 'k', 'model' => 'gpt-4o']);

        $payload = $this->payload(
            $driver,
            Prompt::of('Chào', 'Bạn là trợ lý'),
            ChatOptions::make()->maxTokens(500)->tools([new WeatherTools()])->responseSchema(['type' => 'object']),
            true,
        );

        $this->assertSame(500, $payload['max_completion_tokens']);
        $this->assertArrayNotHasKey('max_tokens', $payload);
        $this->assertSame('function', $payload['tools'][0]['type']);
        $this->assertSame('get_weather', $payload['tools'][0]['function']['name']);
        $this->assertSame('json_schema', $payload['response_format']['type']);
        // Không có cờ này thì chunk cuối của stream sẽ thiếu usage.
        $this->assertTrue($payload['stream_options']['include_usage']);
        // OpenAI để system chung trong messages.
        $this->assertSame('system', $payload['messages'][0]['role']);
    }

    public function test_openai_map_tool_call_va_tool_result(): void
    {
        $driver = new OpenAIDriver(['api_key' => 'k', 'model' => 'gpt-4o']);

        $prompt = Prompt::make()
            ->user('Thời tiết?')
            ->assistant('', [new ToolCall('c1', 'get_weather', ['city' => 'Hue'])])
            ->toolResult('c1', 'get_weather', '30 do');

        $payload = $this->payload($driver, $prompt, ChatOptions::make());

        $this->assertSame('c1', $payload['messages'][1]['tool_calls'][0]['id']);
        $this->assertSame('{"city":"Hue"}', $payload['messages'][1]['tool_calls'][0]['function']['arguments']);
        $this->assertSame('tool', $payload['messages'][2]['role']);
        $this->assertSame('c1', $payload['messages'][2]['tool_call_id']);
    }

    public function test_gemini_dung_cau_truc_contents_va_loc_schema(): void
    {
        $driver = new GeminiDriver(['api_key' => 'k', 'model' => 'gemini-2.5-flash']);

        $payload = $this->payload(
            $driver,
            Prompt::of('Chào', 'Bạn là trợ lý'),
            ChatOptions::make()
                ->maxTokens(500)
                ->responseSchema([
                    'type' => 'object',
                    'properties' => ['ten' => ['type' => 'string']],
                    // Gemini không hiểu khoá này, gửi lên là 400.
                    'additionalProperties' => false,
                ]),
        );

        $this->assertSame('Bạn là trợ lý', $payload['systemInstruction']['parts'][0]['text']);
        $this->assertSame('user', $payload['contents'][0]['role']);
        $this->assertSame(500, $payload['generationConfig']['maxOutputTokens']);

        $schema = $payload['generationConfig']['responseSchema'];
        $this->assertSame('OBJECT', $schema['type']);
        $this->assertSame('STRING', $schema['properties']['ten']['type']);
        $this->assertArrayNotHasKey('additionalProperties', $schema);
    }

    public function test_gemini_doi_assistant_thanh_role_model(): void
    {
        $driver = new GeminiDriver(['api_key' => 'k', 'model' => 'gemini-2.5-flash']);

        $prompt = Prompt::make()->user('Chào')->assistant('Xin chào');
        $payload = $this->payload($driver, $prompt, ChatOptions::make());

        $this->assertSame('model', $payload['contents'][1]['role']);
    }

    public function test_moi_driver_map_anh_theo_dinh_dang_rieng(): void
    {
        $media = Media::fromBase64('image/png', 'AAAA');
        $prompt = Prompt::make()->user('Ảnh này là gì?', [$media]);
        $options = ChatOptions::make();

        $claude = $this->payload(new ClaudeDriver(['api_key' => 'k', 'model' => 'claude-opus-5']), $prompt, $options);
        $this->assertSame('base64', $claude['messages'][0]['content'][0]['source']['type']);

        $openai = $this->payload(new OpenAIDriver(['api_key' => 'k', 'model' => 'gpt-4o']), $prompt, $options);
        $this->assertSame('data:image/png;base64,AAAA', $openai['messages'][0]['content'][1]['image_url']['url']);

        $gemini = $this->payload(new GeminiDriver(['api_key' => 'k', 'model' => 'gemini-2.5-flash']), $prompt, $options);
        $this->assertSame('image/png', $gemini['contents'][0]['parts'][0]['inline_data']['mime_type']);
    }

    public function test_tool_khong_co_tham_so_gui_properties_dang_object(): void
    {
        // Mang rong cua PHP ma hoa thanh "[]" nhung JSON Schema doi "{}".
        // Gemini tu choi thang: "Cannot bind a list to map for field 'properties'".
        $tool = ToolDefinition::make('ping', 'Kiem tra ket noi');

        $this->assertInstanceOf(\stdClass::class, $tool->schema()['properties']);

        foreach ([
            new ClaudeDriver(['api_key' => 'k', 'model' => 'claude-opus-5']),
            new OpenAIDriver(['api_key' => 'k', 'model' => 'gpt-4o']),
            new GeminiDriver(['api_key' => 'k', 'model' => 'gemini-3.6-flash']),
        ] as $driver) {
            $payload = $this->payload($driver, Prompt::of('Chào'), ChatOptions::make()->tools([$tool]));
            $json = json_encode($payload);

            $this->assertStringContainsString('"properties":{}', $json, $driver->name());
            $this->assertStringNotContainsString('"properties":[]', $json, $driver->name());
        }
    }

    public function test_gemini_quy_effort_ve_thinking_level(): void
    {
        $driver = new GeminiDriver(['api_key' => 'k', 'model' => 'gemini-3.6-flash']);

        // Dòng Gemini 3 mặc định bật suy nghĩ và ăn chung hạn mức maxOutputTokens,
        // không khống chế thì câu trả lời bị cắt cụt.
        $payload = $this->payload($driver, Prompt::of('Chào'), ChatOptions::make()->thinking(false));
        $this->assertSame('minimal', $payload['generationConfig']['thinkingConfig']['thinkingLevel']);

        $payload = $this->payload($driver, Prompt::of('Chào'), ChatOptions::make()->effort('low'));
        $this->assertSame('low', $payload['generationConfig']['thinkingConfig']['thinkingLevel']);

        // Gemini không có mức xhigh/max, quy về high.
        $payload = $this->payload($driver, Prompt::of('Chào'), ChatOptions::make()->effort('max'));
        $this->assertSame('high', $payload['generationConfig']['thinkingConfig']['thinkingLevel']);

        // Không chỉ định thì để nhà cung cấp tự quyết.
        $payload = $this->payload($driver, Prompt::of('Chào'), ChatOptions::make());
        $this->assertArrayNotHasKey('thinkingConfig', $payload['generationConfig'] ?? []);
    }

    public function test_gemini_gui_tra_thought_signature_cua_tool_call(): void
    {
        $driver = new GeminiDriver(['api_key' => 'k', 'model' => 'gemini-3.6-flash']);

        $prompt = Prompt::make()
            ->user('Thời tiết Huế?')
            ->assistant('', [new ToolCall('c1', 'get_weather', ['city' => 'Hue'], ['thought_signature' => 'EtAGCs0G'])])
            ->toolResult('c1', 'get_weather', '30 do');

        $payload = $this->payload($driver, $prompt, ChatOptions::make());

        // Thiếu thoughtSignature là Gemini 3 trả về 400.
        $this->assertSame('EtAGCs0G', $payload['contents'][1]['parts'][0]['thoughtSignature']);
    }

    public function test_gemini_khong_them_thought_signature_khi_khong_co(): void
    {
        $driver = new GeminiDriver(['api_key' => 'k', 'model' => 'gemini-2.5-flash']);

        $prompt = Prompt::make()
            ->user('Thời tiết Huế?')
            ->assistant('', [new ToolCall('c1', 'get_weather', ['city' => 'Hue'])]);

        $payload = $this->payload($driver, $prompt, ChatOptions::make());

        $this->assertArrayNotHasKey('thoughtSignature', $payload['contents'][1]['parts'][0]);
    }

    public function test_options_cua_lan_goi_de_len_mac_dinh_trong_config(): void
    {
        $driver = new ClaudeDriver(['api_key' => 'k', 'model' => 'claude-opus-5', 'max_tokens' => 100]);

        $payload = $this->payload($driver, Prompt::of('Chào'), ChatOptions::make()->maxTokens(999));
        $this->assertSame(999, $payload['max_tokens']);

        // Không truyền thì lấy theo config.
        $payload = $this->payload($driver, Prompt::of('Chào'), ChatOptions::make());
        $this->assertSame(100, $payload['max_tokens']);
    }

    public function test_khong_lam_ban_options_giua_cac_lan_goi(): void
    {
        $driver = new ClaudeDriver(['api_key' => 'k', 'model' => 'claude-opus-5', 'effort' => 'high']);
        $options = ChatOptions::make();

        $this->payload($driver, Prompt::of('Chào'), $options);

        // resolveOptions phải làm việc trên bản sao, không ghi mặc định vào
        // object mà người dùng đang giữ.
        $this->assertNull($options->getEffort());
    }
}
