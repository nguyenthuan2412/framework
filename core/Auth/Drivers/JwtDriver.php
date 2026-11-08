<?php

namespace Ryra\Core\Auth\Drivers;

use Ryra\Core\Auth\Contracts\Authenticatable;
use Ryra\Core\Auth\Contracts\JwtSubject;
use Ryra\Core\Auth\Contracts\TokenGuard;
use Ryra\Core\Auth\Contracts\UserProvider;
use Ryra\Core\Auth\Exception\AuthenticationException;
use Ryra\Core\Auth\Exception\InvalidGuardException;
use Ryra\Core\Jwt\Blacklist\BlacklistInterface;
use Ryra\Core\Jwt\Config;
use Ryra\Core\Jwt\Exception\JWTException;
use Ryra\Core\Jwt\JWTManager;
use Ryra\Core\Jwt\Token;
use Ryra\Core\Support\Str;

/**
 * Xác thực bằng JSON Web Token.
 *
 * Token được lấy theo thứ tự: header "Authorization: Bearer", query string,
 * cuối cùng là cookie (nếu có cấu hình).
 */
class JwtDriver extends AbstractDriver implements TokenGuard
{
    protected ?Token $parsedToken = null;

    public function __construct(
        string $name,
        UserProvider $provider,
        array $config,
        protected JWTManager $jwt,
    ) {
        parent::__construct($name, $provider, $config);
    }

    protected function resolveUser(): ?Authenticatable
    {
        $token = $this->parseToken();

        if (\is_null($token)) {
            return null;
        }

        return $this->provider->retrieveById($token->relatedTo());
    }

    /**
     * Xác thực thông tin đăng nhập và phát hành token mới.
     */
    public function attempt(array $credentials = []): ?string
    {
        $user = $this->retrieveByCredentials($credentials);

        if (\is_null($user)) {
            return null;
        }

        return $this->login($user);
    }

    /**
     * Phát hành token cho một người dùng và ghi nhận là người dùng hiện tại.
     */
    public function login(Authenticatable $user): string
    {
        $this->setUser($user);

        return $this->issueToken($user);
    }

    /**
     * Phát hành token theo định danh người dùng.
     */
    public function loginUsingId(mixed $id): ?string
    {
        $user = $this->provider->retrieveById($id);

        return \is_null($user) ? null : $this->login($user);
    }

    /**
     * Thu hồi token của request hiện tại (chỉ có tác dụng khi bật blacklist).
     */
    public function logout(): void
    {
        if ($token = $this->parseToken()) {
            $this->blacklist()?->add($token);
        }

        $this->parsedToken = null;
        $this->forgetUser();
    }

    /**
     * Thu hồi token hiện tại và phát hành token mới cho cùng người dùng.
     *
     * @throws AuthenticationException
     */
    public function refresh(): string
    {
        $user = $this->authenticate();

        if ($token = $this->parseToken()) {
            $this->blacklist()?->add($token);
        }

        $this->parsedToken = null;

        return $this->issueToken($user);
    }

    /**
     * Token thô đang được gửi lên trong request hiện tại.
     */
    public function token(): ?string
    {
        return $this->tokenForRequest();
    }

    /**
     * Token đã được phân tích và kiểm tra hợp lệ.
     */
    public function payload(): ?Token
    {
        return $this->parseToken();
    }

    /**
     * Lấy claim từ token của request hiện tại.
     */
    public function claim(string $key, mixed $default = null): mixed
    {
        return $this->parseToken()?->get($key) ?? $default;
    }

    /**
     * Tạo chuỗi JWT cho người dùng.
     */
    protected function issueToken(Authenticatable $user): string
    {
        $claims = \array_merge(
            $this->config('claims', []),
            $user instanceof JwtSubject ? $user->getJwtCustomClaims() : [],
        );

        $subject = $user instanceof JwtSubject
            ? $user->getJwtIdentifier()
            : $user->getAuthIdentifier();

        $builder = $this->jwt->builder()
            ->relatedTo((string) $subject)
            ->identifiedBy(Str::random(32))
            ->issuedNow()
            ->expiresIn((int) $this->config('ttl', 3600))
            ->withClaims(\array_filter($claims, fn($value) => !\is_null($value)))
            ->signedWith($this->key(), (string) $this->config('alg', 'HS256'));

        if ($issuer = $this->config('issuer')) {
            $builder->issuedBy($issuer);
        }

        if ($audience = $this->config('audience')) {
            $builder->permittedFor($audience);
        }

        return $builder->toString();
    }

    /**
     * Phân tích token của request hiện tại, null nếu không có hoặc không hợp lệ.
     */
    protected function parseToken(): ?Token
    {
        if (!\is_null($this->parsedToken)) {
            return $this->parsedToken;
        }

        $token = $this->tokenForRequest();

        if (empty($token)) {
            return null;
        }

        // Dùng trực tiếp Jwt\Config để hỗ trợ audience dạng mảng.
        $config = new Config(
            $this->key(),
            (array) $this->config('alg', 'HS256'),
            $this->config('issuer'),
            $this->config('audience'),
            (int) $this->config('leeway', 0),
        );

        try {
            $parsed = $config->parse($token);
        } catch (JWTException | \TypeError | \ValueError) {
            // Token rác do client gửi lên: coi như chưa đăng nhập.
            return null;
        }

        if ($this->blacklist()?->has($parsed)) {
            return null;
        }

        if (!$this->claimsMatched($parsed)) {
            return null;
        }

        return $this->parsedToken = $parsed;
    }

    /**
     * Token phải chứa đủ các claim bắt buộc của guard (vd: role = admin).
     */
    protected function claimsMatched(Token $token): bool
    {
        foreach ((array) $this->config('claims', []) as $claim => $value) {
            if (\is_null($value)) {
                continue;
            }

            if ($token->get($claim) !== $value) {
                return false;
            }
        }

        return true;
    }

    /**
     * Lấy token thô từ request.
     */
    protected function tokenForRequest(): ?string
    {
        $request = $this->request();

        if ($token = $request->bearerToken()) {
            return $token;
        }

        if ($inputKey = $this->config('input_key')) {
            if ($token = $request->input($inputKey)) {
                return (string) $token;
            }
        }

        if ($cookie = $this->config('cookie')) {
            if ($token = $request->cookie($cookie)) {
                return (string) $token;
            }
        }

        return null;
    }

    /**
     * Danh sách token bị thu hồi, null nếu guard không bật blacklist.
     */
    protected function blacklist(): ?BlacklistInterface
    {
        if (!$this->config('blacklist', false)) {
            return null;
        }

        return $this->jwt->blacklist($this->config('blacklist_driver'));
    }

    /**
     * Khoá ký token.
     */
    protected function key(): string
    {
        $key = (string) $this->config('key', '');

        if ($key === '') {
            throw new InvalidGuardException(
                "Guard [{$this->name}] is missing a JWT signing key. Please set JWT_SECRET."
            );
        }

        return $key;
    }
}
