<?php

namespace Ryra\Core\Coroutine;

use Ryra\Core\Coroutine\Exception\BatchException;
use Ryra\Core\Coroutine\Exception\RunningInNonCoroutineException;
use Ryra\Core\Coroutine\Exception\CoroutineDestroyedException;
use Ryra\Core\Coroutine\Exception\RuntimeException;
use Ryra\Core\ErrorHandler\Exception\DumperException;
use Ryra\Core\Context\Context;
use Swoole\Coroutine as SwooleCo;
use Swoole\Coroutine\WaitGroup;
use Ryra\Core\App;

class Coroutine
{
    /**
     * @var callable
     */
    private $callable;

    private ?int $id = null;

    public function __construct(callable $callable)
    {
        $this->callable = static::wrap($callable);
    }

    public static function create(callable $callable, mixed ...$data): static
    {
        $coroutine = new static($callable);
        $coroutine->execute(...$data);
        return $coroutine;
    }

    public static function batch(array $tasks, float $timeout = -1): array
    {
        $wg = new WaitGroup(count($tasks));
        $results = [];
        $exceptions = [];
        foreach ($tasks as $id => $task) {
            SwooleCo::create(static function () use ($wg, &$results, &$exceptions, $id, $task) {
                try {
                    $results[$id] = static::wrap($task)();
                } catch (\Throwable $th) {
                    $exceptions[$id] = $th;
                    $results[$id] = null;
                } finally {
                    $wg->done();
                }
            });
        }
        $wg->wait($timeout);

        if (!empty($exceptions)) {
            foreach ($exceptions as $e) {
                if ($e instanceof DumperException) {
                    throw $e;
                }
            }
            throw new BatchException($exceptions, $results);
        }

        return $results;
    }

    public static function parallel(int $numberCoroutine, callable $fn): void
    {
        $count = $numberCoroutine;
        $wg    = new WaitGroup($numberCoroutine);
        while ($count--) {
            SwooleCo::create(static function () use ($fn, $wg) {
                try {
                    static::wrap($fn)();
                } finally {
                    $wg->done();
                }
            });
        }
        $wg->wait();
    }

    public function execute(mixed ...$data): static
    {
        $this->id = SwooleCo::create($this->callable, ...$data);
        return $this;
    }

    public function getId(): int
    {
        if (is_null($this->id)) {
            throw new RuntimeException('Coroutine was not be executed.');
        }
        return $this->id;
    }

    public static function id(): int
    {
        return SwooleCo::getCid();
    }

    public static function pid(?int $id = null): int
    {
        if ($id !== null) {
            $cid = SwooleCo::getPcid($id);
            if ($cid === false) {
                throw new CoroutineDestroyedException(sprintf('Coroutine #%d has been destroyed.', $id));
            }
        } else {
            $cid = SwooleCo::getPcid();
        }
        if ($cid === false) {
            throw new RunningInNonCoroutineException('Non-Coroutine environment don\'t has parent coroutine id.');
        }
        return max(0, $cid);
    }

    public static function set(array $config): void
    {
        SwooleCo::set($config);
    }

    public static function defer(callable $callable): void
    {
        SwooleCo::defer($callable);
    }

    /**
     * Yield the current coroutine.
     */
    public static function yield(): mixed
    {
        return SwooleCo::yield();
    }

    /**
     * Resume the coroutine by coroutine Id.
     */
    public static function resumeById(int $id): mixed
    {
        return SwooleCo::resume($id);
    }

    /**
     * Get the coroutine stats.
     */
    public static function stats(): array
    {
        return SwooleCo::stats();
    }

    public static function exists(?int $id = null): bool
    {
        return SwooleCo::exists($id);
    }

    protected static function wrap(callable $callable): callable
    {
        return static function (mixed ...$args) use ($callable) {
            Context::copy(static::pid(), static::id());
            App::getInstance()->copyScopedInstances(static::pid(), static::id());
            static::defer(static function () {
                Context::delete();
                App::getInstance()->forgetScopedInstances();
            });
            return $callable(...$args);
        };
    }
}