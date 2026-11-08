<?php

namespace Ryra\Core\Excel\Concerns;

interface WithEvents
{
    /**
     * Return an array of event class name => callable to register listeners.
     *
     * Example:
     *   use Ryra\Core\Excel\Events\AfterSheet;
     *
     *   public function registerEvents(): array
     *   {
     *       return [
     *           AfterSheet::class => function (AfterSheet $event) {
     *               $event->sheet->getDelegate()->setTitle('My Sheet');
     *           },
     *       ];
     *   }
     *
     * @return array<class-string, callable>
     */
    public function registerEvents(): array;
}
