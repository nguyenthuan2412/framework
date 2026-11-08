<?php

namespace Ryra\Core\AI\Advisor;

use Closure;
use Ryra\Core\AI\Response\ChatResponse;
use Ryra\Core\AI\Template\PromptTemplate;
use Ryra\Core\AI\VectorStore\Document;
use Ryra\Core\AI\VectorStore\SearchQuery;
use Ryra\Core\Contracts\AI\VectorStore;

/**
 * RAG: lấy câu hỏi của người dùng đi tìm trong kho vector rồi chèn tài liệu tìm
 * được vào prompt trước khi gửi cho model.
 *
 * Nhờ đó model trả lời dựa trên dữ liệu của hệ thống thay vì bịa theo trí nhớ.
 * Danh sách tài liệu đã dùng được lưu vào context để tầng trên trích dẫn nguồn.
 */
class RetrievalAdvisor extends BaseAdvisor
{
    public const CONTEXT_KEY = 'retrieved_documents';

    protected const DEFAULT_TEMPLATE = <<<'TXT'
        Trả lời dựa trên phần tài liệu dưới đây. Nếu tài liệu không chứa thông tin
        cần thiết, hãy nói rõ là không tìm thấy thay vì suy đoán.

        <tai_lieu>
        {context}
        </tai_lieu>
        TXT;

    protected PromptTemplate $template;

    /**
     * @param array<string, mixed> $filter Điều kiện lọc theo metadata.
     */
    public function __construct(
        protected VectorStore $store,
        protected int $topK = 4,
        protected float $threshold = 0.0,
        protected array $filter = [],
        protected ?string $collection = null,
        ?PromptTemplate $template = null,
    ) {
        $this->template = $template ?? PromptTemplate::make(static::DEFAULT_TEMPLATE);
        $this->order = 50;
    }

    public function advise(ChatRequest $request, Closure $next): ChatResponse
    {
        $question = $request->lastUserMessage()?->content;

        if ($question === null || trim($question) === '') {
            return $next($request);
        }

        $documents = $this->store->search(
            SearchQuery::make($question)
                ->topK($this->topK)
                ->threshold($this->threshold)
                ->filter($this->filter)
                ->collection($this->collection),
        );

        $request->withContext(static::CONTEXT_KEY, $documents);

        // Không tìm được gì thì cứ để model tự trả lời, chèn khối tài liệu rỗng
        // chỉ làm nhiễu prompt.
        if ($documents !== []) {
            $request->prependSystem($this->template->render(['context' => $this->format($documents)]));
        }

        return $next($request);
    }

    /**
     * @param array<int, Document> $documents
     */
    protected function format(array $documents): string
    {
        $blocks = [];

        foreach ($documents as $index => $document) {
            $source = $document->metadata('source', $document->metadata('file_name', $document->id));

            $blocks[] = sprintf("[%d] (nguồn: %s)\n%s", $index + 1, $source, $document->content);
        }

        return implode(PHP_EOL . PHP_EOL, $blocks);
    }
}
