<?php

namespace Ryra\Core\AI\Tests;

use PHPUnit\Framework\TestCase;
use Ryra\Core\AI\Document\Splitter\CharacterTextSplitter;
use Ryra\Core\AI\Document\Splitter\TokenTextSplitter;
use Ryra\Core\AI\Exceptions\AIException;
use Ryra\Core\AI\VectorStore\Document;

class SplitterTest extends TestCase
{
    public function test_khong_cat_khi_van_ban_du_ngan(): void
    {
        $splitter = new CharacterTextSplitter(chunkSize: 100, chunkOverlap: 0);

        $this->assertSame(['Đoạn ngắn.'], $splitter->splitText('Đoạn ngắn.'));
    }

    public function test_uu_tien_cat_theo_doan_van(): void
    {
        $splitter = new CharacterTextSplitter(chunkSize: 30, chunkOverlap: 0);

        $chunks = $splitter->splitText("Đoạn một ở đây.\n\nĐoạn hai ở đây.\n\nĐoạn ba ở đây.");

        // Ranh giới đoạn văn được tôn trọng, không cắt ngang câu.
        foreach ($chunks as $chunk) {
            $this->assertStringEndsWith('.', trim($chunk));
        }
    }

    public function test_moi_doan_khong_vuot_qua_chunk_size(): void
    {
        $text = str_repeat('Hướng dẫn viên báo cáo chi phí phát sinh trong ngày. ', 50);
        $splitter = new CharacterTextSplitter(chunkSize: 120, chunkOverlap: 20);

        $chunks = $splitter->splitText($text);

        $this->assertGreaterThan(1, count($chunks));

        foreach ($chunks as $chunk) {
            $this->assertLessThanOrEqual(120, mb_strlen($chunk), "Đoạn quá dài: {$chunk}");
        }
    }

    public function test_khong_lam_mat_chu_khi_cat(): void
    {
        $text = str_repeat('một hai ba bốn năm sáu bảy tám chín mười ', 20);
        $splitter = new CharacterTextSplitter(chunkSize: 80, chunkOverlap: 0);

        $chunks = $splitter->splitText($text);

        // Không overlap thì ghép lại phải ra đúng số từ ban đầu.
        $original = preg_split('/\s+/', trim($text));
        $rebuilt = preg_split('/\s+/', trim(implode(' ', $chunks)));

        $this->assertSame(count($original), count($rebuilt));
    }

    public function test_cac_doan_lien_nhau_co_phan_chong_lap(): void
    {
        $text = str_repeat('alpha beta gamma delta epsilon ', 20);
        $splitter = new CharacterTextSplitter(chunkSize: 100, chunkOverlap: 40);

        $chunks = $splitter->splitText($text);
        $this->assertGreaterThan(2, count($chunks));

        // Đuôi đoạn trước phải xuất hiện lại ở đầu đoạn sau.
        $tailWords = array_slice(preg_split('/\s+/', trim($chunks[0])), -2);
        $this->assertStringContainsString(implode(' ', $tailWords), $chunks[1]);
    }

    public function test_cat_duoc_van_ban_khong_co_dau_cach(): void
    {
        // Trường hợp xấu nhất: không tách được theo từ, phải hạ xuống ký tự.
        $splitter = new CharacterTextSplitter(chunkSize: 10, chunkOverlap: 0);

        $chunks = $splitter->splitText(str_repeat('a', 55));

        $this->assertSame(6, count($chunks));
        $this->assertSame(55, mb_strlen(implode('', $chunks)));
    }

    public function test_dem_dung_do_dai_tieng_viet_co_dau(): void
    {
        $splitter = new CharacterTextSplitter(chunkSize: 20, chunkOverlap: 0);

        foreach ($splitter->splitText(str_repeat('nguyễn hoàng phượng ', 10)) as $chunk) {
            // mb_strlen chứ không phải strlen: chữ có dấu chiếm nhiều byte hơn.
            $this->assertLessThanOrEqual(20, mb_strlen($chunk));
        }
    }

    public function test_transform_giu_metadata_va_danh_so_doan(): void
    {
        $splitter = new CharacterTextSplitter(chunkSize: 50, chunkOverlap: 0);

        $chunks = $splitter->transform([
            new Document('lich-trinh', str_repeat('Ngày một đón khách tại sân bay. ', 10), ['tour_id' => 'T123']),
        ]);

        $this->assertGreaterThan(1, count($chunks));
        $this->assertSame('T123', $chunks[0]->metadata('tour_id'));
        $this->assertSame('lich-trinh', $chunks[0]->metadata('parent_id'));
        $this->assertSame(0, $chunks[0]->metadata('chunk_index'));
        $this->assertSame(count($chunks), $chunks[0]->metadata('chunk_total'));
        $this->assertSame('lich-trinh#1', $chunks[1]->id);
    }

    public function test_bo_qua_van_ban_rong(): void
    {
        $splitter = new CharacterTextSplitter();

        $this->assertSame([], $splitter->splitText('   '));
        $this->assertSame([], $splitter->transform([new Document('a', '')]));
    }

    public function test_token_splitter_dem_theo_token_uoc_luong(): void
    {
        $splitter = new TokenTextSplitter(chunkTokens: 20, overlapTokens: 0, charsPerToken: 4.0);

        $chunks = $splitter->splitText(str_repeat('báo cáo chi phí tour ', 30));

        foreach ($chunks as $chunk) {
            // 20 token x 4 ký tự = khoảng 80 ký tự.
            $this->assertLessThanOrEqual(80, mb_strlen($chunk));
        }
    }

    public function test_bao_loi_khi_cau_hinh_vo_ly(): void
    {
        $this->expectException(AIException::class);
        $this->expectExceptionMessage('chunkOverlap');

        new CharacterTextSplitter(chunkSize: 100, chunkOverlap: 100);
    }
}
