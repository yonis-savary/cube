<?php

namespace Cube\Tests\Units\Utils;

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
        $signed = SignedPayload::sign(serialize([1]), 'another-application-key');

        $this->assertNull(SignedPayload::verify($signed));
    }

    public function testSigningNeedsAKey()
    {
        $this->expectException(RuntimeException::class);

        Environment::withInstance(new Environment(null), fn () => SignedPayload::sign('payload'));
    }
}
