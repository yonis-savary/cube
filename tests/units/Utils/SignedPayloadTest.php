<?php

namespace Cube\Tests\Units\Utils;

use Cube\Console\Commands\Key\Generate;
use Cube\Env\Environment;
use Cube\Utils\SignedPayload;
use PHPUnit\Framework\TestCase;

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
        $this->expectException(\RuntimeException::class);

        Environment::withInstance(new Environment(null), fn () => SignedPayload::sign('payload'));
    }

    /** Any non-empty key was accepted : a derivation does not make `secret` any harder to guess. */
    public function testAShortKeyIsRefused()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('at least 32 are expected');

        SignedPayload::sign('payload', 'c2VjcmV0==');
    }

    /** RedisCache and RedisQueue hold values signed before expirations existed : they must stay readable. */
    public function testAPayloadSignedBeforeExpirationsExistedIsStillGivenBack()
    {
        $key = 'an-application-key-of-at-least-32-bytes';
        $legacy = hash_hmac('sha256', 'payload', hash_hkdf('sha256', $key, 32, 'cube-signed-payload')).'payload';

        $this->assertSame('payload', SignedPayload::verify($legacy, $key));
    }

    public function testAnExpiringPayloadIsGivenBackBeforeItExpires()
    {
        $this->assertSame('payload', SignedPayload::verify(SignedPayload::sign('payload', expiresAt: time() + 60)));
        $this->assertSame('payload', SignedPayload::verify(SignedPayload::sign('payload', expiresAt: new \DateTimeImmutable('+1 hour'))));
    }

    public function testAnExpiredPayloadIsRefused()
    {
        $this->assertNull(SignedPayload::verify(SignedPayload::sign('payload', expiresAt: time() - 1)));
        $this->assertNull(SignedPayload::verify(SignedPayload::sign('payload', expiresAt: time())));
    }

    public function testAnExtendedExpirationIsRefused()
    {
        $signed = SignedPayload::sign('payload', expiresAt: time() + 60);
        $extended = substr($signed, 0, 64).(time() + 3600).substr($signed, 64 + strlen((string) (time() + 60)));

        $this->assertNull(SignedPayload::verify($extended));
    }

    /** Without separate derivations, a permanent signature of "<future timestamp>.x" would read as an expiring one, and the opposite. */
    public function testPermanentAndExpiringSignaturesCannotBeSwapped()
    {
        $expiring = SignedPayload::sign('payload', expiresAt: time() + 60);
        $permanent = SignedPayload::sign((time() + 60).'.payload');

        $this->assertSame((time() + 60).'.payload', SignedPayload::verify($permanent));
        $this->assertNotEquals(substr($expiring, 0, 64), substr($permanent, 0, 64));
    }

    public function testAGeneratedKeyIsAccepted()
    {
        $key = Generate::newKey();

        $this->assertSame('payload', SignedPayload::verify(SignedPayload::sign('payload', $key), $key));
        $this->assertNotEquals($key, Generate::newKey());
    }
}
