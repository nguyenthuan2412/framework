<?php

namespace Ryra\Core\AI\VectorStore;

use Closure;
use Elastic\Elasticsearch\ClientBuilder;
use Ryra\Core\AI\Exceptions\AIException;
use Ryra\Core\AI\VectorStore\Drivers\DatabaseDriver;
use Ryra\Core\AI\VectorStore\Drivers\ElasticsearchDriver;
use Ryra\Core\AI\VectorStore\Drivers\FileDriver;
use Ryra\Core\AI\VectorStore\Drivers\MemoryDriver;
use Ryra\Core\AI\VectorStore\Drivers\PgVectorDriver;
use Ryra\Core\AI\VectorStore\Drivers\QdrantDriver;
use Ryra\Core\Contracts\AI\EmbeddingModel;
use Ryra\Core\Contracts\AI\VectorStore;
use Ryra\Core\Contracts\Config\Repository;
use Ryra\Core\Contracts\Database\Connection;
use Ryra\Core\Contracts\Container\Container;
use Ryra\Core\Support\Manager;

/**
 * @method void                   add(array|Document $documents, ?string $collection = null)
 * @method void                   delete(array $ids, ?string $collection = null)
 * @method array<int, Document>   search(SearchQuery $query)
 * @method void                   clear(?string $collection = null)
 */
class VectorStoreManager extends Manager
{
    protected Repository $config;

    /**
     * @var array<string, Closure>
     */
    protected array $customCreators = [];

    /**
     * @var array<string, VectorStore>
     */
    protected array $resolved = [];

    public function __construct(protected Container $container)
    {
        $this->config = $this->container->get('config');
    }

    public function getDefaultDriver(): string
    {
        return (string) $this->config->get('ai.vector_store.default', 'memory');
    }

    public function driver(?string $driver = null, array $params = []): mixed
    {
        $driver ??= $this->getDefaultDriver();

        return $this->resolved[$driver] ??= $this->resolve($driver);
    }

    public function extend(string $driver, Closure $creator): static
    {
        $this->customCreators[$driver] = $creator;
        unset($this->resolved[$driver]);

        return $this;
    }

    /**
     * Nhận cả SearchQuery đầy đủ lẫn cách gọi nhanh bằng chuỗi câu hỏi.
     *
     * @param  array<string, mixed> $filter
     * @return array<int, Document>
     */
    public function search(
        SearchQuery|string $query,
        int $topK = 4,
        array $filter = [],
        ?string $collection = null,
    ): array {
        if (!$query instanceof SearchQuery) {
            $query = SearchQuery::make($query)
                ->topK($topK)
                ->filter($filter)
                ->collection($collection);
        }

        return $this->driver()->search($query);
    }

    protected function resolve(string $driver): VectorStore
    {
        $config = $this->config->get("ai.vector_store.drivers.{$driver}");

        if (!is_array($config)) {
            throw new AIException(
                sprintf('Vector store driver "%s" chưa được cấu hình trong config/ai.php.', $driver)
            );
        }

        $embeddings = $this->embeddings($config);

        if (isset($this->customCreators[$driver])) {
            return ($this->customCreators[$driver])($config, $embeddings, $this->container);
        }

        if (isset($config['driver']) && class_exists($config['driver'])) {
            return new $config['driver']($embeddings, $config);
        }

        return match ($driver) {
            'memory' => new MemoryDriver($embeddings, $config),
            'file' => new FileDriver($embeddings, $config),
            'database' => new DatabaseDriver($embeddings, $this->connection($config), $config),
            'pgvector' => new PgVectorDriver($embeddings, $this->connection($config), $config),
            'qdrant' => new QdrantDriver($embeddings, $config),
            'elasticsearch' => new ElasticsearchDriver($embeddings, $this->elasticsearch($config), $config),
            default => throw new AIException(
                sprintf('Vector store driver "%s" is not supported.', $driver)
            ),
        };
    }

    /**
     * Mỗi kho vector có thể dùng embedding driver riêng, không thì lấy mặc định.
     *
     * @param array<string, mixed> $config
     */
    protected function embeddings(array $config): EmbeddingModel
    {
        return $this->container->get('ai.embedding')->driver($config['embedding'] ?? null);
    }

    /**
     * @param array<string, mixed> $config
     */
    protected function connection(array $config): Connection
    {
        return $this->container->get('database')->getConnection($config['connection'] ?? null);
    }

    /**
     * @param array<string, mixed> $config
     */
    protected function elasticsearch(array $config): object
    {
        if (!class_exists(ClientBuilder::class)) {
            throw new AIException('Cần cài gói elasticsearch/elasticsearch để dùng driver elasticsearch.');
        }

        $builder = ClientBuilder::create()->setHosts((array) ($config['hosts'] ?? ['localhost:9200']));

        if (!empty($config['api_key'])) {
            $builder->setApiKey((string) $config['api_key']);
        } elseif (!empty($config['username'])) {
            $builder->setBasicAuthentication(
                (string) $config['username'],
                (string) ($config['password'] ?? ''),
            );
        }

        return $builder->build();
    }
}
