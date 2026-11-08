<?php

namespace Ryra\Core\Auth\Providers;

use Ryra\Core\Auth\Contracts\Authenticatable;
use Ryra\Core\Auth\Contracts\UserProvider;
use Ryra\Core\Auth\Exception\InvalidGuardException;
use Ryra\Core\Database\Model\Builder;
use Ryra\Core\Database\Model\Model;
use Ryra\Core\Hashing\Hasher\AbstractHasher;
use Ryra\Core\Hashing\Hasher\Argon2IdHasher;
use Ryra\Core\Hashing\Hasher\ArgonHasher;
use Ryra\Core\Hashing\Hasher\BcryptHasher;

/**
 * Lấy dữ liệu người dùng từ một model của ORM.
 */
class ModelUserProvider implements UserProvider
{
    /**
     * @var BcryptHasher|ArgonHasher|Argon2IdHasher|AbstractHasher
     */
    protected AbstractHasher $hasher;

    /**
     * @param class-string<Model> $model
     * @param BcryptHasher|ArgonHasher|Argon2IdHasher|AbstractHasher $hasher
     * @param array $conditions Điều kiện luôn được áp dụng, vd: ['deleted_at' => null]
     */
    public function __construct(
        protected string $model,
        AbstractHasher $hasher,
        protected array $conditions = [],
    ) {
        $this->hasher = $hasher;
    }

    public function retrieveById(mixed $identifier): ?Authenticatable
    {
        if (empty($identifier)) {
            return null;
        }

        $model = $this->createModel();

        return $this->newQuery()
            ->where($model->getAuthIdentifierName(), $identifier)
            ->first();
    }

    public function retrieveByCredentials(array $credentials): ?Authenticatable
    {
        $credentials = $this->withoutPassword($credentials);

        if (empty($credentials)) {
            return null;
        }

        $query = $this->newQuery();

        foreach ($credentials as $key => $value) {
            if (\is_array($value)) {
                $query->whereIn($key, $value);
            } elseif (\is_null($value)) {
                $query->whereNull($key);
            } else {
                $query->where($key, $value);
            }
        }

        return $query->first();
    }

    public function validateCredentials(Authenticatable $user, array $credentials): bool
    {
        $plain = $credentials['password'] ?? null;
        $hashed = $user->getAuthPassword();

        if (!\is_string($plain) || $plain === '' || empty($hashed)) {
            return false;
        }

        return $this->hasher->check($plain, $hashed);
    }

    public function rehashPasswordIfRequired(Authenticatable $user, array $credentials, bool $force = false): void
    {
        $plain = $credentials['password'] ?? null;
        $hashed = $user->getAuthPassword();

        if (!\is_string($plain) || $plain === '' || empty($hashed)) {
            return;
        }

        // AbstractHasher chỉ đảm bảo check(), các driver băm lại mới có make()/needsRehash().
        if (!\method_exists($this->hasher, 'make') || !\method_exists($this->hasher, 'needsRehash')) {
            return;
        }

        if (!$force && !$this->hasher->needsRehash($hashed)) {
            return;
        }

        $this->updateAuthAttribute($user, $user->getAuthPasswordName(), $this->hasher->make($plain));
    }

    public function updateAuthAttribute(Authenticatable $user, string $key, mixed $value): void
    {
        if (!$user instanceof Model) {
            throw new InvalidGuardException(\sprintf(
                'User [%s] must be an instance of %s to be updated by %s.',
                $user::class,
                Model::class,
                static::class
            ));
        }

        $user->update([$key => $value]);
    }

    /**
     * Model đang được sử dụng.
     */
    public function createModel(): Model&Authenticatable
    {
        $model = new $this->model;

        if (!$model instanceof Model || !$model instanceof Authenticatable) {
            throw new InvalidGuardException(\sprintf(
                'Model [%s] must extend %s and implement %s.',
                $this->model,
                Model::class,
                Authenticatable::class
            ));
        }

        return $model;
    }

    public function getModel(): string
    {
        return $this->model;
    }

    public function getHasher(): AbstractHasher
    {
        return $this->hasher;
    }

    /**
     * Query đã áp dụng sẵn các điều kiện bắt buộc của provider.
     */
    protected function newQuery(): Builder
    {
        $query = $this->createModel()->newModelQuery();

        foreach ($this->conditions as $key => $value) {
            \is_null($value) ? $query->whereNull($key) : $query->where($key, $value);
        }

        return $query;
    }

    /**
     * Loại bỏ các khoá liên quan tới mật khẩu khỏi điều kiện truy vấn.
     */
    protected function withoutPassword(array $credentials): array
    {
        return \array_filter(
            $credentials,
            fn(string $key) => !\str_contains($key, 'password'),
            ARRAY_FILTER_USE_KEY
        );
    }
}
