<?php

namespace Ryra\Core\Database\Model;

use Ryra\Core\Collection\Collection as BaseCollection;

class Collection extends BaseCollection
{
    public function toQuery(): Builder
    {
        /**
         * @var Model
         */
        $model = $this->first();
        if (!$model) {
            throw new \LogicException('Unable to create query for empty collection.');
        }

        return $model->newModelQuery()->whereIn(
            $model->getKeyName(),
            array_map(fn ($model) => $model->getKey(), $this->items)
        );
    }

    public function append(mixed $fields): static
    {
        $this->each(static fn (Model $model) => $model->append($fields));
        return $this;
    }

    public function delete(): int
    {
        $first = $this->first();
        if (\is_null($first)) {
            return 0;
        }

        $ids = $this->pluck($first->getKeyName());
        return $first->whereIn($first->getKeyName(), $ids)->delete();
    }

    public function update(iterable $fields): int
    {
        $first = $this->first();
        if (\is_null($first)) {
            return 0;
        }

        $ids = $this->pluck($first->getKeyName());
        return $first->whereIn($first->getKeyName(), $ids)->update($fields);
    }
}