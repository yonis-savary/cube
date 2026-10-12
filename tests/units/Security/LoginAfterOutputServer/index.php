<?php

use Cube\Core\Autoloader;
use Cube\Security\Authentication;
use Cube\Security\Authentication\AuthenticationConfiguration;
use Cube\Security\Authentication\PasswordAuthentication;
use Cube\Tests\Units\Models\User;

require __DIR__.'/../../../../vendor/autoload.php';

Autoloader::initialize(realpath(__DIR__.'/../../../..'));

$authentication = new Authentication(new AuthenticationConfiguration(new PasswordAuthentication(User::class, 'login', 'password')));

echo 'output before the login, ';

try {
    $authentication->login(new User(['id' => 1, 'login' => 'alice', 'password' => '', 'type' => 1]));
    echo 'logged in';
} catch (RuntimeException) {
    echo 'refused';
}
