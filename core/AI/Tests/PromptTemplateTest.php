<?php

namespace Ryra\Core\AI\Tests;

use PHPUnit\Framework\TestCase;
use Ryra\Core\AI\Exceptions\AIException;
use Ryra\Core\AI\Message\Role;
use Ryra\Core\AI\Template\PromptTemplate;
use Ryra\Core\AI\Tests\Fixtures\FakeChatModel;
use Ryra\Core\AI\ChatClient;
use Ryra\Core\AI\Response\ChatResponse;

class PromptTemplateTest extends TestCase
{
    public function test_thay_bien_trong_mau(): void
    {
        $template = PromptTemplate::make('Tóm tắt tour {ten} trong {so_cau} câu.');

        $this->assertSame(
            'Tóm tắt tour Hạ Long trong 3 câu.',
            $template->render(['ten' => 'Hạ Long', 'so_cau' => 3]),
        );
    }

    public function test_bao_loi_khi_thieu_bien(): void
    {
        // Thiếu biến mà vẫn gửi đi thì model nhận được chuỗi "{so_cau}" vô nghĩa,
        // rất khó phát hiện. Chặn ngay từ đầu.
        $this->expectException(AIException::class);
        $this->expectExceptionMessage('Missing prompt variables: so_cau');

        PromptTemplate::make('Tóm tắt {ten} trong {so_cau} câu.')->render(['ten' => 'Hạ Long']);
    }

    public function test_gia_tri_mac_dinh(): void
    {
        $template = PromptTemplate::make('Trả lời bằng {ngon_ngu}.', ['ngon_ngu' => 'tiếng Việt']);

        $this->assertSame('Trả lời bằng tiếng Việt.', $template->render());
        $this->assertSame('Trả lời bằng tiếng Anh.', $template->render(['ngon_ngu' => 'tiếng Anh']));
    }

    public function test_liet_ke_bien_co_trong_mau(): void
    {
        $template = PromptTemplate::make('{a} và {b} và {a}');

        $this->assertSame(['a', 'b'], $template->variables());
    }

    public function test_mang_tuan_tu_thanh_gach_dau_dong(): void
    {
        $rendered = PromptTemplate::make('Các mục:\n{muc}')->render([
            'muc' => ['ăn uống', 'vé tham quan'],
        ]);

        $this->assertStringContainsString('- ăn uống', $rendered);
        $this->assertStringContainsString('- vé tham quan', $rendered);
    }

    public function test_mang_khoa_thanh_json(): void
    {
        $rendered = PromptTemplate::make('{du_lieu}')->render([
            'du_lieu' => ['so_tien' => 500000],
        ]);

        $this->assertStringContainsString('"so_tien": 500000', $rendered);
    }

    public function test_with_khong_lam_thay_doi_mau_goc(): void
    {
        $base = PromptTemplate::make('{a}', ['a' => 'gốc']);
        $derived = $base->with(['a' => 'mới']);

        $this->assertSame('gốc', $base->render());
        $this->assertSame('mới', $derived->render());
    }

    public function test_nap_mau_tu_file(): void
    {
        $path = sys_get_temp_dir() . '/ryra_prompt_' . uniqid() . '.txt';
        file_put_contents($path, 'Xin chào {ten}');

        try {
            $this->assertSame('Xin chào Thuận', PromptTemplate::fromFile($path)->render(['ten' => 'Thuận']));
        } finally {
            unlink($path);
        }
    }

    public function test_bao_loi_khi_thieu_file_mau(): void
    {
        $this->expectException(AIException::class);
        $this->expectExceptionMessage('Prompt template file not found');

        PromptTemplate::fromFile('/khong/co/that.txt');
    }

    public function test_dung_mau_truc_tiep_trong_chat_client(): void
    {
        $model = new FakeChatModel([new ChatResponse('xong')]);

        (new ChatClient($model))
            ->template('Bạn là trợ lý của {cong_ty}.', ['cong_ty' => 'OneGuide'], Role::System)
            ->template('Tóm tắt tour {ma_tour}.', ['ma_tour' => 'T123'])
            ->call();

        $messages = $model->received[0]->messages();
        $this->assertSame('Bạn là trợ lý của OneGuide.', $messages[0]->content);
        $this->assertSame('Tóm tắt tour T123.', $messages[1]->content);
    }
}
