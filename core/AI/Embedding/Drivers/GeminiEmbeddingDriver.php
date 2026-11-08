<?php

namespace Ryra\Core\AI\Embedding\Drivers;

/**
 * Google Gemini Embeddings API.
 *
 * @see https://ai.google.dev/api/embeddings
 */
class GeminiEmbeddingDriver extends AbstractEmbeddingDriver
{
    protected const DEFAULT_BASE_URL = 'https://generativelanguage.googleapis.com/v1beta';

    public function name(): string
    {
        return 'gemini';
    }

    public function embedBatch(array $texts): array
    {
        $this->guardNotEmpty($texts);

        $model = $this->model();
        $modelPath = str_starts_with($model, 'models/') ? $model : "models/{$model}";

        $requests = [];

        foreach ($texts as $text) {
            $request = [
                'model' => $modelPath,
                'content' => ['parts' => [['text' => $text]]],
            ];

            if ($this->dimensions() > 0) {
                $request['outputDimensionality'] = $this->dimensions();
            }

            if (!empty($this->config['task_type'])) {
                $request['taskType'] = (string) $this->config['task_type'];
            }

            $requests[] = $request;
        }

        $body = $this->transport->post(
            sprintf('%s/%s:batchEmbedContents', $this->baseUrl(static::DEFAULT_BASE_URL), $modelPath),
            ['x-goog-api-key' => $this->apiKey()],
            ['requests' => $requests],
        );

        return array_map(
            static fn (array $item): array => array_map('floatval', $item['values'] ?? []),
            $body['embeddings'] ?? [],
        );
    }
}
