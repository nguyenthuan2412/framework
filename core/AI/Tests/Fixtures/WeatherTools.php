<?php

namespace Ryra\Core\AI\Tests\Fixtures;

use Ryra\Core\AI\Tool\Attributes\Tool;
use Ryra\Core\AI\Tool\Attributes\ToolParam;

class WeatherTools
{
    public int $invocations = 0;

    #[Tool('Lấy nhiệt độ hiện tại của một thành phố')]
    public function getWeather(
        #[ToolParam('Tên thành phố')]
        string $city,
        #[ToolParam('Đơn vị nhiệt độ', enum: ['c', 'f'])]
        string $unit = 'c',
        ?int $days = null,
    ): string {
        $this->invocations++;

        return sprintf('%s: 30 do %s (%s ngay)', $city, $unit, $days ?? 1);
    }

    #[Tool('Ném lỗi để kiểm tra xử lý ngoại lệ', name: 'broken_tool')]
    public function broken(): string
    {
        throw new \RuntimeException('dịch vụ thời tiết đang lỗi');
    }

    public function notATool(): string
    {
        return 'không được đăng ký';
    }
}
