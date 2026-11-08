<?php

namespace Ryra\Core\AI\Facade;

use Ryra\Core\Support\Facade;

/**
 * @method static void                                      add(array|\Ryra\Core\AI\VectorStore\Document $documents, ?string $collection = null)
 * @method static void                                      delete(array $ids, ?string $collection = null)
 * @method static array<int, \Ryra\Core\AI\VectorStore\Document> search(\Ryra\Core\AI\VectorStore\SearchQuery|string $query, int $topK = 4, array $filter = [], ?string $collection = null)
 * @method static void                                      clear(?string $collection = null)
 * @method static \Ryra\Core\Contracts\AI\VectorStore        driver(?string $driver = null)
 * @method static \Ryra\Core\AI\VectorStore\VectorStoreManager extend(string $driver, \Closure $creator)
 *
 * @see \Ryra\Core\AI\VectorStore\VectorStoreManager
 */
class VectorStore extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'ai.vector';
    }
}
