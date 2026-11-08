<?php

namespace Ryra\Core\Session;

use SessionHandlerInterface;
use Ryra\Core\Collection\Arr;

class Session
{
    protected bool $started = false;

    protected array $attributes = [];

    /**
     * Create a new session instance.
     */
    public function __construct(
        protected string $name,
        protected SessionHandlerInterface $handler,
        protected ?string $id = null,
        protected string $serialization = 'php'
    ) {
        if (is_null($id)) {
            $this->id = $this->randomString();
        }
    }

    /**
     * Start the session, reading the data from a handler.
     */
    public function start(): bool
    {
        $this->loadSession();

        $this->put('_flash.old', $this->get('_flash.new', []));
        $this->forget('_flash.new');

        $this->put('_errors.old', $this->get('_errors.new', []));
        $this->forget('_errors.new');

        if (!$this->has('_token')) {
            $this->regenerateToken();
        }

        return $this->started = true;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        if (Arr::has($this->attributes, $key)) {
            return Arr::get($this->attributes, $key, $default);
        }

        if (
            isset($this->attributes['_flash'])
            && Arr::has($this->attributes['_flash'], "old.$key")
        ) {
            return Arr::get($this->attributes['_flash'], "old.$key", $default);
        }

        return $default;
    }

    public function has(string|array $key): bool
    {
        return !collect(is_array($key) ? $key : [$key])->contains(function ($key) {
            return is_null($this->get($key));
        });
    }

    public function hasAny(string|array $key): bool
    {
        return collect(is_array($key) ? $key : func_get_args())->filter(function ($key) {
            return ! is_null($this->get($key));
        })->count() >= 1;
    }

    /**
     * Put a key / value pair or array of key / value pairs in the session.
     */
    public function put(string $key, mixed $value = null): void
    {
        if (!is_array($key)) {
            $keys = [$key => $value];
        }

        foreach ($keys as $arrayKey => $arrayValue) {
            Arr::set($this->attributes, $arrayKey, $arrayValue);
        }
    }

    public function pull(string $key, mixed $default = null): mixed
    {
        return Arr::pull($this->attributes, $key, $default);
    }

    public function forget(string $key): void
    {
        Arr::forget($this->attributes, [$key]);
    }

    /**
     * Push a value onto a session array.
     */
    public function push(string $key, mixed $value): void
    {
        $array = $this->get($key, []);
        $array[] = $value;
        $this->put($key, $array);
    }

    /**
     * Increment the value of an item in the session.
     */
    public function increment(string $key, $amount = 1): mixed
    {
        $this->put($key, $value = $this->get($key, 0) + $amount);
        return $value;
    }

    /**
     * Decrement the value of an item in the session.
     */
    public function decrement(string $key, int $amount = 1): mixed
    {
        return $this->increment($key, $amount * -1);
    }

    /**
     * Push a value onto a flash session array.
     */
    public function flash(string $key, mixed $value): void
    {
        $flash = $this->get('_flash.new', []);
        $flash[$key] = $value;
        $this->put('_flash.new', $flash);
    }

    public function hasOldInput($key = null): bool
    {
        $old = $this->getOldInput($key);
        return is_null($key) ? count($old) > 0 : ! is_null($old);
    }

    public function getOldInput(?string $field = null, mixed $default = null): mixed
    {
        return Arr::get($this->get('_old_input') ?? [], $field, $default);
    }

    public function flashInput(array $value): void
    {
        $this->flash('_old_input', $value);
    }

    public function getErrors(): array
    {
        return Arr::get($this->attributes['_errors'], 'old', []);
    }

    public function setErrors(array $errors): void
    {
        $this->put('_errors.new', $errors);
    }

    /**
     * Remove all of the items from the session.
     */
    public function flush(): void
    {
        $this->attributes = [];
    }

    public function save(): void
    {
        $this->forget('_flash.old');
        $this->forget('_errors.old');
        $this->handler->write($this->getId(), $this->prepareForStorage(
            $this->serialization === 'json' ? json_encode($this->attributes) : serialize($this->attributes)
        ));
        $this->started = false;
    }

    /**
     * Regenerate the CSRF token value.
     */
    public function regenerateToken(): string
    {
        $token = $this->randomString();
        $this->flash('_token', $token);
        return $token;
    }

    public function getHandler(): SessionHandlerInterface
    {
        return $this->handler;
    }

    /**
     * Load the session data from the handler.
     *
     * @return void
     */
    protected function loadSession(): void
    {
        $this->attributes = array_replace($this->attributes, $this->readFromHandler());
    }

    /**
     * Read the session data from the handler.
     *
     * @return array
     */
    protected function readFromHandler(): array
    {
        if ($data = $this->handler->read($this->getId())) {
            if ($this->serialization === 'json') {
                $data = json_decode($this->prepareForUnserialize($data), true);
            } else {
                $data = unserialize($this->prepareForUnserialize($data));
            }

            if ($data !== false && is_array($data)) {
                return $data;
            }
        }

        return [];
    }

    /**
     * Prepare the raw string data from the session for unserialization.
     */
    protected function prepareForUnserialize(string $data): string
    {
        return $data;
    }

    /**
     * Prepare the serialized session data for storage.
     */
    protected function prepareForStorage(string $data): string
    {
        return $data;
    }

    /**
     * Determine if the session has been started.
     */
    public function isStarted(): bool
    {
        return $this->started;
    }

    /**
     * Get the name of the session.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Set the name of the session.
     */
    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function id(): string
    {
        return $this->getId();
    }

    public function getId(): string
    {
        return $this->id;
    }

    /**
     * Set the session ID.
     */
    public function setId(string $id): void
    {
        $this->id = $this->isValidId($id) ? $id : $this->randomString();
    }

    /**
     * Determine if this is a valid session ID.
     */
    public function isValidId(string $id): bool
    {
        return is_string($id) && ctype_alnum(text: $id) && strlen($id) === 40;
    }

    public function setCurrentUrl(string $url): void
    {
        $histories = $this->get('_histories', []);
        if (count($histories) === 10) {
            array_shift($histories);
        }
        $histories[] = $url;
        $this->put('_histories', $histories);
    }

    public function getPreviousUrl(int $number = 1): ?string
    {
        $number += 1;
        $histories = $this->get('_histories', []);
        return empty($histories[count($histories) - $number]) ? null : $histories[count($histories) - $number];
    }

    protected function randomString(): string
    {
        $string = '';
        $length = 40;
        while (($len = strlen($string)) < $length) {
            $size = $length - $len;
            $bytesSize = (int) ceil($size / 3) * 3;
            $bytes = random_bytes($bytesSize);
            $string .= substr(str_replace(['/', '+', '='], '', base64_encode($bytes)), 0, $size);
        }
        return $string;
    }
}