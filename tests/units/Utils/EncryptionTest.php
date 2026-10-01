<?php

namespace Cube\Tests\Units\Utils;

use PHPUnit\Framework\TestCase;

use function Cube\decrypt;
use function Cube\encrypt;

class EncryptionTest extends TestCase
{
    public function testEncryptThenDecrypt()
    {
        $this->assertEquals('order #1024', decrypt(encrypt('order #1024', 'app-key'), 'app-key'));
    }

    /** decrypt() tests its result for truthiness, so a "0" or empty plaintext is taken for a failure. */
    public function testFalsyPlaintextsSurviveARoundTrip()
    {
        $this->assertSame('0', decrypt(encrypt('0', 'app-key'), 'app-key'));
        $this->assertSame('', decrypt(encrypt('', 'app-key'), 'app-key'));
    }

    /** AES-CBC carries no authentication tag, so a flipped IV bit silently changes the plaintext. */
    public function testATamperedCiphertextIsRejected()
    {
        $payload = base64_decode(encrypt('{"admin":0}', 'app-key'));
        $payload[9] = chr(ord($payload[9]) ^ (ord('0') ^ ord('1')));

        $this->expectException(\Exception::class);
        decrypt(base64_encode($payload), 'app-key');
    }
}
