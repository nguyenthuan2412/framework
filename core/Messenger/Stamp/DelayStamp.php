<?php

namespace Ryra\Core\Messenger\Stamp;

use DateInterval;
use Ryra\Core\Date\Date;

class DelayStamp implements NonSendableStampInterface
{
    protected DateInterval $interval;

    public function __construct(int|DateInterval $interval)
    {
        $this->interval = $interval instanceof DateInterval ? $interval : new DateInterval("PT{$interval}S");
    }

    public function getDelay(): int
    {
        $start = Date::now();
        $end = (clone $start)->add($this->interval);
        return $end->getTimestamp() - $start->getTimestamp();
    }
}