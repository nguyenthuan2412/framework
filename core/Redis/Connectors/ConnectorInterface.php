<?php

namespace Ryra\Core\Redis\Connectors;

use Ryra\Core\Redis\Connections\Connection;

interface ConnectorInterface
{
    /**
     * Tạo kết nối Redis mới.
     */
    public function connect(array $config): Connection;
}
