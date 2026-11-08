# Component AI

Lớp trừu tượng cho phép làm việc với nhiều nhà cung cấp AI qua cùng một API, kèm
kho vector phục vụ tìm kiếm ngữ nghĩa (RAG).

Đổi nhà cung cấp chỉ là đổi một dòng trong `config/ai.php`, code nghiệp vụ không
phải sửa.

## Kiến trúc

Theo đúng mô hình Manager + Driver như `core/Cache` và `core/Mail`:

| Service | Facade | Vai trò |
|---|---|---|
| `ai` | `AI` | Hội thoại: Claude, OpenAI, Gemini |
| `ai.embedding` | `Embedding` | Nhúng văn bản thành vector |
| `ai.vector` | `VectorStore` | Lưu và tìm kiếm vector |
| `ai.memory` | `ChatMemory` | Lịch sử hội thoại |

Mọi driver đều nói chuyện qua contract trong `core/Contracts/AI/`, nên module
nghiệp vụ chỉ phụ thuộc vào interface.

## Chat

```php
use Ryra\Core\AI\Facade\AI;

$text = AI::chat()
    ->system('Bạn là trợ lý cho hướng dẫn viên du lịch.')
    ->user('Tóm tắt lịch trình ngày 1 của tour Hạ Long.')
    ->text();
```

Đổi nhà cung cấp cho riêng một lần gọi:

```php
AI::chat('openai')->model('gpt-4o')->user('Xin chào')->text();
AI::chat('gemini')->user('Xin chào')->text();
```

Lấy đầy đủ thông tin thay vì chỉ chuỗi text:

```php
$response = AI::chat()->user('Xin chào')->call();

$response->text;                     // nội dung trả lời
$response->usage->totalTokens();     // số token đã dùng
$response->finishReason;             // Stop | Length | ToolCalls | Refusal...
$response->finishReason->isTruncated();  // bị cắt vì chạm trần max_tokens
```

### Streaming

```php
foreach (AI::chat()->user('Viết báo cáo tour')->stream() as $chunk) {
    echo $chunk->text;
}
```

Vừa đẩy token ra ngoài vừa giữ lại kết quả cuối để lưu DB:

```php
$response = AI::chat()
    ->user('Viết báo cáo tour')
    ->streamTo(fn ($chunk) => $socket->push($chunk->text));

$report->update(['content' => $response->text]);
```

Streaming dựa trên `Http::stream()` của `core/HttpClient` (dùng `write_func` của
Swoole) nên không chặn worker và token về tới đâu đẩy ra tới đó.

## Tool calling

Khai báo tool bằng attribute, schema tham số được suy ra từ kiểu dữ liệu PHP:

```php
use Ryra\Core\AI\Tool\Attributes\Tool;
use Ryra\Core\AI\Tool\Attributes\ToolParam;

class TourTools
{
    #[Tool('Lấy chi phí đã ghi nhận của một tour')]
    public function getTourExpenses(
        #[ToolParam('ID của tour')] string $tourId,
        #[ToolParam('Trạng thái duyệt', enum: ['moi', 'da_duyet', 'da_thanh_toan'])]
        ?string $status = null,
    ): array {
        return Expense::query()->where('tour_id', $tourId)->get()->toArray();
    }
}
```

```php
$answer = AI::chat()
    ->user('Tour T123 đã chi bao nhiêu và còn khoản nào chưa duyệt?')
    ->tools([new TourTools()])
    ->text();
```

Khi model yêu cầu gọi tool, `ChatClient` tự thực thi rồi gửi kết quả lại cho tới
khi có câu trả lời cuối (mặc định tối đa 5 vòng, đổi bằng `maxToolIterations()`).
Tool ném exception sẽ được trả về cho model dưới dạng lỗi để nó tự xoay xở, thay
vì làm đứt cuộc hội thoại.

Tool không có handler thì lib trả nguyên `toolCalls` ra ngoài để phía client tự
xử lý — hợp cho những thao tác cần người dùng xác nhận trên giao diện.

## Structured output

```php
$data = AI::chat()
    ->user('Trích thông tin chi phí từ hoá đơn này.')
    ->schema([
        'type' => 'object',
        'properties' => [
            'so_tien' => ['type' => 'integer'],
            'loai'    => ['type' => 'string', 'enum' => ['an_uong', 've', 'khac']],
        ],
        'required' => ['so_tien', 'loai'],
    ])
    ->json();
```

## Gửi kèm ảnh

```php
use Ryra\Core\AI\Message\Media;

AI::chat()
    ->user('Hoá đơn này bao nhiêu tiền?', [Media::fromPath($invoicePath)])
    ->text();
```

## Mẫu prompt

Thay cho việc nối chuỗi rải rác trong code. Biến thiếu sẽ báo lỗi ngay thay vì
lặng lẽ gửi `{ten_tour}` lên model.

```php
use Ryra\Core\AI\Template\PromptTemplate;
use Ryra\Core\AI\Message\Role;

AI::chat()
    ->template('Bạn là trợ lý của {cong_ty}.', ['cong_ty' => 'OneGuide'], Role::System)
    ->template('Tóm tắt chi phí tour {ma_tour} theo {tieu_chi}.', [
        'ma_tour' => 'T123',
        'tieu_chi' => ['loại chi phí', 'trạng thái duyệt'],  // mảng -> gạch đầu dòng
    ])
    ->text();

// Prompt dài nên để ra file riêng cho dễ review qua git
$template = PromptTemplate::fromFile(base_path('resources/prompts/bao-cao.txt'));
```

## Nạp tài liệu vào kho vector (ETL)

Tài liệu dài **bắt buộc** phải cắt nhỏ trước khi nhúng: nhúng nguyên một file
vào một vector sẽ làm loãng ngữ nghĩa, tìm kiếm gần như vô dụng.

```php
use Ryra\Core\AI\Document\Ingestor;
use Ryra\Core\AI\Document\Reader\DirectoryReader;
use Ryra\Core\AI\Facade\VectorStore;

$soDoan = (new Ingestor(VectorStore::driver()))
    ->read(new DirectoryReader(base_path('docs/tour')))
    ->split(chunkSize: 800, chunkOverlap: 100)
    ->into('lich_trinh')
    ->run();
```

Reader có sẵn: `TextReader`, `MarkdownReader` (tách theo tiêu đề), `HtmlReader`,
`JsonReader`, `CsvReader`, `ExcelReader`, `PdfReader`, `DirectoryReader` (tự chọn
reader theo đuôi file). Thêm định dạng mới bằng `DirectoryReader::register()`.

Muốn xem trước kết quả cắt nhỏ mà chưa tốn tiền gọi API nhúng thì dùng
`->collect()` thay cho `->run()`.

Cách cắt:

| Splitter | Đo bằng | Dùng khi |
|---|---|---|
| `CharacterTextSplitter` | ký tự | mặc định, tách đệ quy theo đoạn → dòng → câu → từ |
| `TokenTextSplitter` | token ước lượng | cần khống chế lượng ngữ cảnh nhồi vào prompt |

`PdfReader` cần thêm `smalot/pdfparser` hoặc lệnh `pdftotext`; thiếu cả hai sẽ
báo lỗi rõ ràng chứ không trả về nội dung rỗng.

## Kho vector và RAG

```php
use Ryra\Core\AI\Facade\VectorStore;
use Ryra\Core\AI\VectorStore\Document;

// Lập chỉ mục thủ công (không qua Ingestor)
VectorStore::add([
    Document::make('Ngày 1: đón khách tại sân bay Nội Bài...', ['tour_id' => 'T123']),
    Document::make('Ngày 2: tham quan vịnh Hạ Long...', ['tour_id' => 'T123']),
], collection: 'lich_trinh');

// Tìm theo ngữ nghĩa
$docs = VectorStore::search('khách được đón ở đâu', topK: 3, filter: ['tour_id' => 'T123']);
```

Thực tế nên để `RetrievalAdvisor` làm hết phần truy xuất và nhồi ngữ cảnh — xem
mục Advisor bên dưới.

Cần điều khiển chi tiết hơn thì dùng `SearchQuery`:

```php
use Ryra\Core\AI\VectorStore\SearchQuery;

VectorStore::search(
    SearchQuery::make('chi phí ăn uống')
        ->topK(5)
        ->threshold(0.75)          // bỏ kết quả không đủ liên quan
        ->filter(['tour_id' => 'T123', 'loai' => ['an_uong', 've']])
        ->collection('chi_phi'),
);
```

### Chọn driver cho kho vector

Hai nhóm khác nhau về cách tìm hàng xóm gần nhất:

| Driver | Cách tìm | Dùng khi |
|---|---|---|
| `memory` | duyệt toàn bộ, cosine bằng PHP | test, dữ liệu nạp lại mỗi lần chạy |
| `file` | duyệt toàn bộ, cosine bằng PHP | vài nghìn tài liệu, không muốn thêm hạ tầng |
| `database` | duyệt toàn bộ, cosine bằng PHP | tới vài chục nghìn bản ghi, tận dụng DB sẵn có |
| `pgvector` | HNSW trong PostgreSQL | dữ liệu lớn nhưng vẫn muốn nằm trong DB chính |
| `qdrant` | HNSW, DB vector chuyên dụng | dữ liệu lớn, cần tính năng vector nâng cao |
| `elasticsearch` | kNN trên `dense_vector` | đã có sẵn Elasticsearch trong hệ thống |

Nhóm duyệt toàn bộ cho kết quả **chính xác tuyệt đối** nhưng chi phí tăng tuyến
tính theo số tài liệu. Nhóm HNSW là tìm kiếm *xấp xỉ*: nhanh hơn nhiều lần, đổi
lại có thể bỏ sót một vài kết quả ở rìa.

Thuật toán chấm điểm nằm ở `VectorStore/Similarity.php` (cosine, dot, euclidean)
và `AbstractVectorDriver::rank()`.

### Bảng cần tạo

`database` (MySQL/PostgreSQL):

```sql
CREATE TABLE ai_vector_documents (
    id          VARCHAR(64)  NOT NULL PRIMARY KEY,
    collection  VARCHAR(120) NOT NULL,
    content     LONGTEXT     NOT NULL,
    metadata    JSON         NULL,
    embedding   JSON         NOT NULL,
    created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ai_vector_collection (collection)
);
```

`pgvector` — xem hướng dẫn đầy đủ trong `VectorStore/Drivers/PgVectorDriver.php`.
Lưu ý số chiều khai báo trong `VECTOR(...)` phải khớp `dimensions` của embedding
driver, lệch là kết quả sai hoặc bị từ chối.

## Advisor

Advisor bọc quanh mỗi lần gọi model, can thiệp được cả trước khi gửi lẫn sau khi
nhận. Nhờ vậy chuyện nhớ hội thoại, nhồi ngữ cảnh, ghi log không phải lặp lại
trong từng chỗ gọi.

```php
use Ryra\Core\AI\Advisor\ChatMemoryAdvisor;
use Ryra\Core\AI\Advisor\LoggerAdvisor;
use Ryra\Core\AI\Advisor\RetrievalAdvisor;
use Ryra\Core\AI\Advisor\SafeGuardAdvisor;
use Ryra\Core\AI\Facade\ChatMemory;
use Ryra\Core\AI\Facade\VectorStore;

$answer = AI::chat()
    ->user($cauHoi)
    ->advisors(
        new SafeGuardAdvisor(['số thẻ tín dụng', 'mật khẩu']),
        new RetrievalAdvisor(VectorStore::driver(), topK: 4, filter: ['tour_id' => $tourId]),
        new ChatMemoryAdvisor(ChatMemory::driver(), "tour:{$tourId}:user:{$userId}"),
        new LoggerAdvisor(logger()),
    )
    ->text();
```

| Advisor | Thứ tự | Việc làm |
|---|---|---|
| `LoggerAdvisor` | 0 | Ghi model, thời gian, token đã dùng |
| `SafeGuardAdvisor` | 10 | Chặn nội dung cấm, **không gọi model** nên không tốn tiền |
| `RetrievalAdvisor` | 50 | Tìm tài liệu liên quan rồi chèn vào prompt (RAG) |
| `ChatMemoryAdvisor` | 80 | Nạp lịch sử trước khi gửi, lưu lượt mới sau khi nhận |

Số `order` nhỏ chạy trước và nằm ngoài cùng. Tự viết advisor thì kế thừa
`BaseAdvisor`; không gọi `$next($request)` nghĩa là chặn hẳn request và tự trả về
phản hồi.

Advisor chạy được với `call()` và `streamTo()`. Riêng `stream()` trả về generator
nên không chạy được advisor — gọi `stream()` khi đã đăng ký advisor sẽ báo lỗi rõ
thay vì lặng lẽ bỏ qua.

## Bộ nhớ hội thoại

```php
use Ryra\Core\AI\Facade\ChatMemory;

// Thường dùng gián tiếp qua ChatMemoryAdvisor, nhưng gọi thẳng cũng được
ChatMemory::messages("tour:{$tourId}");
ChatMemory::clear("tour:{$tourId}");
```

`MessageWindowChatMemory` chỉ giữ N lượt gần nhất (mặc định 20). Message system
luôn được giữ lại, và cửa sổ được cắt sao cho không để lại `tool_result` mồ côi —
thiếu lượt assistant tương ứng thì nhà cung cấp sẽ từ chối request.

## Thêm nhà cung cấp mới

Cách nhanh nhất: nếu dịch vụ nói cùng giao thức OpenAI thì chỉ cần đổi `base_url`
trong `config/ai.php`, không phải viết code (áp dụng cho Azure OpenAI, Groq,
Together, OpenRouter, Ollama...).

```php
'local' => [
    'driver'   => \Ryra\Core\AI\Drivers\OpenAIDriver::class,
    'base_url' => 'http://localhost:11434/v1',
    'api_key'  => 'ollama',
    'model'    => 'llama3.1',
],
```

Giao thức khác hẳn thì viết driver kế thừa `AbstractDriver` và đăng ký:

```php
AI::extend('mistral', fn (array $config) => new MistralDriver($config));
```

Cách này áp dụng cho cả `Embedding::extend()` và `VectorStore::extend()`.

## Lưu ý theo từng nhà cung cấp

Driver đã xử lý sẵn các khác biệt sau, không cần bận tâm khi dùng:

- **Claude**: `max_tokens` là bắt buộc; dòng Opus 5 / Sonnet 5 **từ chối**
  `temperature` và `top_p` (trả về 400) nên driver tự bỏ qua — điều chỉnh hành vi
  bằng prompt và `effort()`. System prompt tách riêng và có thể đánh dấu cache
  (`->system($text, cacheable: true)`), rất đáng dùng vì system prompt thường lặp
  lại giữa các request.
- **OpenAI**: dùng `max_completion_tokens`; muốn có usage khi stream phải bật
  `stream_options.include_usage` (driver tự bật).
- **Gemini**: cấu trúc `contents`/`parts` riêng, `assistant` gọi là `model`, và
  chỉ nhận một tập con của JSON Schema — driver tự lọc bỏ khoá không hợp lệ.

## Test

```bash
./vendor/bin/phpunit core/AI/Tests
```

Test dùng model và transport giả nên không gọi ra ngoài mạng, không cần API key.

### Test với API thật

```bash
export GEMINI_API_KEY=...        # hoac ANTHROPIC_API_KEY / OPENAI_API_KEY
docker compose exec php php core/AI/Tests/Live/run.php
```

Nhà cung cấp nào không có key thì tự bỏ qua. Bộ này kiểm những thứ chỉ lộ ra khi
gọi thật: payload có bị từ chối không, SSE thật có parse đúng không, số chiều
vector có khớp config không, tool calling có chạy trọn vòng không.

Phải chạy trong container vì `core/HttpClient` dùng `Swoole\Coroutine\Http\Client`
— client này cần Swoole có OpenSSL mới gọi được HTTPS.

## Lưu ý riêng cho Gemini 3

Dòng Gemini 3 bật suy nghĩ mặc định và **phần suy nghĩ ăn chung hạn mức
`maxOutputTokens`**. Với `maxTokens(100)`, model có thể tiêu 400 token cho suy
nghĩ rồi trả về câu cụt. Khống chế bằng:

```php
AI::chat('gemini')->thinking(false)->user('...')->text();   // thinkingLevel: minimal
AI::chat('gemini')->effort('low')->user('...')->text();     // thinkingLevel: low
```

Driver cũng tự giữ và gửi trả `thoughtSignature` khi tool calling — thiếu nó
Gemini 3 trả về 400.
