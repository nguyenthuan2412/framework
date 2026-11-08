<?php

/**
 * Bộ kiểm thử kho vector trên hạ tầng thật.
 *
 * Chạy cùng một bộ kiểm tra cho mọi driver để vừa xác nhận từng driver hoạt
 * động, vừa lộ ra chỗ các driver hành xử khác nhau - điều mà test dùng đồ giả
 * không bao giờ thấy được.
 *
 * Dùng embedding giả nên không cần API key.
 *
 * Chạy:
 *   docker run --rm --network ai-test -v "$PWD":/var/www/html -w /var/www/html \
 *       oneguide-backend php core/AI/Tests/Live/vector.php
 */

$base = dirname(__DIR__, 4);

require "{$base}/vendor/autoload.php";
require "{$base}/core/Collection/functions.php";

use Ryra\Core\AI\Tests\Fixtures\FakeEmbeddingModel;
use Ryra\Core\AI\VectorStore\Document;
use Ryra\Core\AI\VectorStore\Drivers\DatabaseDriver;
use Ryra\Core\AI\VectorStore\Drivers\ElasticsearchDriver;
use Ryra\Core\AI\VectorStore\Drivers\FileDriver;
use Ryra\Core\AI\VectorStore\Drivers\MemoryDriver;
use Ryra\Core\AI\VectorStore\Drivers\QdrantDriver;
use Ryra\Core\AI\VectorStore\SearchQuery;
use Ryra\Core\Contracts\AI\VectorStore;
use Ryra\Core\Database\Connection;
use Swoole\Coroutine;

Coroutine::set(['hook_flags' => SWOOLE_HOOK_ALL]);

final class Report
{
    public int $passed = 0;

    public int $failed = 0;

    public int $skipped = 0;

    public function section(string $name): void
    {
        printf("\n\033[1m%s\033[0m\n", $name);
    }

    public function check(string $label, bool $ok, string $detail = ''): void
    {
        $ok ? $this->passed++ : $this->failed++;

        printf(
            "  %s %s%s\n",
            $ok ? "\033[32mOK  \033[0m" : "\033[31mFAIL\033[0m",
            $label,
            $detail === '' ? '' : "  \033[90m{$detail}\033[0m",
        );
    }

    public function skip(string $label, string $reason): void
    {
        $this->skipped++;
        printf("  \033[33mSKIP\033[0m %s  \033[90m%s\033[0m\n", $label, $reason);
    }

    public function fail(string $label, Throwable $e): void
    {
        $this->failed++;
        printf("  \033[31mFAIL\033[0m %s\n       \033[90m%s\033[0m\n", $label, $e->getMessage());
    }

    public function summary(): int
    {
        printf("\n\033[1mTong: %d dat, %d hong, %d bo qua\033[0m\n", $this->passed, $this->failed, $this->skipped);

        return $this->failed === 0 ? 0 : 1;
    }
}

$report = new Report();

Coroutine\run(function () use ($report): void {
    $embeddings = new FakeEmbeddingModel();

    $stores = [
        'memory' => fn (): VectorStore => new MemoryDriver($embeddings),
        'file' => fn (): VectorStore => new FileDriver($embeddings, [
            'path' => sys_get_temp_dir() . '/ryra_vector_live_' . uniqid(),
        ]),
        'qdrant' => fn (): VectorStore => new QdrantDriver($embeddings, [
            'host' => getenv('QDRANT_HOST') ?: 'http://ai-qdrant:6333',
            'collection_prefix' => 'live_' . substr(md5((string) getmypid()), 0, 6) . '_',
        ]),
        'database' => function () use ($embeddings): VectorStore {
            $connection = mysqlConnection();
            createVectorTable($connection);

            return new DatabaseDriver($embeddings, $connection, ['table' => 'ai_vector_documents']);
        },
        'elasticsearch' => fn (): VectorStore => new ElasticsearchDriver(
            $embeddings,
            \Elastic\Elasticsearch\ClientBuilder::create()
                ->setHosts([getenv('ELASTIC_HOST') ?: 'http://ai-es:9200'])
                ->build(),
            ['index_prefix' => 'live_' . substr(md5((string) getmypid()), 0, 6)],
        ),
    ];

    foreach ($stores as $name => $factory) {
        $report->section(strtoupper($name));

        try {
            $store = $factory();

            // Thăm dò bằng search chứ không phải clear: nhiều driver cố tình
            // nuốt lỗi trong clear() nên dịch vụ chết vẫn lọt qua.
            $store->search(SearchQuery::make('smoke')->collection('smoke'));
        } catch (Throwable $e) {
            $report->skip('tat ca', mb_strimwidth($e->getMessage(), 0, 90, '...'));
            continue;
        }

        conformance($report, $store);
    }
});

exit($report->summary());

function mysqlConnection(): Connection
{
    return new Connection([
        'driver' => 'mysql',
        'host' => getenv('MYSQL_HOST') ?: 'ai-mysql',
        'port' => 3306,
        'database' => getenv('MYSQL_DATABASE') ?: 'aitest',
        'username' => getenv('MYSQL_USERNAME') ?: 'root',
        'password' => getenv('MYSQL_PASSWORD') ?: 'secret',
        'prefix' => '',
        'options' => [PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC],
    ]);
}

/**
 * Dựng bảng đúng như hướng dẫn ghi trong DatabaseDriver, để kiểm luôn xem
 * hướng dẫn đó có chạy được thật không.
 */
function createVectorTable(Connection $connection): void
{
    $connection->statement('DROP TABLE IF EXISTS ai_vector_documents');
    $connection->statement(
        'CREATE TABLE ai_vector_documents (
            id          VARCHAR(64)  NOT NULL PRIMARY KEY,
            collection  VARCHAR(120) NOT NULL,
            content     LONGTEXT     NOT NULL,
            metadata    JSON         NULL,
            embedding   JSON         NOT NULL,
            created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_ai_vector_collection (collection)
        )'
    );
}

/**
 * Bộ kiểm tra chung mà mọi driver đều phải qua.
 */
function conformance(Report $report, VectorStore $store): void
{
    // Từ vựng của FakeEmbeddingModel: tour guide chi phi khach san.
    // Tài liệu nào cũng phải chứa ít nhất một từ, vector toàn 0 sẽ bị
    // Qdrant/Elasticsearch từ chối vì không tính được cosine.
    $collection = 'conformance';

    try {
        $store->clear($collection);

        $store->add([
            new Document('doc-chi-phi', 'chi phi chi phi an uong', ['loai' => 'chi_phi', 'tour' => 'T1']),
            new Document('doc-tour', 'tour tour lich trinh', ['loai' => 'lich_trinh', 'tour' => 'T1']),
            new Document('doc-khach-san', 'khach san khach san', ['loai' => 'luu_tru', 'tour' => 'T2']),
        ], $collection);

        $report->check('add khong nem loi', true);
    } catch (Throwable $e) {
        $report->fail('add', $e);

        return;
    }

    // 1. Tìm đúng tài liệu gần nhất về ngữ nghĩa.
    try {
        $results = $store->search(SearchQuery::make('chi phi')->collection($collection)->topK(3));

        $report->check(
            'tim dung tai lieu gan nhat',
            ($results[0]->id ?? null) === 'doc-chi-phi',
            sprintf('top1=%s', $results[0]->id ?? 'khong co'),
        );

        $report->check(
            'sap xep theo diem giam dan',
            count($results) < 2 || $results[0]->score >= $results[1]->score,
            sprintf('%s', implode(' > ', array_map(
                static fn (Document $d): string => sprintf('%s(%.2f)', $d->id, $d->score ?? 0),
                $results,
            ))),
        );

        $report->check(
            'ket qua co noi dung va metadata',
            ($results[0]->content ?? '') !== '' && ($results[0]->metadata('loai') ?? null) === 'chi_phi',
            sprintf('loai=%s', $results[0]->metadata('loai') ?? 'thieu'),
        );
    } catch (Throwable $e) {
        $report->fail('search', $e);
    }

    // 2. topK giới hạn số lượng trả về.
    try {
        $results = $store->search(SearchQuery::make('tour')->collection($collection)->topK(1));
        $report->check('topK gioi han so luong', count($results) === 1, sprintf('%d ket qua', count($results)));
    } catch (Throwable $e) {
        $report->fail('topK', $e);
    }

    // 3. Lọc theo metadata dạng giá trị đơn.
    try {
        $results = $store->search(
            SearchQuery::make('chi phi')->collection($collection)->topK(10)->filter(['tour' => 'T2']),
        );

        $report->check(
            'loc metadata gia tri don',
            count($results) === 1 && $results[0]->id === 'doc-khach-san',
            sprintf('%d ket qua', count($results)),
        );
    } catch (Throwable $e) {
        $report->fail('loc metadata', $e);
    }

    // 4. Lọc theo danh sách giá trị.
    try {
        $results = $store->search(
            SearchQuery::make('tour')->collection($collection)->topK(10)->filter(['loai' => ['chi_phi', 'luu_tru']]),
        );

        $report->check('loc metadata dang danh sach', count($results) === 2, sprintf('%d ket qua', count($results)));
    } catch (Throwable $e) {
        $report->fail('loc danh sach', $e);
    }

    // 5. Ngưỡng điểm loại kết quả không liên quan.
    try {
        $results = $store->search(
            SearchQuery::make('khach san')->collection($collection)->topK(10)->threshold(0.9),
        );

        $report->check(
            'nguong diem loai ket qua kem',
            count($results) === 1 && $results[0]->id === 'doc-khach-san',
            sprintf('%d ket qua', count($results)),
        );
    } catch (Throwable $e) {
        $report->fail('nguong diem', $e);
    }

    // 6. Ghi đè theo id thay vì tạo bản ghi mới.
    try {
        $store->add(
            new Document('doc-tour', 'tour tour da cap nhat', ['loai' => 'lich_trinh', 'tour' => 'T1', 'v' => 2]),
            $collection,
        );

        $results = $store->search(SearchQuery::make('tour')->collection($collection)->topK(10));
        $matches = array_values(array_filter(
            $results,
            static fn (Document $d): bool => $d->id === 'doc-tour',
        ));

        $report->check(
            'ghi de theo id',
            count($matches) === 1 && str_contains($matches[0]->content, 'da cap nhat'),
            sprintf('%d ban ghi cung id', count($matches)),
        );
    } catch (Throwable $e) {
        $report->fail('ghi de theo id', $e);
    }

    // 7. Tách biệt giữa các collection.
    try {
        $store->clear('khac');
        $store->add(new Document('rieng', 'tour tour rieng biet'), 'khac');

        $results = $store->search(SearchQuery::make('tour')->collection('khac')->topK(10));

        $report->check(
            'tach biet collection',
            count($results) === 1 && $results[0]->id === 'rieng',
            sprintf('%d ket qua', count($results)),
        );
    } catch (Throwable $e) {
        $report->fail('tach biet collection', $e);
    }

    // 8. Xoá theo id.
    try {
        $store->delete(['doc-khach-san'], $collection);

        $results = $store->search(SearchQuery::make('khach san')->collection($collection)->topK(10));
        $ids = array_map(static fn (Document $d): string => $d->id, $results);

        $report->check('xoa theo id', !in_array('doc-khach-san', $ids, true), implode(', ', $ids) ?: 'rong');
    } catch (Throwable $e) {
        $report->fail('xoa theo id', $e);
    }

    // 9. Xoá sạch collection.
    try {
        $store->clear($collection);

        $results = $store->search(SearchQuery::make('tour')->collection($collection)->topK(10));

        $report->check('xoa sach collection', $results === [], sprintf('%d ket qua con lai', count($results)));
    } catch (Throwable $e) {
        $report->fail('xoa sach collection', $e);
    }

    // Dọn dẹp.
    try {
        $store->clear('khac');
    } catch (Throwable) {
        // Không quan trọng nếu dọn không được.
    }
}
