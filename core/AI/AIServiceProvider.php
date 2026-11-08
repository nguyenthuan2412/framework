<?php

namespace Ryra\Core\AI;

use Ryra\Core\AI\Embedding\EmbeddingManager;
use Ryra\Core\AI\Memory\ChatMemoryManager;
use Ryra\Core\AI\VectorStore\VectorStoreManager;
use Ryra\Core\Container\ServiceProvider;
use Ryra\Core\Contracts\AI\ChatMemory;
use Ryra\Core\Contracts\AI\ChatModel;
use Ryra\Core\Contracts\AI\EmbeddingModel;
use Ryra\Core\Contracts\AI\VectorStore;

class AIServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton('ai', fn ($app) => new AIManager($app));
        $this->app->singleton('ai.embedding', fn ($app) => new EmbeddingManager($app));
        $this->app->singleton('ai.vector', fn ($app) => new VectorStoreManager($app));
        $this->app->singleton('ai.memory', fn ($app) => new ChatMemoryManager($app));

        // Cho phép inject thẳng contract vào constructor thay vì gọi qua facade.
        $this->app->transient(ChatModel::class, fn ($app) => $app->get('ai')->driver());
        $this->app->transient(EmbeddingModel::class, fn ($app) => $app->get('ai.embedding')->driver());
        $this->app->transient(VectorStore::class, fn ($app) => $app->get('ai.vector')->driver());
        $this->app->transient(ChatMemory::class, fn ($app) => $app->get('ai.memory')->driver());
    }
}
