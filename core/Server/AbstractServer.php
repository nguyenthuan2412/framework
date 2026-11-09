<?php

namespace Ryra\Core\Server;

use Ryra\Core\Server\Attributes\Event;
use Swoole\Server as SwooleServer;
use Ryra\Core\Context\Context;
use ReflectionClass;
use Swoole\Server\Task;
use Ryra\Core\App;
use Ryra\Core\ErrorHandler\ErrorRenderer\CliErrorRenderer;
use Ryra\Core\Log\Log;

use function Termwind\render;

abstract class AbstractServer
{
    protected SwooleServer $swooleServer;

    public function __construct(protected App $app, array $configs)
    {
        $this->swooleServer = $this->createServer($configs);

        $reflectionClass = new ReflectionClass($this);
        $methods = $reflectionClass->getMethods();
        foreach ($methods as $method) {
            $eventAttribute = $method->getAttributes(Event::class);
            if (!empty($eventAttribute)) {
                $event = $eventAttribute[0]->newInstance();
                $this->swooleServer->on((string) $event, [$this, $method->getName()]);
            }
        }
    }

    abstract protected function createServer(array $configs): SwooleServer;

    public function start(): bool
    {
        return $this->swooleServer->start();
    }

    public function task(string $task, mixed $data, int $taskWorkerIndex = -1): int|false
    {
        $this->ensureTaskable($task);
        return $this->swooleServer->task([$task, $data], $taskWorkerIndex);
    }

    public function taskWait(string $task, mixed $data, float $timeout = 0.5, int $taskWorkerIndex = -1): int|false
    {
        $this->ensureTaskable($task);
        return $this->swooleServer->taskwait([$task, $data], $timeout, $taskWorkerIndex);
    }

    public function taskWaitMulti(array $tasks, float $timeout = 0.5): bool|array
    {
        foreach ($tasks as $task) {
            $this->ensureTaskable($task);
        }
        return $this->swooleServer->taskWaitMulti($tasks, $timeout);
    }

    public function taskCoroutine(array $tasks, float $timeout = 0.5): bool|array
    {
        return $this->swooleServer->taskCo($tasks, $timeout);
    }

    public function workerId(): int
    {
        return $this->swooleServer->worker_id;
    }

    #[Event('Start')]
    public function onStart(): void
    {
        $url = App::baseUrl();
        \Termwind\render(<<<HTML
            <div>
                <div class="text-green">Start running on [$url]</div>
                <div class="font-bold text-yellow">Press Ctr+C to stop the server</div>
            </div>
        HTML);
    }

    #[Event('WorkerStart')]
    public function onWorkerStart(SwooleServer $server, int $workerId): void
    {
        try {
            App::service('module.installer')->install();
            // Build bảng route ngay khi đã đăng ký đủ route của mọi module (dispatcher là singleton,
            // nếu để request đầu tiên tạo thì có thể chốt bảng route khi install chưa xong).
            $this->app->get('route.dispatcher');
            \Termwind\render(<<<HTML
                <div>
                    <div class="text-green font-bold">
                        ✅ Worker #{$workerId} has started successfully!
                    </div>
                </div>
            HTML);
        } catch (\Throwable $th) {
            CliErrorRenderer::render($th);
        }
    }

    #[Event('Task')]
    public function onTask(SwooleServer $server, Task $task): void
    {
        [$task, $data] = $task->data;
        $task = $this->app->make($task);
        $task->process($data);
    }

    #[Event('ManagerStart')]
    public function onManagerStart(): void
    {
    }

    #[Event('AfterReload')]
    public function onAfterReload(): void
    {
        render('<div class="mt-1">Server has been restarted</div>');
    }

    protected function ensureTaskable(string $task): void
    {
        if (is_a($task, Taskable::class)) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Expected a Taskable, got %s',
                    is_object($task) ? get_class($task) : gettype($task)
                )
            );
        }
    }

    public function __call(string $method, array $args = []): mixed
    {
        return $this->swooleServer->{$method}(...$args);
    }
}