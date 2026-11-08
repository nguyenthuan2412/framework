<?php

namespace Ryra\Core\AI\Tests;

use PHPUnit\Framework\TestCase;
use Ryra\Core\AI\Response\ToolCall;
use Ryra\Core\AI\Tests\Fixtures\WeatherTools;
use Ryra\Core\AI\Tool\Toolbox;
use Ryra\Core\AI\Tool\ToolFactory;

class ToolFactoryTest extends TestCase
{
    public function test_dung_schema_tu_attribute_va_kieu_du_lieu_php(): void
    {
        $tools = ToolFactory::fromObject(new WeatherTools());

        $this->assertCount(2, $tools);

        $weather = $tools[0];
        $this->assertSame('get_weather', $weather->name);
        $this->assertSame('Lấy nhiệt độ hiện tại của một thành phố', $weather->description);

        $schema = $weather->schema();
        $this->assertSame('string', $schema['properties']['city']['type']);
        $this->assertSame('Tên thành phố', $schema['properties']['city']['description']);
        $this->assertSame(['c', 'f'], $schema['properties']['unit']['enum']);
        $this->assertSame('integer', $schema['properties']['days']['type']);

        // city bắt buộc; unit có mặc định, days nullable nên đều không bắt buộc.
        $this->assertSame(['city'], $schema['required']);
        $this->assertFalse($schema['additionalProperties']);
    }

    public function test_doi_ten_tool_qua_attribute(): void
    {
        $names = array_map(
            static fn ($tool): string => $tool->name,
            ToolFactory::fromObject(new WeatherTools()),
        );

        $this->assertContains('broken_tool', $names);
        $this->assertNotContains('not_a_tool', $names);
    }

    public function test_toolbox_thuc_thi_va_dung_gia_tri_mac_dinh(): void
    {
        $tools = new WeatherTools();
        $toolbox = new Toolbox([$tools]);

        $result = $toolbox->execute(new ToolCall('call_1', 'get_weather', ['city' => 'Hue']));

        $this->assertFalse($result->isError);
        $this->assertSame('Hue: 30 do c (1 ngay)', $result->content);
        $this->assertSame(1, $tools->invocations);
    }

    public function test_goi_tool_khong_ton_tai_tra_ve_loi_thay_vi_crash(): void
    {
        $toolbox = new Toolbox([new WeatherTools()]);

        $result = $toolbox->execute(new ToolCall('call_1', 'khong_co_that', []));

        $this->assertTrue($result->isError);
        $this->assertStringContainsString('is not registered', $result->content);
    }

    public function test_ep_kieu_tham_so_tu_model_tra_ve(): void
    {
        $toolbox = new Toolbox([new WeatherTools()]);

        // Model hay trả số dưới dạng chuỗi, phải ép về đúng kiểu khai báo.
        $result = $toolbox->execute(new ToolCall('call_1', 'get_weather', [
            'city' => 'Hoi An',
            'unit' => 'f',
            'days' => '3',
        ]));

        $this->assertSame('Hoi An: 30 do f (3 ngay)', $result->content);
    }
}
