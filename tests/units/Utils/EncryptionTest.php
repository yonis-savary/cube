<?php

namespace Cube\Tests\Units\Utils;

use Cube\Security\Encryption\Encryption;
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
        $payload = base64_decode(substr(encrypt('{"admin":0}', 'app-key'), strlen(Encryption::PREFIX)));
        $payload[9] = chr(ord($payload[9]) ^ (ord('0') ^ ord('1')));

        $this->expectException(\Exception::class);
        decrypt(Encryption::PREFIX.base64_encode($payload), 'app-key');
    }

    public function testAnotherKeyCannotDecrypt()
    {
        $this->expectException(\Exception::class);
        decrypt(encrypt('order #1024', 'app-key'), 'another-key');
    }

    /** The raw key used to be cut at 32 bytes, so two keys sharing those bytes were the same key. */
    public function testTheWholeKeyIsUsed()
    {
        $sharedPrefix = str_repeat('k', 32);

        $this->expectException(\Exception::class);
        decrypt(encrypt('order #1024', $sharedPrefix.'-first'), $sharedPrefix.'-second');
    }

    public function testNewValuesAreVersioned()
    {
        $this->assertStringStartsWith(Encryption::PREFIX, encrypt('order #1024', 'app-key'));
    }

    public function testAValueEncryptedWithTheFormerFormatIsStillRead()
    {
        $iv = random_bytes(16);
        $legacy = base64_encode($iv.openssl_encrypt('order #1024', 'aes-256-cbc', 'app-key', OPENSSL_RAW_DATA, $iv));

        $this->assertEquals('order #1024', decrypt($legacy, 'app-key'));
    }

    /** A tampered new value must not get a second chance through the unauthenticated former format. */
    public function testATamperedValueIsNotRetriedAsALegacyOne()
    {
        $tampered = substr(encrypt('order #1024', 'app-key'), 0, -4).'AAAA';

        $this->expectException(\Exception::class);
        decrypt($tampered, 'app-key');
    }
}
