<?php

namespace Ryra\Core\Auth\Middleware;

use Ryra\Core\Auth\AuthManager;
use Ryra\Core\Http\Request;
use Ryra\Core\Http\Response;

/**
 * Chặn request chưa đăng nhập.
 *
 * Do middleware được khai báo bằng tên class nên mỗi guard tạo một lớp con:
 *
 *     class AuthAdmin extends Authenticate
 *     {
 *         protected ?string $guard = 'admin';
 *     }
 */
class Authenticate
{
    /**
     * Guard được sử dụng, null là guard mặc định trong config.
     */
    protected ?string $guard = null;

    public function __construct(protected AuthManager $auth) {}

    public function handle(Request $request, callable $next): Response
    {
        $this->auth->guard($this->guard)->authenticate();

        // Các đoạn code phía sau chỉ cần gọi auth() là ra đúng guard này.
        $this->auth->shouldUse($this->guard);

        return $next($request);
    }
}
