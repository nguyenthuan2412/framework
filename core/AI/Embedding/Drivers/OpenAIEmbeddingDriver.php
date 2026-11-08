<?php

namespace Ryra\Core\AI\Embedding\Drivers;

/**
 * OpenAI Embeddings API - dùng được cho mọi dịch vụ tương thích giao thức.
 *
 * @see https://platform.openai.com/docs/api-reference/embeddings
 */
class OpenAIEmbeddingDriver extends AbstractEmbeddingDriver
{
    protected const DEFAULT_BASE_URL = 'https://api.openai.com/v1';

    public function name(): string
    {
        return 'openai';
    }

    public function embedBatch(array $texts): array
    {
        $this->guardNotEmpty($texts);

        $payload = [
            'model' => $this->model(),
            'input' => array_values($texts),
        ];

        // text-embedding-3-* cho phép rút gọn số chiều để tiết kiệm dung lượng lưu trữ.
        if ($this->dimensions() > 0) {
            $payload['dimensions'] = $this->dimensions();
        }

        $body = $this->transport->post(
            $this->baseUrl(static::DEFAULT_BASE_URL) . '/embeddings',
            ['Authorization' => 'Bearer ' . $this->apiKey()],
            $payload,
        );

        $vectors = [];

        foreach ($body['data'] ?? [] as $item) {
            // API không đảm bảo thứ tự, phải xếp lại theo index.
            $vectors[$item['index'] ?? count($vectors)] = array_map('floatval', $item['embedding'] ?? []);
        }

        ksort($vectors);

        return array_values($vectors);
    }
}
