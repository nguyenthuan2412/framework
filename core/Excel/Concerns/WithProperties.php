<?php

namespace Ryra\Core\Excel\Concerns;

interface WithProperties
{
    /**
     * Return spreadsheet document properties.
     *
     * Supported keys: creator, lastModifiedBy, title, description, subject, keywords, category, company, manager.
     *
     * @return array<string, string>
     */
    public function properties(): array;
}
