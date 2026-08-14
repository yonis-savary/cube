<?php 

namespace Cube\Security\Authentication;

use Cube\Core\Autoloader;
use Cube\Data\Models\Model;
use Cube\Utils\Utils;

class PasswordAuthentication implements AuthenticationProvider
{
    private static ?string $decoyHash = null;

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
        $password ??= '';

        $loginFields = Utils::toArray($this->loginFields);
        $query = $model::select();

        foreach ($loginFields as $field) {
            $query->where($field, $identifier, '=', $model::table())->or();
        }

        if (!$user = $query->first()) {
            password_verify($password, self::decoyHash());
            return false;
        }

        $this->saltString($password, $user);

        $storedHash = (string) $user->{$this->passwordField};
        if ('' === $storedHash) {
            password_verify($password, self::decoyHash());
            return false;
        }

        if (!password_verify($password, $storedHash)) {
            return false;
        }

        return $user;
    }

    /**
     * A hash of a value nobody can submit, built once per process with the algorithm
     * `password_hash()` currently defaults to, so verifying against it costs what verifying a
     * real password costs.
     */
    protected static function decoyHash(): string
    {
        return self::$decoyHash ??= password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT);
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