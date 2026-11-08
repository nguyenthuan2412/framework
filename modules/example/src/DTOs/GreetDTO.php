<?php

namespace Ryra\Modules\Example\DTOs;

use Ryra\Core\ValidatedDTO\Rules\IsRequired;
use Ryra\Core\ValidatedDTO\Rules\IsString;
use Ryra\Core\ValidatedDTO\Rules\Max;
use Ryra\Core\ValidatedDTO\ValidatedDTO;

class GreetDTO extends ValidatedDTO
{
    #[IsRequired]
    #[IsString]
    #[Max(50)]
    public $name;
}
