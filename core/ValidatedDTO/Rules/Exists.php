<?php

namespace Ryra\Core\ValidatedDTO\Rules;

use Attribute;
use Ryra\Core\Database\DB;
use Ryra\Core\ValidatedDTO\Property;
use Ryra\Core\ValidatedDTO\Rule;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Exists extends Rule
{
    public function __construct(
        protected string $tableOrModel,
        protected string $field = 'id',
        protected ?string $where = null,
        protected ?string $connection = null,
        protected string $message = 'The value of :attribute does not exist.'
    ) {}

    public function check(mixed &$value, array &$values, Property $property): bool
    {
        if (\class_exists($this->tableOrModel)) {
            $model = $this->tableOrModel;
            $query = (new $model())->query();
        } else {
            $query = DB::connection($this->connection)
                ->query()
                ->from($this->tableOrModel);
        }
        $query->where($this->field, $value);

        if ($this->where) {
            $schema = $this->getSchema();
            $schema::{$this->where}($query, $value, $values, $property);
        }

        return $query->exists();
    }
}
