<?php

namespace YonisSavary\Cube\Security;

use InvalidArgumentException;
use YonisSavary\Cube\Core\Autoloader;
use YonisSavary\Cube\Core\Component;
use YonisSavary\Cube\Database\Database;
use YonisSavary\Cube\Env\Session;
use YonisSavary\Cube\Env\Session\HasScopedSession;
use YonisSavary\Cube\Models\Model;
use YonisSavary\Cube\Security\Authentication\AuthenticationConfiguration;
use YonisSavary\Cube\Utils\Path;
use YonisSavary\Cube\Utils\Utils;

class Authentication
{
    use Component;
    use HasScopedSession;

    const SESSION_USER_ID = "session-user-id";
    const SESSION_USER_DATA = "session-user-data";

    public static function getDefaultInstance(): static
    {
        $config = AuthenticationConfiguration::resolve();
        return new self(
            $config->model,
            $config->loginFields,
            $config->passwordField,
            $config->saltField
        );
    }

    /**
     * @param class-string<Model> $model
     */
    public function __construct(
        public readonly string $model,
        public readonly string|array $loginFields,
        public readonly string $passwordField,
        public readonly ?string $saltField=null,
        protected ?Database $database=null
    )
    {
        if (!Autoloader::extends($model, Model::class))
            throw new InvalidArgumentException("$model class does not extends Model");

        $this->database ??= Database::getInstance();
    }

    public function getSessionKey(string $key): string
    {
        return md5(Path::relative($key));
    }

    public function saltString(string &$string, Model $user): void
    {
        if ($saltField = $this->saltField)
            $string .= $user->$saltField;
    }

    public function attempt(string $login, string $userPassword): bool
    {
        $this->logout();

        $model = $this->model;

        $loginFields = Utils::toArray($this->loginFields);
        $query = $model::select();

        foreach ($loginFields as $field)
            $query->where($field, $login, "=", $model::table());

        if (! $user = $query->first())
            return false;

        $passwordField = $this->passwordField;
        $hash = $user->$passwordField;

        $this->saltString($userPassword, $user);

        if (!password_verify($userPassword, $hash))
            return false;

        $userPrimaryKey = $model::primaryKey();
        $this->login(
            $user->$userPrimaryKey,
            $user->toArray()
        );
        return true;
    }

    public function login(mixed $userId, array $userData): void
    {
        $session = $this->getSession();

        $session->set($this->getSessionKey(self::SESSION_USER_DATA), $userData);
        $session->set($this->getSessionKey(self::SESSION_USER_ID), $userId);
    }

    public function logout(): void
    {
        $session = $this->getSession();

        $session->unset($this->getSessionKey(self::SESSION_USER_DATA));
        $session->unset($this->getSessionKey(self::SESSION_USER_ID));
    }
}