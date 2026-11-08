<?php

namespace Ryra\Core\Watcher\Drivers;

use Ryra\Core\Watcher\Exception\AddWatchException;
use Ryra\Core\Watcher\Event;
use FFI;

class Inotify implements DriverInterface
{
    protected FFI $ffi;

    protected int $instance;

    protected array $coreEvents = [
        'access' => 1,
        'modify' => 2,
        'attrib' => 4,
        'close_write' => 8,
        'close_nowrite' => 16,
        'open' => 32,
        'move_from' => 64,
        'move_to' => 128,
        'create' => 256,
        'delete' => 512,
        'delete_self' => 1024,
        'move_self' => 2048,
        'unmount' => 8192,
        'ignored' => 32768,
        'isdir' => 1073741824
    ];

    protected array $events = [];

    protected array $paths = [];

    protected array $watchDescriptors = [];

    public function __construct()
    {
        $this->ffi = FFI::cdef("
            #include <errno.h>
            extern int errno;
            int inotify_init();
            int inotify_add_watch(int fd, const char *pathname, uint32_t mask);
            int read(int fd, void *buf, size_t count);
            int inotify_rm_watch(int fd, int wd);
            int close(int fd);
            char *strerror(int errnum);
        ", "libc.so.6");

        $this->instance = $this->ffi->inotify_init();
    }

    public function on(Event|array $events, callable $callback): static
    {
        if ($events instanceof Event) {
            $events = [$events];
        }
        foreach ($events as $event) {
            if ($event instanceof Event) {
                if (empty($this->events[$event->value])) {
                    $this->events[$event->value] = [];
                }
                $this->events[$event->value][] = $callback;
            }
        }
        return $this;
    }

    public function add(string $path, ?string $regex = null): static
    {
        if (is_null($regex)) {
            $this->paths[] = $path;
        } else {
            $this->paths[] = [$path, $regex];
        }
        return $this;
    }

    protected function getStandardEventbyInotifyEvent(int $inotifyEvent, string $path): ?Event
    {
        if ($inotifyEvent & $this->coreEvents['modify']) {
            return Event::CHANGE;
        }

        $listEventAdd = [
            $this->coreEvents['create'],
            $this->coreEvents['move_to']
        ];
        foreach ($listEventAdd as $eventAdd) {
            if ($inotifyEvent & $eventAdd) {
                if ($inotifyEvent && is_dir($path)) {
                    return Event::ADD_DIR;
                }
    
                return Event::ADD;
            }
        }

        $listEventDelete = [
            $this->coreEvents['delete'],
            $this->coreEvents['delete_self'],
            $this->coreEvents['move_from'],
            $this->coreEvents['ignored'],
            $this->coreEvents['unmount'],
            $this->coreEvents['ignored']
        ];
        foreach ($listEventDelete as $eventDelete) {
            if ($inotifyEvent & $eventDelete) {
                if ($inotifyEvent && is_dir($path)) {
                    return Event::UNLINK_DIR;
                }
    
                return Event::UNLINK;
            }
        }

        return null;
    }

    protected function convertToInotifyEvent():int
    {
        $events = 1;
        foreach (array_keys($this->events) as $event) {
            switch ($event) {
                case Event::ADD->value:
                case Event::ADD_DIR->value:
                    $events |= $this->coreEvents['create'];
                    break;
                case Event::CHANGE->value:
                    $events |= $this->coreEvents['modify'];
                    break;
                case Event::UNLINK->value:
                case Event::UNLINK_DIR->value:
                    $events |= $this->coreEvents['delete'];
                    $events |= $this->coreEvents['delete_self'];
                    $events |= $this->coreEvents['move_from'];
                    $events |= $this->coreEvents['unmount'];
                    break;
            }
        }
        return $events;
    }

    protected function addWatch($path): void
    {
        $inotifyEvents = $this->convertToInotifyEvent();
        $watchDescriptor = $this->ffi->inotify_add_watch(
            $this->instance,
            $path,
            $inotifyEvents
        );

        if ($watchDescriptor < 0) {
            $errorMessage = FFI::string($this->ffi->strerror($this->ffi->errno));
            throw new AddWatchException("Failed to add watch for $path. Error: $errorMessage");
        }

        $this->watchDescriptors[$watchDescriptor] = $path;
    }

    protected function addWatchMultiFiles()
    {
        foreach ($this->paths as $path) {
            $regex = null;
            if (is_array($path)) {
                [$directory, $regex] = $path;
                $directory = new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS);
                $iterator = new \RecursiveIteratorIterator($directory, \RecursiveIteratorIterator::SELF_FIRST);
            } else {
                $directory = new \RecursiveDirectoryIterator($path);
                $iterator = new \RecursiveIteratorIterator($directory);
            }

            foreach ($iterator as $path) {
                if (
                    ($regex && preg_match("/$regex/", $path->getRealPath()))
                    || $path->isDir()
                ) {
                    $this->addWatch($path->getRealPath());
                }
            }
        }
    }

    protected function handleRemoveWatch(): void
    {
        foreach (array_keys($this->watchDescriptors) as $descriptor) {
            $this->ffi->inotify_rm_watch($this->instance, $descriptor);
        }
    }

    protected function isPathInDirectory(string $path, string $directory): bool
    {
        $realPath = realpath($path);
        $realDirectory = realpath($directory);
    
        if ($realPath === false || $realDirectory === false) {
            return false;
        }
    
        return str_starts_with($realPath, $realDirectory);
    }

    public function run(): void
    {
        $this->addWatchMultiFiles();

        $lastTriggerEvents = [];
        while (true) {
            $sizeBuffer = 4096;
            $buffer = FFI::new("char[$sizeBuffer]");
            $bytesRead = $this->ffi->read($this->instance, $buffer, $sizeBuffer);

            if ($bytesRead > 0) {
                $eventData = FFI::string($buffer, $bytesRead);
                $offset = 0;
                while ($offset < $bytesRead) {
                    // Unpack data
                    $data = unpack(
                        'iwd/Imask/Icookie/Ilen',
                        substr($eventData, $offset)
                    );
                    $offset += 16;
                    $path = '';
                    if ($data['len'] > 0) {
                        $path = trim(substr($eventData, $offset, $data['len']));
                        $offset += $data['len'];
                    }
                    $path = "{$this->watchDescriptors[$data['wd']]}/$path";
                    // Check path
                    foreach ($this->paths as $dir) {
                        $regex = null;
                        if (is_array($dir)) {
                            [$dir, $regex] = $dir;
                        }

                        if ($regex && is_file($path) && $this->isPathInDirectory($path, $dir) && !preg_match("/$regex/", $path)) {
                            break 2;
                        }
                    }

                    // Call event
                    $event = $this->getStandardEventbyInotifyEvent($data['mask'], $path);
                    if ($event == Event::ADD || $event == Event::ADD_DIR) {
                        $this->addWatch($path);
                    }

                    if (
                        !empty($event)
                        && (
                            empty($lastTriggerEvents[$event->value])
                            || $lastTriggerEvents[$event->value] !== time()
                        )
                    ) {
                        if (!empty($this->events[$event->value])) {
                            foreach ($this->events[$event->value] as $callable) {
                                $callable($event->value, $path);
                                $lastTriggerEvents[$event->value] = time();
                            }
                        }
                    }
                }
            }
        }

        $this->handleRemoveWatch();
        $this->ffi->close($this->instance);
    }
}