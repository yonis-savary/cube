<?php 

namespace Cube\Security\Authentication;

use Cube\Core\Autoloader;
use Cube\Data\Models\Model;
use Cube\Utils\Utils;

class PasswordAuthentication implements AuthenticationProvider
{
    // Hash of a random value nobody knows, with PHP's default bcrypt cost : an unknown login costs one verification, like a known one
    protected const DECOY_HASH = '$2y$12$vBUoAnEM4IjmK9UXG/XQ0us4Q8iTt15U1gV4HPqSVC.58o6tWl0Za';

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

    public function attempt(string $identifier, #[\SensitiveParameter] ?string $password = null): Model|false
    {
        $model = $this->model;
        $password ??= '';

        $loginFields = Utils::toArray($this->loginFields);
        $query = $model::select();

        foreach ($loginFields as $field) {
            $query->where($field, $identifier, '=', $model::table())->or();
        }

        $candidates = $query->limit(2)->fetch();
        if (1 !== count($candidates)) {
            password_verify($password, self::DECOY_HASH);
            return false;
        }

        $user = $candidates[0];

        $this->saltString($password, $user);

        $storedHash = (string) $user->{$this->passwordField};
        if ('' === $storedHash) {
            password_verify($password, self::DECOY_HASH);
            return false;
        }

        if (!password_verify($password, $storedHash)) {
            return false;
        }

        return $this->withoutCredentials($user);
    }

    public function userById(mixed $id): Model|false 
    {
        $model = $this->model;

        if (!$user = $model::find($id))
            return false;

        return $this->withoutCredentials($user);
    }

    public function getCredentialFields(): array
    {
        return array_values(array_filter([$this->passwordField, $this->saltField]));
    }

    protected function withoutCredentials(Model $user): Model
    {
        foreach ($this->getCredentialFields() as $field)
            unset($user->data->{$field});

        return $user;
    }

    public function saltString(string &$string, Model $user): void
    {
        if ($saltField = $this->saltField) {
            $string .= $user->{$saltField};
        }
    }
}