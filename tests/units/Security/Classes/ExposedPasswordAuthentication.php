<?php

namespace Cube\Tests\Units\Security\Classes;

use Cube\Security\Authentication\PasswordAuthentication;

/**
 * Opens the decoy hash so a test can check what it is made of, without timing anything.
 */
class ExposedPasswordAuthentication extends PasswordAuthentication
{
    public static function publicDecoyHash(): string
    {
        return self::decoyHash();
    }
}
