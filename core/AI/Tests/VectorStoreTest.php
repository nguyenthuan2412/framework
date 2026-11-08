<?php

namespace Ryra\Core\AI\Tests;

use PHPUnit\Framework\TestCase;
use Ryra\Core\AI\Exceptions\AIException;
use Ryra\Core\AI\Tests\Fixtures\FakeEmbeddingModel;
use Ryra\Core\AI\VectorStore\Document;
use Ryra\Core\AI\VectorStore\Drivers\FileDriver;
use Ryra\Core\AI\VectorStore\Drivers\MemoryDriver;
use Ryra\Core\AI\VectorStore\SearchQuery;
use Ryra\Core\AI\VectorStore\Similarity;

class VectorStoreTest extends TestCase
{
    protected FakeEmbeddingModel $embeddings;

    protected string $folder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->embeddings = new FakeEmbeddingModel();
        $this->folder = sys_get_temp_dir() . '/ryra_vector_test_' . uniqid();
    }

    protected function tearDown(): void
    {
        if (is_dir($this->folder)) {
            foreach (glob($this->folder . '/*') ?: [] as $file) {
                unlink($file);
            }

            rmdir($this->folder);
        }

        parent::tearDown();
    }

    public function test_cosine_dung_cong_thuc_chuan(): void
    {
        // Dùng delta vì phép chia số thực có sai số ở chữ số cuối.
        $this->assertEqualsWithDelta(1.0, Similarity::cosine([1, 0], [1, 0]), 1e-9);
        $this->assertEqualsWithDelta(0.0, Similarity::cosine([1, 0], [0, 1]), 1e-9);
        $this->assertEqualsWithDelta(-1.0, Similarity::cosine([1, 0], [-1, 0]), 1e-9);

        // Cosine bỏ qua độ dài vector, chỉ quan tâm hướng.
        $this->assertEqualsWithDelta(1.0, Similarity::cosine([1, 1], [5, 5]), 1e-9);

        // Vector rỗng (tài liệu chưa nhúng được) không được gây chia cho 0.
        $this->assertSame(0.0, Similarity::cosine([0, 0], [0, 0]));
    }

    public function test_bao_loi_khi_so_chieu_lech_nhau(): void
    {
        $this->expectException(AIException::class);
        $this->expectExceptionMessage('Vector dimensions do not match: 2 vs 3');

        Similarity::cosine([1, 2], [1, 2, 3]);
    }

    public function test_tim_tai_lieu_gan_nhat_theo_ngu_nghia(): void
    {
        $store = new MemoryDriver($this->embeddings);

        $store->add([
            Document::make('tour tour guide', ['loai' => 'tour'], 'doc-tour'),
            Document::make('chi phi chi phi', ['loai' => 'chi_phi'], 'doc-chi-phi'),
            Document::make('khach san khach san', ['loai' => 'luu_tru'], 'doc-khach-san'),
        ]);

        $results = $store->search(SearchQuery::make('chi phi')->topK(2));

        $this->assertCount(2, $results);
        $this->assertSame('doc-chi-phi', $results[0]->id);
        // Kết quả phải xếp theo điểm giảm dần.
        $this->assertGreaterThanOrEqual($results[1]->score, $results[0]->score);
    }

    public function test_nhung_ca_lo_trong_mot_lan_goi(): void
    {
        $store = new MemoryDriver($this->embeddings);

        $store->add([
            Document::make('tour'),
            Document::make('guide'),
            Document::make('chi phi'),
        ]);

        // Ba tài liệu nhưng chỉ được gọi API một lần.
        $this->assertSame(1, $this->embeddings->calls);
    }

    public function test_khong_nhung_lai_tai_lieu_da_co_vector(): void
    {
        $store = new MemoryDriver($this->embeddings);

        $store->add(Document::make('tour')->withEmbedding([1, 0, 0, 0, 0, 0]));

        $this->assertSame(0, $this->embeddings->calls);
    }

    public function test_loc_theo_metadata(): void
    {
        $store = new MemoryDriver($this->embeddings);

        $store->add([
            Document::make('chi phi', ['tour_id' => 1], 'a'),
            Document::make('chi phi', ['tour_id' => 2], 'b'),
        ]);

        $results = $store->search(SearchQuery::make('chi phi')->filter(['tour_id' => 2]));

        $this->assertCount(1, $results);
        $this->assertSame('b', $results[0]->id);
    }

    public function test_loc_theo_danh_sach_gia_tri(): void
    {
        $store = new MemoryDriver($this->embeddings);

        $store->add([
            Document::make('tour', ['trang_thai' => 'moi'], 'a'),
            Document::make('tour', ['trang_thai' => 'huy'], 'b'),
            Document::make('tour', ['trang_thai' => 'xong'], 'c'),
        ]);

        $results = $store->search(SearchQuery::make('tour')->filter(['trang_thai' => ['moi', 'xong']])->topK(10));

        $this->assertCount(2, $results);
    }

    public function test_nguong_diem_loai_bo_ket_qua_khong_lien_quan(): void
    {
        $store = new MemoryDriver($this->embeddings);

        $store->add([
            Document::make('chi phi', [], 'lien-quan'),
            Document::make('khach san', [], 'khong-lien-quan'),
        ]);

        $results = $store->search(SearchQuery::make('chi phi')->threshold(0.5)->topK(10));

        $this->assertCount(1, $results);
        $this->assertSame('lien-quan', $results[0]->id);
    }

    public function test_ghi_de_theo_id_va_xoa(): void
    {
        $store = new MemoryDriver($this->embeddings);

        $store->add(Document::make('tour', [], 'trung-id'));
        $store->add(Document::make('tour guide', ['v' => 2], 'trung-id'));

        $this->assertSame(1, $store->count());
        $this->assertSame(2, $store->search(SearchQuery::make('tour'))[0]->metadata('v'));

        $store->delete(['trung-id']);
        $this->assertSame(0, $store->count());
    }

    public function test_tach_biet_theo_collection(): void
    {
        $store = new MemoryDriver($this->embeddings);

        $store->add(Document::make('tour', [], 'a'), 'tour_2024');
        $store->add(Document::make('tour', [], 'b'), 'tour_2025');

        $results = $store->search(SearchQuery::make('tour')->collection('tour_2025')->topK(10));

        $this->assertCount(1, $results);
        $this->assertSame('b', $results[0]->id);
    }

    public function test_file_driver_luu_va_doc_lai_duoc(): void
    {
        $store = new FileDriver($this->embeddings, ['path' => $this->folder, 'collection' => 'tour']);

        $store->add([
            Document::make('chi phi an uong', ['muc' => 'an_uong'], 'doc-1'),
            Document::make('khach san ha long', ['muc' => 'luu_tru'], 'doc-2'),
        ]);

        // Instance mới đọc lại từ đĩa, dữ liệu phải còn nguyên.
        $reopened = new FileDriver($this->embeddings, ['path' => $this->folder, 'collection' => 'tour']);
        $results = $reopened->search(SearchQuery::make('chi phi')->topK(1));

        $this->assertCount(1, $results);
        $this->assertSame('doc-1', $results[0]->id);
        $this->assertSame('an_uong', $results[0]->metadata('muc'));

        $reopened->delete(['doc-1']);
        $this->assertCount(0, $reopened->search(SearchQuery::make('chi phi')->threshold(0.9)));
    }

    public function test_ket_qua_khong_kem_theo_vector_cho_nhe(): void
    {
        $store = new MemoryDriver($this->embeddings);
        $store->add(Document::make('tour', [], 'a'));

        $this->assertFalse($store->search(SearchQuery::make('tour'))[0]->hasEmbedding());
    }

    public function test_bat_buoc_co_text_hoac_vector(): void
    {
        $this->expectException(AIException::class);
        $this->expectExceptionMessage('cần có text hoặc vector');

        (new MemoryDriver($this->embeddings))->search(SearchQuery::make());
    }
}
