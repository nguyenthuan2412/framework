<?php

namespace Ryra\Core\AI\Tests;

use PHPUnit\Framework\TestCase;
use Ryra\Core\AI\Document\Ingestor;
use Ryra\Core\AI\Document\Reader\CsvReader;
use Ryra\Core\AI\Document\Reader\DirectoryReader;
use Ryra\Core\AI\Document\Reader\HtmlReader;
use Ryra\Core\AI\Document\Reader\JsonReader;
use Ryra\Core\AI\Document\Reader\MarkdownReader;
use Ryra\Core\AI\Document\Reader\TextReader;
use Ryra\Core\AI\Document\Splitter\CharacterTextSplitter;
use Ryra\Core\AI\Exceptions\AIException;
use Ryra\Core\AI\Tests\Fixtures\FakeEmbeddingModel;
use Ryra\Core\AI\VectorStore\Drivers\MemoryDriver;
use Ryra\Core\AI\VectorStore\SearchQuery;

class DocumentReaderTest extends TestCase
{
    protected string $folder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->folder = sys_get_temp_dir() . '/ryra_docs_' . uniqid();
        mkdir($this->folder, 0755, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->folder . '/*') ?: [] as $file) {
            unlink($file);
        }

        rmdir($this->folder);

        parent::tearDown();
    }

    protected function write(string $name, string $content): string
    {
        $path = $this->folder . '/' . $name;
        file_put_contents($path, $content);

        return $path;
    }

    public function test_doc_file_van_ban(): void
    {
        $path = $this->write('note.txt', 'Ghi chú tour Hạ Long.');

        $documents = (new TextReader($path, ['tour_id' => 'T123']))->read();

        $this->assertCount(1, $documents);
        $this->assertSame('Ghi chú tour Hạ Long.', $documents[0]->content);
        $this->assertSame('T123', $documents[0]->metadata('tour_id'));
        $this->assertSame('note.txt', $documents[0]->metadata('file_name'));
    }

    public function test_bao_loi_ro_khi_thieu_file(): void
    {
        $this->expectException(AIException::class);
        $this->expectExceptionMessage('Document file not found');

        (new TextReader($this->folder . '/khong-co.txt'))->read();
    }

    public function test_markdown_tach_theo_tieu_de(): void
    {
        $path = $this->write('lich-trinh.md', <<<'MD'
        # Tour Hạ Long

        ## Ngày 1
        Đón khách tại sân bay Nội Bài.

        ## Ngày 2
        Tham quan vịnh Hạ Long.
        MD);

        $documents = (new MarkdownReader($path))->read();

        $this->assertCount(3, $documents);
        $this->assertSame('Ngày 1', $documents[1]->metadata('heading'));
        $this->assertStringContainsString('Nội Bài', $documents[1]->content);
        // Giữ lại dòng tiêu đề trong nội dung để model biết đang nói về mục nào.
        $this->assertStringContainsString('## Ngày 2', $documents[2]->content);
    }

    public function test_markdown_khong_tach_khi_tat(): void
    {
        $path = $this->write('a.md', "# A\n\n## B\nnội dung");

        $this->assertCount(1, (new MarkdownReader($path, splitLevel: 0))->read());
    }

    public function test_html_bo_script_va_style(): void
    {
        $path = $this->write('page.html', <<<'HTML'
        <html><head><title>Hạ Long</title><style>body{color:red}</style></head>
        <body><script>alert(1)</script><p>Vịnh Hạ Long là di sản.</p></body></html>
        HTML);

        $documents = (new HtmlReader($path))->read();

        $this->assertStringContainsString('Vịnh Hạ Long là di sản.', $documents[0]->content);
        $this->assertStringNotContainsString('alert', $documents[0]->content);
        $this->assertStringNotContainsString('color:red', $documents[0]->content);
        $this->assertSame('Hạ Long', $documents[0]->metadata('title'));
    }

    public function test_csv_moi_dong_mot_tai_lieu(): void
    {
        $path = $this->write('chi-phi.csv', "muc,so_tien,tour_id\nĂn trưa,500000,T123\nVé tham quan,250000,T124\n");

        $documents = (new CsvReader($path, metadataColumns: ['tour_id']))->read();

        $this->assertCount(2, $documents);
        // Trình bày "cột: giá trị" để model hiểu ngữ cảnh.
        $this->assertStringContainsString('muc: Ăn trưa', $documents[0]->content);
        $this->assertStringContainsString('so_tien: 500000', $documents[0]->content);
        $this->assertSame('T123', $documents[0]->metadata('tour_id'));
        $this->assertSame(1, $documents[0]->metadata('row'));
    }

    public function test_csv_chiu_duoc_dong_thieu_cot(): void
    {
        $path = $this->write('lech.csv', "a,b,c\n1,2\n1,2,3,4\n");

        $documents = (new CsvReader($path))->read();

        $this->assertCount(2, $documents);
        $this->assertStringContainsString('a: 1', $documents[0]->content);
    }

    public function test_json_doc_mang_va_theo_pointer(): void
    {
        $path = $this->write('tours.json', json_encode([
            'data' => [
                'items' => [
                    ['ten' => 'Hạ Long', 'gia' => 3000000],
                    ['ten' => 'Sa Pa', 'gia' => 2500000],
                ],
            ],
        ]));

        $documents = (new JsonReader($path, pointer: 'data.items', metadataKeys: ['ten']))->read();

        $this->assertCount(2, $documents);
        $this->assertStringContainsString('ten: Hạ Long', $documents[0]->content);
        $this->assertSame('Sa Pa', $documents[1]->metadata('ten'));
    }

    public function test_json_bao_loi_khi_pointer_sai(): void
    {
        $path = $this->write('a.json', '{"a":1}');

        $this->expectException(AIException::class);
        $this->expectExceptionMessage('JSON pointer "b.c" not found');

        (new JsonReader($path, pointer: 'b.c'))->read();
    }

    public function test_json_bao_loi_khi_file_hong(): void
    {
        $path = $this->write('hong.json', '{khong phai json}');

        $this->expectException(AIException::class);
        $this->expectExceptionMessage('Invalid JSON');

        (new JsonReader($path))->read();
    }

    public function test_directory_reader_chon_dung_reader_theo_duoi_file(): void
    {
        $this->write('a.txt', 'văn bản thuần');
        $this->write('b.md', "## Mục\nnội dung markdown");
        $this->write('c.csv', "cot\ngia tri");
        $this->write('d.xyz', 'định dạng không hỗ trợ');

        $documents = (new DirectoryReader($this->folder))->read();

        $sources = array_map(static fn ($doc): string => $doc->metadata('file_name'), $documents);

        $this->assertContains('a.txt', $sources);
        $this->assertContains('b.md', $sources);
        $this->assertContains('c.csv', $sources);
        // File lạ được bỏ qua chứ không làm hỏng cả lượt quét.
        $this->assertNotContains('d.xyz', $sources);
    }

    public function test_directory_reader_loc_theo_duoi_file(): void
    {
        $this->write('a.txt', 'một');
        $this->write('b.md', 'hai');

        $documents = (new DirectoryReader($this->folder, extensions: ['txt']))->read();

        $this->assertCount(1, $documents);
    }

    public function test_ingestor_chay_ca_day_chuyen(): void
    {
        $this->write('lich-trinh.txt', str_repeat('Ngày một đón khách tại sân bay Nội Bài. ', 20));

        $store = new MemoryDriver(new FakeEmbeddingModel());

        $written = (new Ingestor($store))
            ->read(new DirectoryReader($this->folder))
            ->split(chunkSize: 100, chunkOverlap: 20)
            ->into('lich_trinh')
            ->run();

        $this->assertGreaterThan(1, $written);
        $this->assertSame($written, $store->count('lich_trinh'));

        $results = $store->search(SearchQuery::make('đón khách')->collection('lich_trinh'));
        $this->assertNotEmpty($results);
    }

    public function test_ingestor_xem_truoc_khong_ghi_vao_kho(): void
    {
        $store = new MemoryDriver(new FakeEmbeddingModel());

        $chunks = (new Ingestor($store))
            ->add(new \Ryra\Core\AI\VectorStore\Document('a', str_repeat('nội dung dài. ', 30)))
            ->transform(new CharacterTextSplitter(chunkSize: 60, chunkOverlap: 0))
            ->collect();

        $this->assertGreaterThan(1, count($chunks));
        // collect() chỉ xem trước, chưa tốn tiền gọi API nhúng.
        $this->assertSame(0, $store->count());
    }

    public function test_ingestor_chia_lo_khi_ghi(): void
    {
        $store = new MemoryDriver($embeddings = new FakeEmbeddingModel());

        $documents = [];
        for ($i = 0; $i < 25; $i++) {
            $documents[] = new \Ryra\Core\AI\VectorStore\Document("doc-{$i}", "nội dung {$i}");
        }

        (new Ingestor($store))->add($documents)->batchSize(10)->run();

        // 25 tài liệu, mỗi lô 10 -> 3 lần gọi API nhúng.
        $this->assertSame(3, $embeddings->calls);
        $this->assertSame(25, $store->count());
    }
}
