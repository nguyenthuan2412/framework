<?php

namespace Ryra\Core\AI\Tests;

use PHPUnit\Framework\TestCase;
use Ryra\Core\AI\AIManager;
use Ryra\Core\AI\ChatClient;
use Ryra\Core\AI\Drivers\ClaudeDriver;
use Ryra\Core\AI\Drivers\GeminiDriver;
use Ryra\Core\AI\Drivers\OpenAIDriver;
use Ryra\Core\AI\Embedding\Drivers\GeminiEmbeddingDriver;
use Ryra\Core\AI\Embedding\Drivers\OpenAIEmbeddingDriver;
use Ryra\Core\AI\Embedding\EmbeddingManager;
use Ryra\Core\AI\Exceptions\AIException;
use Ryra\Core\AI\Tests\Fixtures\FakeChatModel;
use Ryra\Core\AI\Tests\Fixtures\StubContainer;
use Ryra\Core\AI\VectorStore\Drivers\MemoryDriver;
use Ryra\Core\AI\VectorStore\VectorStoreManager;
use Ryra\Core\Config\Repository;

class AIManagerTest extends TestCase
{
    protected StubContainer $container;

    protected function setUp(): void
    {
        parent::setUp();

        $config = new Repository();
        $config->set('ai', [
            'default' => 'claude',
            'drivers' => [
                'claude' => ['api_key' => 'k', 'model' => 'claude-opus-5'],
                'openai' => ['api_key' => 'k', 'model' => 'gpt-4o'],
                'gemini' => ['api_key' => 'k', 'model' => 'gemini-2.5-flash'],
            ],
            'embedding' => [
                'default' => 'openai',
                'drivers' => [
                    'openai' => ['api_key' => 'k', 'model' => 'text-embedding-3-small', 'dimensions' => 1536],
                    'gemini' => ['api_key' => 'k', 'model' => 'gemini-embedding-001', 'dimensions' => 768],
                ],
            ],
            'vector_store' => [
                'default' => 'memory',
                'drivers' => [
                    'memory' => ['collection' => 'default'],
                    'memory_gemini' => ['driver' => MemoryDriver::class, 'embedding' => 'gemini'],
                ],
            ],
        ]);

        $this->container = new StubContainer(['config' => $config]);
        $this->container->services['ai'] = new AIManager($this->container);
        $this->container->services['ai.embedding'] = new EmbeddingManager($this->container);
        $this->container->services['ai.vector'] = new VectorStoreManager($this->container);
    }

    protected function ai(): AIManager
    {
        return $this->container->services['ai'];
    }

    public function test_lay_dung_driver_theo_ten(): void
    {
        $this->assertInstanceOf(ClaudeDriver::class, $this->ai()->driver('claude'));
        $this->assertInstanceOf(OpenAIDriver::class, $this->ai()->driver('openai'));
        $this->assertInstanceOf(GeminiDriver::class, $this->ai()->driver('gemini'));
    }

    public function test_ten_goi_khac_tro_ve_cung_driver(): void
    {
        // anthropic/google là alias, phải dùng lại config của claude/gemini
        // chứ không đi tìm khoá cấu hình cùng tên.
        $this->assertInstanceOf(ClaudeDriver::class, $this->ai()->driver('anthropic'));
        $this->assertInstanceOf(GeminiDriver::class, $this->ai()->driver('google'));

        // Và phải là cùng một instance với tên chuẩn.
        $this->assertSame($this->ai()->driver('claude'), $this->ai()->driver('anthropic'));
    }

    public function test_khong_truyen_ten_thi_lay_driver_mac_dinh(): void
    {
        $this->assertSame('claude', $this->ai()->getDefaultDriver());
        $this->assertInstanceOf(ClaudeDriver::class, $this->ai()->driver());
    }

    public function test_dung_lai_instance_da_dung(): void
    {
        $this->assertSame($this->ai()->driver('openai'), $this->ai()->driver('openai'));
    }

    public function test_bao_loi_ro_rang_khi_driver_chua_cau_hinh(): void
    {
        $this->expectException(AIException::class);
        $this->expectExceptionMessage('chưa được cấu hình');

        $this->ai()->driver('mistral');
    }

    public function test_dang_ky_them_provider_ngoai_core(): void
    {
        $this->container->services['config']->set('ai.drivers.grok', ['api_key' => 'x', 'model' => 'grok-4']);

        $this->ai()->extend('grok', fn (array $config) => new FakeChatModel());

        $this->assertInstanceOf(FakeChatModel::class, $this->ai()->driver('grok'));
    }

    public function test_chat_tra_ve_client_moi_moi_lan_goi(): void
    {
        $first = $this->ai()->chat();
        $second = $this->ai()->chat();

        $this->assertInstanceOf(ChatClient::class, $first);
        // Mỗi request một client riêng, tránh lẫn state khi chạy coroutine.
        $this->assertNotSame($first, $second);
    }

    public function test_embedding_manager_lay_dung_driver(): void
    {
        $embedding = $this->container->services['ai.embedding'];

        $this->assertInstanceOf(OpenAIEmbeddingDriver::class, $embedding->driver());
        $this->assertInstanceOf(GeminiEmbeddingDriver::class, $embedding->driver('gemini'));
        $this->assertInstanceOf(GeminiEmbeddingDriver::class, $embedding->driver('google'));
        $this->assertSame(1536, $embedding->driver()->dimensions());
    }

    public function test_vector_store_dung_embedding_rieng_theo_cau_hinh(): void
    {
        $vector = $this->container->services['ai.vector'];

        $this->assertInstanceOf(MemoryDriver::class, $vector->driver('memory'));
        // Kho này khai báo embedding riêng nên phải dùng đúng driver gemini.
        $this->assertInstanceOf(MemoryDriver::class, $vector->driver('memory_gemini'));
    }
}
