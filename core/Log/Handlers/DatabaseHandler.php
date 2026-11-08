<?php

namespace Ryra\Core\Log\Handlers;

use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;
use Ryra\Core\Database\DB;

class DatabaseHandler extends AbstractProcessingHandler
{
    protected string $table;

    protected string $connection;

    public function __construct(int|string|Level $level = Level::Debug, bool $bubble = true)
    {
        parent::__construct($level, $bubble);
    }

    protected function write(LogRecord $record): void
    {
        DB::connection($this->connection)
            ->query()
            ->table($this->table)
            ->insert([
                'instance' => gethostname(),
                'message' => $record['message'],
                'channel' => $record['channel'],
                'level' => $record['level'],
                'level_name' => $record['level_name'],
                'context' => json_encode($record['context']),
                'created_at'  => $record['datetime']->format('Y-m-d H:i:s')
            ]);
    }
}