<?php

namespace Ryra\Core\Watcher\Drivers;

use Swoole\Timer;
use Ryra\Core\Watcher\Event;
use function Swoole\Coroutine\run;

class Polling implements DriverInterface
{
    protected array $resource = [];
    protected array $events = [];
    protected array $paths = [];

    /**
     * Interval tính bằng millisecond
     */
    protected int $interval = 1000;

    protected bool $running = false;

    public function on(Event|array $events, callable $callback): static
    {
        $events = is_array($events) ? $events : [$events];

        foreach ($events as $event) {
            $this->events[$event->value][] = $callback;
        }

        return $this;
    }

    public function add(string $path, ?string $regex = null): static
    {
        $real = realpath($path);

        if ($real && is_dir($real)) {
            $this->paths[$real] = $regex;
        }

        return $this;
    }

    public function setTimeInterval(int $interval): static
    {
        $this->interval = max(200, $interval); // tránh quá thấp gây CPU spike
        return $this;
    }

    protected function iterateFiles(callable $callback): void
    {
        foreach ($this->paths as $path => $regex) {

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator(
                    $path,
                    \FilesystemIterator::SKIP_DOTS
                    | \FilesystemIterator::CURRENT_AS_FILEINFO
                ),
                \RecursiveIteratorIterator::LEAVES_ONLY
            );

            foreach ($iterator as $file) {

                if (!$file->isFile()) {
                    continue;
                }

                $filePath = $file->getPathname();

                if ($regex && !preg_match("/{$regex}/", $filePath)) {
                    continue;
                }

                $callback($filePath);
            }
        }
    }

    protected function detectEvent(string $filePath): ?Event
    {
        $mTime = $this->getMTime($filePath);

        if (!isset($this->resource[$filePath])) {
            $this->resource[$filePath] = $mTime;
            return Event::ADD;
        }

        if ($this->resource[$filePath] < $mTime) {
            $this->resource[$filePath] = $mTime;
            return Event::CHANGE;
        }

        return null;
    }

    protected function fireEvent(Event $event, ?string $filePath = null): void
    {
        if (!empty($this->events[$event->value])) {
            foreach ($this->events[$event->value] as $callback) {
                $callback($event, $filePath);
            }
        }

        if (!empty($this->events[Event::ALL->value])) {
            foreach ($this->events[Event::ALL->value] as $callback) {
                $callback($event, $filePath);
            }
        }
    }

    protected function handleCheck(): void
    {
        // Xoá stat cache 1 lần đầu mỗi tick (ngoài vòng lặp file) để filemtime
        // luôn đọc mtime từ đĩa, không phụ thuộc số lượng file đang watch.
        clearstatcache();

        $currentFiles = [];

        // Check new & changed
        $this->iterateFiles(function (string $filePath) use (&$currentFiles) {
            $currentFiles[$filePath] = true;

            $event = $this->detectEvent($filePath);
            if ($event) {
                $this->fireEvent($event, $filePath);
            }
        });

        // Check deleted
        foreach (array_keys($this->resource) as $filePath) {
            if (!isset($currentFiles[$filePath])) {
                unset($this->resource[$filePath]);
                $this->fireEvent(Event::UNLINK, $filePath);
            }
        }
    }

    public function run(): void
    {
        run(function () {
            // Initial scan
            $this->iterateFiles(function (string $filePath) {
                $this->resource[$filePath] = $this->getMTime($filePath);
            });

            $this->fireEvent(Event::READY);

            Timer::tick($this->interval, function () {

                if ($this->running) {
                    return;
                }

                $this->running = true;

                try {
                    $this->handleCheck();
                } catch (\Throwable $e) {
                    echo "[Watcher Error] " . $e->getMessage() . PHP_EOL;
                }

                $this->running = false;
            });
        });
    }

    protected function getMTime(string $path): int
    {
        return @filemtime($path) ?: 0;
    }
}
