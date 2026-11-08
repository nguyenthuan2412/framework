<?php

namespace Ryra\Core\Excel\Concerns;

interface FromGenerator
{
    /**
     * Return a PHP Generator for memory-efficient, lazy data export.
     * The generator yields one row at a time — no full dataset is held in memory.
     *
     * Example:
     *   public function generator(): \Generator
     *   {
     *       foreach (User::cursor() as $user) {
     *           yield [$user->id, $user->name, $user->email];
     *       }
     *   }
     */
    public function generator(): \Generator;
}
