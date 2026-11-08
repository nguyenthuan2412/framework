<?php

namespace Ryra\Core\Datastar;

use Ryra\Core\BaseDTO\BaseDTO;
use Ryra\Core\Http\Request;
use Ryra\Core\ValidatedDTO\ValidatedDTO;

/**
 * Signals mà client Datastar gửi kèm mỗi request.
 *
 * Theo SDK spec: request GET nhận signals qua query param `datastar` (JSON đã
 * url-encode), các method còn lại nhận qua body JSON.
 *
 * @implements \IteratorAggregate<string, mixed>
 */
class Signals implements \IteratorAggregate, \Countable, \JsonSerializable
{
    /**
     * @param array<string, mixed> $signals
     */
    public function __construct(protected array $signals = []) {}

    /**
     * Đọc signals ra từ request hiện tại.
     */
    public static function fromRequest(Request $request, ?string $param = null): static
    {
        $param ??= (string) config('datastar.signals.param', Consts::DATASTAR_KEY);

        if (!$request->isMethod('GET')) {
            // Body JSON, đọc thẳng từ request gốc để không phụ thuộc content-type
            $body = $request->getSwooleRequest()?->rawContent();

            if (\is_string($body) && $body !== '' && \json_validate($body)) {
                $signals = \json_decode($body, true);

                if (\is_array($signals)) {
                    return new static($signals);
                }
            }

            // Body đã được framework parse sẵn khi content-type là application/json
            if ($request->request->all() !== []) {
                return new static($request->request->all());
            }
        }

        $raw = $request->query->get($param);

        if (!\is_string($raw) || $raw === '' || !\json_validate($raw)) {
            return new static();
        }

        $signals = \json_decode($raw, true);

        return new static(\is_array($signals) ? $signals : []);
    }

    /**
     * Lấy signal theo đường dẫn dạng chấm: `filters.keyword`.
     */
    public function get(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->signals;
        }

        $value = $this->signals;

        foreach (\explode('.', $key) as $segment) {
            if (!\is_array($value) || !\array_key_exists($segment, $value)) {
                return $default;
            }

            $value = $value[$segment];
        }

        return $value;
    }

    public function has(string $key): bool
    {
        return $this->get($key, $this) !== $this;
    }

    public function string(string $key, ?string $default = null): ?string
    {
        $value = $this->get($key, $default);

        return $value === null ? null : (string) $value;
    }

    public function integer(string $key, ?int $default = null): ?int
    {
        $value = $this->get($key, $default);

        return $value === null ? null : (int) $value;
    }

    public function float(string $key, ?float $default = null): ?float
    {
        $value = $this->get($key, $default);

        return $value === null ? null : (float) $value;
    }

    public function boolean(string $key, ?bool $default = null): ?bool
    {
        $value = $this->get($key, $default);

        if ($value === null) {
            return null;
        }

        return \filter_var($value, \FILTER_VALIDATE_BOOL, \FILTER_NULL_ON_FAILURE) ?? (bool) $value;
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->signals;
    }

    /**
     * Chỉ lấy một vài signal.
     *
     * @param string[] $keys
     * @return array<string, mixed>
     */
    public function only(array $keys): array
    {
        $result = [];

        foreach ($keys as $key) {
            $result[$key] = $this->get($key);
        }

        return $result;
    }

    /**
     * Đổ signals vào DTO của framework để validate như dữ liệu form thường.
     *
     * @template T of BaseDTO|ValidatedDTO
     * @param class-string<T> $dto
     * @return T
     */
    public function toDTO(string $dto, bool $validate = true): BaseDTO|ValidatedDTO
    {
        if (\is_subclass_of($dto, ValidatedDTO::class)) {
            return $dto::fromArray($this->signals, $validate);
        }

        return $dto::fromArray($this->signals);
    }

    public function isEmpty(): bool
    {
        return $this->signals === [];
    }

    public function count(): int
    {
        return \count($this->signals);
    }

    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->signals);
    }

    public function jsonSerialize(): array
    {
        return $this->signals;
    }

    public function __toString(): string
    {
        return \json_encode($this->signals, \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE);
    }
}
