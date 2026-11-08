<?php

namespace Ryra\Core\Support;

use Ryra\Core\Http\Response;

interface Responsable
{
    public function toResponse(): Response;
}