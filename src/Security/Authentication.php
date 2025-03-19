<?php

namespace Cube\Security;

use Cube\Core\Autoloader;
use Cube\Core\Component;
use Cube\Database\Database;
use Cube\Env\Session\HasScopedSession;
use Cube\Models\Model;
use Cube\Security\Authentication\AuthenticationConfiguration;
use Cube\Security\Authentication\Events\AuthenticatedUser;
use Cube\Security\Authentication\Events\FailedAuthentication;
use Cube\Utils\Path;
use Cube\Utils\Utils;

class Authentication
{
    use Component;
    use HasScopedSession;

    public const SESSION_USER_ID = 'session-user-id';
    public const SESSION_USER_DATA = 'session-user-data';

    /**
     * @param class-string<Model> $model
     */
    public function __construct(
        public readonly string $model,
        public readonly array|string $loginFields,
        public readonly string $passwordField,
        public readonly ?string $saltField = null,
        protected ?Database $database = null
    ) {
        if (!Autoloader::extends($model, Model::class)) {
            throw new \InvalidArgumentException("{$model} class does not extends Model");
        }

        $this->database ??= Database::getInstance();
    }

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

    public function getSessionKey(string $key): string
    {
        return md5(Path::relative($key));
    }

    public function saltString(string &$string, Model $user): void
    {
        if ($saltField = $this->saltField) {
            $string .= $user->{$saltField};
        }
    }

    public function attempt(string $login, string $userPassword): bool
    {
        $this->logout();

        $model = $this->model;

        $loginFields = Utils::toArray($this->loginFields);
        $query = $model::select();

        foreach ($loginFields as $field) {
            $query->where($field, $login, '=', $model::table());
        }

        if (!$user = $query->first()) {
            return false;
        }

        $passwordField = $this->passwordField;
        $hash = $user->{$passwordField};

        $this->saltString($userPassword, $user);

        if (!password_verify($userPassword, $hash)) {
            (new FailedAuthentication())->dispatch();

            return false;
        }

        $userPrimaryKey = $model::primaryKey();
        $this->login(
            $user->{$userPrimaryKey},
            $user->toArray()
        );

        (new AuthenticatedUser($user, $user->{$userPrimaryKey}))->dispatch();

        return true;
    }

    public function login(mixed $userId, ?array $userData = null): void
    {
        $session = $this->getSession();

        $userData ??= $this->model::find($userId)->data;

        $session->set($this->getSessionKey(self::SESSION_USER_DATA), $userData);
        $session->set($this->getSessionKey(self::SESSION_USER_ID), $userId);
    }

    public function logout(): void
    {
        $session = $this->getSession();

        $session->unset($this->getSessionKey(self::SESSION_USER_DATA));
        $session->unset($this->getSessionKey(self::SESSION_USER_ID));
    }

    public function isLogged(): bool
    {
        $session = $this->getSession();
        $userArrayData = $session->get($this->getSessionKey(self::SESSION_USER_DATA), false);

        return false != $userArrayData;
    }

    public function user(): Model
    {
        $session = $this->getSession();
        $userArrayData = $session->get($this->getSessionKey(self::SESSION_USER_DATA));
        $model = $this->model;

        if (!$this->isLogged()) {
            throw new \RuntimeException('Cannot retrieve data of unauthenticated user');
        }

        return new $model($userArrayData);
    }

    public function userId(): mixed
    {
        $session = $this->getSession();
        $userArrayData = $session->get($this->getSessionKey(self::SESSION_USER_DATA), false);

        if (!$this->isLogged()) {
            throw new \RuntimeException('Cannot retrieve data of unauthenticated user');
        }

        $primaryKey = $this->model::primaryKey();

        if (!array_key_exists($primaryKey, $userArrayData)) {
            throw new \RuntimeException("Could not retrieve {$primaryKey} key on logged user");
        }

        return $userArrayData[$primaryKey];
    }
}
