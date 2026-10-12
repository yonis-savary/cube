<?php 

namespace Cube;

use Cube\Security\Encryption\Encryption;

function encrypt(string $content, ?string $key=null): string {
    return Encryption::encrypt($content, $key);
}

function decrypt(string $encrypted, ?string $key=null): string
{
    return Encryption::decrypt($encrypted, $key);
}
