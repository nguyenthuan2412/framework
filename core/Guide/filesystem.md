# Lưu trữ file (Filesystem)

Filesystem dùng [Flysystem](https://flysystem.thephpleague.com/) bên dưới. Nhờ vậy cùng một đoạn code có thể lưu file trên máy chủ (local) hoặc trên S3 / MinIO.

## 1. Cấu hình

File [config/filesystems.php](../../config/filesystems.php). Mỗi **disk** là một nơi lưu trữ:

| Disk | Driver | Lưu ở đâu |
|------|--------|-----------|
| `local` (mặc định) | `local` | `storage/public/`, truy cập qua URL `APP_URL/public/...` |
| `s3` | `s3` | Bucket S3 / MinIO theo các biến `AWS_*` |

```dotenv
AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=ap-southeast-1
AWS_BUCKET=my-bucket
AWS_URL=https://my-bucket.s3.amazonaws.com
AWS_ENDPOINT=                      # điền khi dùng MinIO, ví dụ http://minio:9000
AWS_USE_PATH_STYLE_ENDPOINT=false  # true khi dùng MinIO
```

Muốn thêm disk, thêm một mục vào mảng `disks` với `driver` là `local` hoặc `s3`.

## 2. Đọc / ghi file

```php
use Ryra\Core\Filesystem\Facade\Filesystem;

// Disk mặc định
Filesystem::write('reports/2026.txt', 'nội dung');
Filesystem::read('reports/2026.txt');
Filesystem::fileExists('reports/2026.txt');
Filesystem::delete('reports/2026.txt');

// Disk cụ thể
$s3 = Filesystem::disk('s3');
$s3->write('avatars/1.jpg', $binary);
```

Danh sách hàm:

| Hàm | Mô tả |
|-----|-------|
| `write($path, $contents)` | Ghi nội dung (tạo mới hoặc ghi đè) |
| `writeStream($path, $resource)` | Ghi từ stream, dùng cho file lớn |
| `read($path)` | Đọc toàn bộ nội dung |
| `readStream($path)` | Đọc dạng stream |
| `fileExists($path)` / `directoryExists($path)` / `has($path)` | Kiểm tra tồn tại |
| `delete($path)` / `deleteDirectory($path)` | Xoá |
| `createDirectory($path)` | Tạo thư mục |
| `move($from, $to)` / `copy($from, $to)` | Di chuyển / sao chép |
| `fileSize($path)` / `mimeType($path)` / `lastModified($path)` | Thông tin file |
| `publicUrl($path)` | URL công khai của file |
| `temporaryUrl($path, $expiresAt)` | URL tạm thời có hạn (chỉ S3) |
| `path($path)` | Đường dẫn tuyệt đối trên đĩa (chỉ disk `local`) |

```php
$url = Filesystem::publicUrl('avatars/1.jpg');
// local: http://localhost:9090/public/avatars/1.jpg

$url = Filesystem::disk('s3')->temporaryUrl('invoices/1.pdf', now()->addMinutes(15));
```

## 3. Lưu file người dùng upload

```php
use Ryra\Core\Http\Request;

#[Post(uri: 'avatar')]
public function upload(Request $request): JsonResponse
{
    $file = $request->file('avatar');   // ?UploadedFile

    // Cách 1: chuyển vào storage/public/avatars, tên file ngẫu nhiên
    $path = $file->move('avatars');                  // '/avatars/66f1c2a3b4d5e.jpg'
    $path = $file->move('avatars', 'user-1.jpg');    // tự đặt tên file

    // Cách 2: đẩy lên S3
    Filesystem::disk('s3')->writeStream(
        'avatars/' . uniqid() . '.' . $file->getClientOriginalExtension(),
        fopen($file->getPathname(), 'r'),
    );

    return Response::json(['url' => Filesystem::publicUrl($path)]);
}
```

Nên kiểm tra file trong DTO bằng các rule `IsFile`, `IsImage`, `IsMimeType`, `IsExtension`, `IsSize` (xem [dto.md](dto.md)), thay vì tự kiểm tra trong controller.

## 4. Ghi nhớ

- Đường dẫn trong các hàm đều tính **từ thư mục gốc của disk**, không phải từ thư mục gốc dự án.
- File trong `storage/public/` được Swoole phục vụ trực tiếp ở URL `/public/...`. Đừng lưu file nhạy cảm vào đây.
