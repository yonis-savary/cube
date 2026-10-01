<?php 

namespace Cube;

use Cube\Security\Encryption\Encryption;
use Cube\Security\Encryption\LegacyEncryption;

function encrypt(string $content, ?string $key=null): string {
    return Encryption::encrypt($content, $key);
}

function decrypt(string $encrypted, ?string $key=null): string
{
    return Encryption::supports($encrypted)
        ? Encryption::decrypt($encrypted, $key)
        : LegacyEncryption::decrypt($encrypted, $key);
}
