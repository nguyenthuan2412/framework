<?php

namespace Ryra\Core\AI\Embedding\Drivers;

/**
 * Voyage AI Embeddings.
 *
 * Anthropic không có API embedding riêng nên đây là lựa chọn đi kèm khi
 * dùng Claude cho phần chat.
 *
 * @see https://docs.voyageai.com/reference/embeddings-api
 */
class VoyageEmbeddingDriver extends AbstractEmbeddingDriver
{
    protected const DEFAULT_BASE_URL = 'https://api.voyageai.com/v1';

    public function name(): string
    {
        return 'voyage';
    }

    public function embedBatch(array $texts): array
    {
        $this->guardNotEmpty($texts);

        $payload = [
            'model' => $this->model(),
            'input' => array_values($texts),
        ];

        // document khi lập chỉ mục, query khi tìm kiếm - đặt đúng giúp tăng độ chính xác.
        if (!empty($this->config['input_type'])) {
            $payload['input_type'] = (string) $this->config['input_type'];
        }

        if ($this->dimensions() > 0) {
            $payload['output_dimension'] = $this->dimensions();
        }

        $body = $this->transport->post(
            $this->baseUrl(static::DEFAULT_BASE_URL) . '/embeddings',
            ['Authorization' => 'Bearer ' . $this->apiKey()],
            $payload,
        );

        $vectors = [];

        foreach ($body['data'] ?? [] as $item) {
            $vectors[$item['index'] ?? count($vectors)] = array_map('floatval', $item['embedding'] ?? []);
        }

        ksort($vectors);

        return array_values($vectors);
    }
}
