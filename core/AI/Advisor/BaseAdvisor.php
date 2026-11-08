<?php

namespace Ryra\Core\AI\Advisor;

use Ryra\Core\Contracts\AI\Advisor;

abstract class BaseAdvisor implements Advisor
{
    protected int $order = 100;

    public function order(): int
    {
        return $this->order;
    }

    public function withOrder(int $order): static
    {
        $this->order = $order;

        return $this;
    }
}
