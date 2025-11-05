<?php 

namespace Cube\Security\Authentication;

use Cube\Core\Autoloader;
use Cube\Data\Models\Model;
use Cube\Security\Authentication\Events\FailedAuthentication;
use Cube\Utils\Utils;

class PasswordAuthentication implements AuthenticationProvider
{
    /**
     * @param class-string<Model> $model
     */
    public function __construct(
        public readonly string $model,
        public readonly array|string $loginFields,
        public readonly string $passwordField,
        public readonly ?string $saltField = null
    ) {
        if (!Autoloader::extends($model, Model::class)) {
            throw new \InvalidArgumentException("{$model} class does not extends Model");
        }
    }

    public function attempt(string $identifier, ?string $password = null): Model|false
    {
        $model = $this->model;

        $loginFields = Utils::toArray($this->loginFields);
        $query = $model::select();

        foreach ($loginFields as $field) {
            $query->where($field, $identifier, '=', $model::table())->or();
        }

        if (!$user = $query->first()) {
            return false;
        }

        $passwordField = $this->passwordField;
        $hash = $user->{$passwordField};

        $this->saltString($password, $user);

        if (!password_verify($password, $hash)) {
            (new FailedAuthentication())->dispatch();

            return false;
        }

        return $user;
    }

    public function userById(mixed $id): Model|false 
    {
        $model = $this->model;
        return $model::find($id) ?? false;
    }

    public function saltString(string &$string, Model $user): void
    {
        if ($saltField = $this->saltField) {
            $string .= $user->{$saltField};
        }
    }
}