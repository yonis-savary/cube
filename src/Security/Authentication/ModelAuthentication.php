<?php

namespace Cube\Security\Authentication;

use Cube\Core\Autoloader;
use Cube\Data\Models\Model;

/** Finds users by primary key and checks nothing */
class ModelAuthentication implements AuthenticationProvider
{
    /** @param class-string<Model> $model */
    public function __construct(
        public readonly string $model
    ) {
        if (!Autoloader::extends($model, Model::class)) {
            throw new \InvalidArgumentException("{$model} class does not extends Model");
        }
    }

    public function userById(mixed $id): false|Model
    {
        return $this->model::find($id) ?? false;
    }

    public function getCredentialFields(): array
    {
        return [];
    }
}
