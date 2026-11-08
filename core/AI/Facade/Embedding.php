<?php

namespace Ryra\Core\AI\Facade;

use Ryra\Core\Support\Facade;

/**
 * @method static array<int, float>                       embed(string $text)
 * @method static array<int, array<int, float>>           embedBatch(array $texts)
 * @method static int                                     dimensions()
 * @method static \Ryra\Core\Contracts\AI\EmbeddingModel  driver(?string $driver = null)
 * @method static \Ryra\Core\AI\Embedding\EmbeddingManager extend(string $driver, \Closure $creator)
 *
 * @see \Ryra\Core\AI\Embedding\EmbeddingManager
 */
class Embedding extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'ai.embedding';
    }
}
