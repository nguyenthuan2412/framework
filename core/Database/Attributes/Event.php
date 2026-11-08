<?php

namespace Ryra\Core\Database\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class Event
{
    const CREATING = 'creating';
    const CREATED = 'created';
    
    const UPDATING = 'updating';
    const UPDATED = 'updated';

    const SAVING = 'saving';
    const SAVED = 'saved';

    const DELETING = 'deleting';
    const DELETED = 'deleted';

    public function __construct(protected string $event) {}

    public function __toString(): string
    {
        return $this->event;
    }
}