<?php

namespace Ryra\Core\AI\Facade;

use Ryra\Core\Support\Facade;

/**
 * @method static array<int, \Ryra\Core\AI\Message\Message> messages(string $conversationId)
 * @method static void                                      add(string $conversationId, \Ryra\Core\AI\Message\Message ...$messages)
 * @method static void                                      clear(string $conversationId)
 * @method static \Ryra\Core\Contracts\AI\ChatMemory        driver(?string $driver = null)
 *
 * @see \Ryra\Core\AI\Memory\ChatMemoryManager
 */
class ChatMemory extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'ai.memory';
    }
}
