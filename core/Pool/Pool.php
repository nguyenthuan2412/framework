<?php

namespace Ryra\Core\Pool;

use Ryra\Core\Contracts\Pool\ConnectionFactory;
use Ryra\Core\Contracts\Pool\PoolOption;
use Ryra\Core\Coroutine\Channel;
use Ryra\Core\Coroutine\Timer;
use Ryra\Core\Log\Log;
use RuntimeException;

class Pool implements \Ryra\Core\Contracts\Pool\Pool
{
    protected Channel $channel;

    protected int $currentConnections = 0;

    protected int $timerId = 0;
    
    public function __construct(
        protected ConnectionFactory $factory,
        protected PoolOption $option
    )
    {
        $this->channel = new Channel($option->getMaxConnections());
        if ($this->option->getHeartbeat() > 0) {
            $this->heartbeat();
        }
    }

    public function get(): object
    {
        $num = $this->getConnectionsInChannel();

        try {
            if ($num === 0 && $this->currentConnections < $this->option->getMaxConnections()) {
                ++$this->currentConnections;
                return $this->factory->create();
            }
        } catch (\Throwable $throwable) {
            --$this->currentConnections;
            throw $throwable;
        }

        $connection = $this->channel->pop($this->option->getWaitTimeout());
        if ($connection === false) {
            throw new RuntimeException('Connection pool exhausted. Cannot establish new connection before wait_timeout.');
        }

        return $connection;
    }

    public function release(object $connection): void
    {
        try {
            $this->factory->reset($connection);
        } catch (\Throwable $throwable) {
            // Connection hỏng tới mức không reset được thì không đẩy lại vào pool,
            // nhưng vẫn phải trả slot lại. Nếu không, mỗi lần như vậy pool mất đứt
            // một connection và sau max_connections lần thì cạn vĩnh viễn.
            $this->discard($connection);
            Log::error((string) $throwable);
            return;
        }

        if (!$this->channel->push($connection, $this->option->getWaitTimeout())) {
            $this->discard($connection);
        }
    }

    /**
     * Vứt bỏ một connection đã lấy ra khỏi channel và trả lại slot cho pool.
     *
     * Luôn giảm currentConnections kể cả khi destroy() ném, vì connection đó
     * chắc chắn không còn nằm trong channel nữa.
     */
    protected function discard(object $connection): void
    {
        try {
            $this->factory->destroy($connection);
        } catch (\Throwable $throwable) {
            Log::error((string) $throwable);
        } finally {
            --$this->currentConnections;
        }
    }

    public function getConnectionsInChannel(): int
    {
        return $this->channel->length();
    }

    public function getCurrentConnections(): int
    {
        return $this->currentConnections;
    }

    public function flush(): void
    {
        $num = $this->getConnectionsInChannel();

        if ($num > 0) {
            while ($this->currentConnections > $this->option->getMinConnections() && $conn = $this->channel->pop(0.001)) {
                try {
                    $conn->close();
                } catch (\Throwable $exception) {
                    Log::error((string) $exception);
                } finally {
                    --$this->currentConnections;
                    --$num;
                }

                if ($num <= 0) {
                    // Ignore connections queued during flushing.
                    break;
                }
            }
        }
    }

    /**
     * Kiểm tra và làm mới connection nếu cần.
     */
    protected function heartbeat(): void
    {
        $this->timerId = Timer::interval(function () {
            $connections = $this->getConnectionsInChannel();
            for ($i = 0; $i < $connections; $i++) {
                $connection = $this->channel->pop(0.001);
                if (!is_object($connection)) {
                    break;
                }

                try {
                    $elapsedSeconds = \time() - $this->factory->getLastQueryTime($connection);
                    $keep = $elapsedSeconds <= $this->option->getMaxIdleTime()
                        && $this->factory->ping($connection);
                } catch (\Throwable $th) {
                    $keep = false;
                    Log::error((string) $th);
                }

                // ping() là I/O nên có yield, coroutine khác kịp release và lấp đầy
                // channel. Đẩy không được thì phải bỏ hẳn, không thể để connection
                // rơi ra ngoài mà vẫn được tính vào currentConnections.
                if (!$keep || !$this->channel->push($connection, 0.001)) {
                    $this->discard($connection);
                }
            }

            try {
                while ($this->currentConnections < $this->option->getMinConnections()) {
                    $connection = $this->factory->create();
                    ++$this->currentConnections;
                    if (!$this->channel->push($connection, 0.001)) {
                        $this->discard($connection);
                        break;
                    }
                }
            } catch (\Throwable $th) {
                Log::error((string) $th);
            }
        }, $this->option->getHeartbeat() * 1000);
    }

    public function __destruct()
    {
        if ($this->timerId) {
            Timer::clear($this->timerId);
        }
    }
}