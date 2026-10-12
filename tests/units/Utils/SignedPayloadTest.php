<?php

namespace Cube\Tests\Units\Utils;

use Cube\Console\Commands\Key\Generate;
use Cube\Env\Environment;
use Cube\Utils\SignedPayload;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * @internal
 */
class SignedPayloadTest extends TestCase
{
    public function testASignedPayloadIsGivenBack()
    {
        $payload = serialize(['some', 'job', 'arguments']);

        $this->assertSame($payload, SignedPayload::verify(SignedPayload::sign($payload)));
    }

    public function testATamperedPayloadIsRefused()
    {
        $signed = SignedPayload::sign(serialize([1]));

        $this->assertNull(SignedPayload::verify(substr($signed, 0, -2).'2;}'));
        $this->assertNull(SignedPayload::verify(serialize([1])));
        $this->assertNull(SignedPayload::verify(''));
    }

    public function testAPayloadSignedWithAnotherKeyIsRefused()
    {
        $signed = SignedPayload::sign(serialize([1]), 'another-application-key-of-32-bytes-or-more');

        $this->assertNull(SignedPayload::verify($signed));
    }

    public function testSigningNeedsAKey()
    {
        $this->expectException(RuntimeException::class);

        Environment::withInstance(new Environment(null), fn () => SignedPayload::sign('payload'));
    }

    /** Any non-empty key was accepted : a derivation does not make `secret` any harder to guess. */
    public function testAShortKeyIsRefused()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('at least 32 are expected');

        SignedPayload::sign('payload', 'c2VjcmV0==');
    }

    public function testAGeneratedKeyIsAccepted()
    {
        $key = Generate::newKey();

        $this->assertSame('payload', SignedPayload::verify(SignedPayload::sign('payload', $key), $key));
        $this->assertNotEquals($key, Generate::newKey());
    }
}
