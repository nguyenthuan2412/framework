<?php

namespace Ryra\Core\Database\Model\Traits;

use Ryra\Core\Database\Model\Relations\HasOne;
use Ryra\Core\Database\Model\Relations\Relation;
use ReflectionMethod;

trait HasRelationships
{
    protected array $relations = [];

    protected function registerRelationships()
    {
        $methods = $this->getReflectionMethodsByReturnType(Relation::class);
        $methods = array_filter($methods, fn (ReflectionMethod $method) => $method->getModifiers() == ReflectionMethod::IS_PUBLIC);
        $this->relations = array_map(function (ReflectionMethod $method) {
            return $method->getName();
        }, $methods);
    }

    protected function hasOne(string $relatedModel, string $foreignKey, string $primaryKey): HasOne
    {
        $relatedModel = new $relatedModel();
        return new HasOne($relatedModel->newModelQuery(), $this, $foreignKey, $primaryKey);
    }

    protected function hasMany(string $relatedModel, string $foreignKey, string $primaryKey): HasOne
    {
        $relatedModel = new $relatedModel();
        return new HasMany($relatedModel->newModelQuery(), $this, $foreignKey, $primaryKey);
    }

    protected function belongsTo(string $relatedModel, string $foreignKey, string $primaryKey): BelongsTo
    {
        $relatedModel = new $relatedModel();
        return new BelongsTo($relatedModel->newModelQuery(), $this, $foreignKey, $primaryKey);
    }

    // protected function belongsTo(string $relatedModel, string $foreignKey, string $primaryKey): BelongsTo
    // {
    //     $relatedModel = new $relatedModel();
    //     return new BelongsTo($relatedModel->newModelQuery(), $this, $foreignKey, $primaryKey);
    // }

    public function with()
    {

    }
}