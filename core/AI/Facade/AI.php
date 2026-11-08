<?php

namespace Ryra\Core\AI\Facade;

use Ryra\Core\Support\Facade;

/**
 * @method static \Ryra\Core\AI\ChatClient                chat(?string $driver = null)
 * @method static \Ryra\Core\Contracts\AI\ChatModel       driver(?string $driver = null)
 * @method static \Ryra\Core\AI\AIManager                 extend(string $driver, \Closure $creator)
 * @method static array<int, string>                      drivers()
 * @method static \Ryra\Core\AI\Response\ChatResponse     call(\Ryra\Core\AI\Prompt $prompt, ?\Ryra\Core\AI\ChatOptions $options = null)
 * @method static \Generator                              stream(\Ryra\Core\AI\Prompt $prompt, ?\Ryra\Core\AI\ChatOptions $options = null)
 *
 * @see \Ryra\Core\AI\AIManager
 */
class AI extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'ai';
    }
}
