<?php

namespace Ryra\Core\Database\Query;

class PostgreBuilder extends Builder
{
    public function lockForUpdate(): static
    {
        return $this->lock('for update');
    }

    public function sharedLock(): static
    {
        return $this->lock('lock in share mode');
    }
}