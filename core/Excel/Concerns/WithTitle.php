<?php

namespace Ryra\Core\Excel\Concerns;

interface WithTitle
{
    /**
     * Return the worksheet tab title (max 31 characters, no special chars).
     */
    public function title(): string;
}
