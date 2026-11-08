<?php

namespace Ryra\Core\ValidatedDTO\Rules;

use Attribute;
use Ryra\Core\Database\DB;
use Ryra\Core\ValidatedDTO\Rule;
use Ryra\Core\ValidatedDTO\Property;

#[Attribute(Attribute::TARGET_PROPERTY)]
class IsUnique extends Rule
{
    public function __construct(
        protected string $tableOrModel,
        protected ?string $field = null,
        protected ?string $where = null,
        protected ?string $connection = null,
        protected string $message = 'The :attribute has already been taken.'
    ) {}

    public function check(mixed &$value, array &$values, Property $property): bool
    {
        if (\is_null($this->field)) {
            $this->field = $property->name;
        }

        $table = $this->tableOrModel;
        if (\class_exists($this->tableOrModel)) {
            $model = $this->tableOrModel;
            $table = (new $model())->getTable();
        }

        $query = DB::connection($this->connection)->query()->from($table);
        $query = $query->where($this->field, $value);

        if ($this->where) {
            $schema = $this->getSchema();
            $where = $this->where;
            $schema::{$where}($query, $value, $values, $property);
        }

        return $query->doesntExist();
    }
}
