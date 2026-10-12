<?php 

namespace Cube;

use Cube\Security\Encryption\Encryption;

function encrypt(#[\SensitiveParameter] string $content, #[\SensitiveParameter] ?string $key=null): string {
    return Encryption::encrypt($content, $key);
}

function decrypt(string $encrypted, #[\SensitiveParameter] ?string $key=null): string
{
    return Encryption::decrypt($encrypted, $key);
}
