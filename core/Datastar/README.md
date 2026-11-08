# Component Datastar

Hỗ trợ phía server cho [Datastar](https://data-star.dev) — framework hypermedia
chỉ cần một file JS ~11KB. Trình duyệt khai báo hành vi bằng thuộc tính `data-*`,
server trả về event SSE để patch HTML và signal, không cần build frontend.

Component bám đúng
[SDK spec](https://github.com/starfederation/datastar/blob/main/sdk/ADR.md) của
Datastar: đủ hai event `datastar-patch-elements` / `datastar-patch-signals`, đủ 8
mode patch, bỏ qua các giá trị mặc định khi ghi event, đọc signals theo đúng quy
ước GET/body.

## Kiến trúc

| Thành phần | Vai trò |
|---|---|
| `ServerSentEventGenerator` | Sinh event SSE đúng format, API giống các SDK chính thức |
| `SseResponse` | Response giữ kết nối, ghi thẳng vào `Swoole\Http\Response` |
| `Signals` | Signals client gửi lên, truy cập theo đường dẫn dạng chấm |
| `Fragment` | Khai báo mảnh giao diện render riêng được, khớp với danh sách client gửi |
| `DatastarManager` | Điểm vào: mở stream, đọc signals, render view, sinh thẻ script |
| `DatastarExtension` | Hàm/filter Twig sinh thuộc tính `data-*` |

| Service | Facade | Helper |
|---|---|---|
| `datastar` | `Datastar` | `datastar()`, `sse()`, `signals()` |

Service `datastar` bind dạng **scoped** — mỗi coroutine (mỗi request) có manager
riêng, signals đã đọc được cache trong phạm vi request đó.

## Cấu hình

`config/datastar.php` quản lý version script, header SSE, `retry_duration`,
`keep_alive` và tên query param chứa signals. Các biến `.env` hay dùng:

```dotenv
DATASTAR_VERSION=1.0.2
DATASTAR_SCRIPT_URL=            # để trống thì lấy CDN theo version ở trên
DATASTAR_KEEP_ALIVE=0           # giây, >0 khi có proxy cắt kết nối idle
```

## Nạp script trong layout

```twig
{# modules/guide/views/layout.twig #}
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    {{ datastar_script() }}
</head>
<body>
    {% block content %}{% endblock %}
</body>
</html>
```

Muốn self-host thì tải `datastar.js` về `assets` của module rồi đặt
`DATASTAR_SCRIPT_URL` bằng `asset('datastar.js', 'guide')`.

## Thuộc tính `data-*` trong Twig

Viết tay JSON trong attribute rất dễ sai quote, nên dùng các hàm sau:

```twig
<div {{ ds_signals({ count: 0, keyword: '' }) }}>
    <span {{ ds({ text: '$count' }) }}></span>

    <input {{ ds({
        bind: 'keyword',
        'on_input__debounce.300ms': ds_get(route('todo.search'))
    }) }}>

    <button {{ ds({
        on_click: ds_post(route('todo.increase')),
        indicator: 'loading',
        attr_disabled: '$loading'
    }) }}>Tăng</button>

    <div id="todo-list">{% block todo_list %}...{% endblock %}</div>
</div>
```

Quy tắc của `ds()`:

- `on_click` → `data-on-click`, `onKeydown` → `data-on-keydown`
- phần sau `__` là modifier của Datastar nên được giữ nguyên:
  `'on_input__debounce.300ms'` → `data-on-input__debounce.300ms`
- `true` cho ra attribute không có giá trị, `false`/`null` thì bỏ qua
- mảng được encode thành JSON và escape sẵn

Các hàm còn lại: `ds_signals(mảng, ifMissing)`, `ds_signal('user.name')` → `$user.name`,
`ds_get/ds_post/ds_put/ds_patch/ds_delete(url, options, fragments)` → `@post('/url', {...})`,
`ds_fragments(['todo_list'])`, filter `|ds_json`.

## Trả event SSE từ controller

```php
use Ryra\Core\Datastar\Facade\Datastar;
use Ryra\Core\Datastar\ServerSentEventGenerator;
use Ryra\Core\Datastar\SseResponse;
use Ryra\Core\Route\Attributes\Get;
use Ryra\Core\Route\Attributes\Group;
use Ryra\Core\Route\Attributes\Post;

#[Group(prefix: 'todos')]
class TodoController
{
    #[Get(uri: '', name: 'todo.index')]
    public function index(): Response
    {
        return Response::view('todos/index.twig', ['todos' => Todo::query()->get()]);
    }

    #[Post(uri: 'increase', name: 'todo.increase')]
    public function increase(): SseResponse
    {
        $count = signals('count') + 1;

        return Datastar::stream(function (ServerSentEventGenerator $sse) use ($count) {
            // Cập nhật state ở client
            $sse->patchSignals(['count' => $count, 'loading' => false]);

            // Render lại đúng block `todo_list` của chính view trang danh sách
            $sse->patchView('todos/index.twig', ['todos' => Todo::query()->get()], block: 'todo_list');
        });
    }
}
```

Nhờ `block:`, trang đầy đủ và fragment dùng chung một template — không phải tách
file partial cho từng mảnh HTML.

Các cách rút gọn khi chỉ gửi một event:

```php
return Datastar::patchView('todos/index.twig', $data, block: 'todo_list');
return Datastar::patchSignals(['count' => 3]);
return Datastar::patchElements('<div id="alert">Đã lưu</div>');
return Datastar::redirect(route('todo.index'));
```

## Partial render: client báo trước mảnh nào sẽ đổi

Server không thể tự đoán mảnh nào cần render, nên client phải khai báo. Cơ chế
gồm hai phía:

| Phía | Việc phải làm |
|---|---|
| Server | Khai báo sẵn các mảnh render riêng được (mỗi mảnh là một `{% block %}`) |
| Client | Gắn danh sách mảnh sẽ đổi vào action, gửi lên qua header `Datastar-Fragments` |

Server chỉ render phần giao của hai danh sách. Client không thể ép server render
một block bất kỳ, tên gõ sai thì bị bỏ qua chứ không làm vỡ stream.

Ngoài ra Datastar luôn gắn header `Datastar-Request: true`, nên `page()` phân
biệt được ba tình huống:

| Tình huống | Server trả về |
|---|---|
| Gõ url, F5, bot vào trang | HTML đầy đủ: layout + header + footer + nội dung |
| Datastar request có `Datastar-Fragments` | Đúng các mảnh được liệt kê, mỗi mảnh một event |
| Datastar request không liệt kê gì | Mảnh đầu tiên trong khai báo (thường là `content`) |

### View: mỗi mảnh là một block

```twig
{# modules/guide/views/todos/index.twig #}
{% extends '@guide/layout.twig' %}

{% block content %}
    <main id="content">
        <h1>{{ title }}</h1>
        {% block todo_list %}
            <div id="todo-list">
                {% for todo in todos %}<div>{{ todo.name }}</div>{% endfor %}
            </div>
        {% endblock %}
        {% block pagination %}<nav id="pagination">{{ page }}/{{ pages }}</nav>{% endblock %}
    </main>
{% endblock %}
```

### Controller: khai báo các mảnh

```php
#[Get(uri: '', name: 'todo.index')]
public function index(): Response
{
    return Datastar::page(
        view: 'todos/index.twig',
        context: [
            'title' => 'Việc cần làm',

            // Closure trong context chung được hoãn tới lúc render, dùng cho dữ
            // liệu nhiều mảnh cùng cần
            'todos' => fn () => Todo::query()->paginate(signals('page', 1)),
        ],
        fragments: [
            'content',                      // cả vùng nội dung, dùng khi chuyển trang
            'todo_list' => '#todo-list',    // chỉ danh sách
            'pagination' => ['selector' => '#pagination', 'mode' => 'inner'],
        ],
        url: route('todo.index'),           // đẩy lên thanh địa chỉ để back/F5 vẫn đúng
        title: 'Việc cần làm',              // đổi luôn document.title
    );
}
```

Muốn dữ liệu nặng **chỉ được tính khi mảnh đó thực sự được yêu cầu** thì đặt
closure vào context của chính mảnh đó — đây mới là chỗ tiết kiệm thật sự:

```php
fragments: [
    'content',
    'todo_list' => [
        'selector' => '#todo-list',
        'context' => fn (array $shared) => ['todos' => Todo::query()->get()],
    ],
],
```

Client xin `content` thì closure của `todo_list` không chạy, và ngược lại.
Closure ở context chung thì khác: nó luôn chạy một lần cho mọi lần render, chỉ
được hoãn chứ không bỏ qua — nên dữ liệu chỉ một mảnh dùng thì đừng để ở đó.

### Template: client liệt kê mảnh sẽ đổi

```twig
{# Chuyển trang: đổi cả vùng nội dung #}
<a href="{{ route('todo.index') }}" {{ ds({ on_click: ds_get(route('todo.index'), fragments: ['content']) }) }}>
    Việc cần làm
</a>

{# Lọc danh sách: chỉ danh sách và phân trang đổi, tiêu đề giữ nguyên #}
<input {{ ds({
    bind: 'keyword',
    'on_input__debounce.300ms': ds_get(route('todo.index'), fragments: ['todo_list', 'pagination'])
}) }}>
```

`ds_get(url, options, fragments)` sinh ra:

```
@get('/todos', {"headers":{"datastar-fragments":"todo_list,pagination"}})
```

Cần ghép thủ công với option khác thì dùng `ds_fragments(['todo_list'])` để lấy
riêng phần options, hoặc gọi `Datastar::requestedFragments()` ở server khi muốn
tự xử lý.

Lưu ý:

- Element gốc trong block phải có `id` để Datastar biết chỗ patch, hoặc khai báo
  `selector` cho mảnh đó.
- Luôn truyền `url` khi điều hướng, nếu không F5 sẽ quay về trang cũ. Nhánh F5
  chạy lại full page nên không cần xử lý gì thêm.
- Debug bằng trình duyệt: thêm `?_fragments=todo_list` vào url cũng có tác dụng
  tương đương header.

## API của generator

```php
$sse->patchElements($html, selector: '#list', mode: 'append');  // 8 mode: outer|inner|replace|prepend|append|before|after|remove
$sse->patchView('todos/index.twig', $ctx, block: 'todo_list');  // render Twig rồi patch
$sse->removeElements('#row-12');
$sse->patchSignals(['user' => ['name' => 'Thuận']]);            // JSON Merge Patch
$sse->patchSignals(['count' => 0], onlyIfMissing: true);
$sse->removeSignals(['user.name', 'draft']);                    // gán null theo spec
$sse->executeScript('alert("xong")');
$sse->pushUrl('/todos?page=2');                                  // đổi url, không tải lại trang
$sse->title('Việc cần làm');                                     // đổi document.title
$sse->redirect('/dang-nhap');
$sse->comment('keep-alive');                                     // giữ kết nối
$sse->isConnected();                                             // client còn kết nối không
$sse->close();
```

Tất cả method đều nhận thêm `eventId` và `retryDuration`. `retryDuration` chỉ
được ghi vào event khi khác mặc định 1000ms, đúng như spec yêu cầu.

## Đọc signals gửi lên

Datastar gửi toàn bộ signal store theo mỗi request: GET thì nằm ở query param
`datastar` (JSON), method khác thì nằm trong body JSON. Component đọc cả hai:

```php
signals();                       // Signals — toàn bộ store
signals('keyword');              // giá trị theo đường dẫn dạng chấm
signals('filters.status', 'new'); // kèm giá trị mặc định

$signals = Datastar::signals();
$signals->integer('page', 1);
$signals->boolean('only_active');
$signals->only(['keyword', 'page']);
$signals->toDTO(SearchTodoDTO::class);   // validate như dữ liệu form thường
```

`Datastar::isDatastarRequest()` cho biết request có phải do Datastar phát ra hay
không (dựa vào header `Datastar-Request` hoặc `Accept: text/event-stream`), dùng
khi một route vừa trả HTML đầy đủ vừa trả fragment — xem phần partial render ở trên.

## Luồng chạy dài (realtime, progress)

`SseResponse` giữ kết nối nên có thể đẩy event liên tục trong coroutine:

```php
return sse(function (ServerSentEventGenerator $sse) use ($jobId) {
    while ($sse->isConnected()) {
        $job = Job::query()->find($jobId);

        $sse->patchSignals(['progress' => $job->progress]);

        if ($job->isDone()) {
            $sse->patchView('jobs/show.twig', ['job' => $job], block: 'result');
            break;
        }

        \Swoole\Coroutine::sleep(1);
    }
});
```

Vài lưu ý khi chạy thật:

- `isConnected()` trả về false ngay khi client đóng tab (Swoole báo lỗi ghi), nhớ
  dùng nó làm điều kiện vòng lặp để coroutine không sống mãi.
- Đặt `DATASTAR_KEEP_ALIVE` > 0 khi có nginx/ALB phía trước để chúng không cắt
  kết nối idle; component sẽ tự gửi comment SSE theo chu kỳ đó.
- Header `X-Accel-Buffering: no` đã được gửi sẵn để nginx không gom buffer. Nếu
  tự cấu hình proxy, nhớ tắt luôn `proxy_buffering` cho route SSE.
- Mỗi stream chiếm một coroutine, cân nhắc số kết nối đồng thời khi mở stream
  chạy dài cho toàn bộ người dùng.

## Test

```bash
./vendor/bin/phpunit tests/Datastar
```

Không cần server Swoole để kiểm tra chuỗi SSE:

```php
// Kiểm tra generator
$sse = Datastar::buffer();
$sse->patchSignals(['count' => 1]);
$sse->getWriter()->getContent();  // "event: datastar-patch-signals\ndata: signals {\"count\":1}\n\n"

// Kiểm tra cả response trả về từ controller
$response = $controller->index();
$this->assertStringContainsString('data: selector #todo-list', $response->capture());
```
