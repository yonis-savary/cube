<?php

namespace Cube\Tests\Units\Utils;

use Cube\Env\Environment;
use Cube\Utils\Identifier;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class IdentifierTest extends TestCase
{
    public const UUID4_FORMAT = '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/';
    public const UUID6_FORMAT = '/^[0-9a-f]{8}-[0-9a-f]{4}-6[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/';

    public function testAnUuid4HasItsVersionAndVariant()
    {
        $this->assertMatchesRegularExpression(self::UUID4_FORMAT, Identifier::uuid4());
    }

    public function testUuid4sAreUnique()
    {
        $uuids = array_map(fn () => Identifier::uuid4(), range(1, 1000));

        $this->assertCount(1000, array_unique($uuids));
    }

    public function testAnUuid6HasItsVersionAndVariant()
    {
        $this->assertMatchesRegularExpression(self::UUID6_FORMAT, Identifier::uuid6());
    }

    /** Generated within one tick, they would collide or come out of order without the timestamp bump. */
    public function testUuid6sAreUniqueAndSortedInCreationOrder()
    {
        $uuids = array_map(fn () => Identifier::uuid6(), range(1, 1000));

        $sorted = $uuids;
        sort($sorted);

        $this->assertCount(1000, array_unique($uuids));
        $this->assertSame($uuids, $sorted);
    }

    public function testAnUuid6CarriesTheCurrentTime()
    {
        $hex = str_replace('-', '', Identifier::uuid6());
        $ticks = (hexdec(substr($hex, 0, 12)) << 12) | hexdec(substr($hex, 13, 3));
        $unixSeconds = intdiv($ticks - 0x01B21DD213814000, 10_000_000);

        $this->assertEqualsWithDelta(time(), $unixSeconds, 2);
    }

    public function testAnUuid6NodeIsNeverAHardwareAddress()
    {
        $node = substr(Identifier::uuid6(), 24, 2);

        $this->assertEquals(1, hexdec($node) & 0x01, 'The multicast bit of a random node must be set');
    }

    public function testAPrefixedIdentifierIsRecognizable()
    {
        $secret = Identifier::prefixed('whsec_');

        $this->assertMatchesRegularExpression('/^whsec_[A-Za-z0-9_-]{43}$/', $secret);
        $this->assertNotEquals($secret, Identifier::prefixed('whsec_'));
    }

    public function testAPrefixedIdentifierTakesItsLength()
    {
        $this->assertEquals(strlen('cus_') + 22, strlen(Identifier::prefixed('cus_', 16)));
    }

    public function testAPrefixedIdentifierNeedsEnoughRandomness()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('[8]');

        Identifier::prefixed('cus_', 8);
    }

    public function testAHashedSecretMatches()
    {
        $secret = Identifier::prefixed('whsec_');
        $hash = Identifier::hash($secret);

        $this->assertTrue(Identifier::matches($secret, $hash));
        $this->assertFalse(Identifier::matches(Identifier::prefixed('whsec_'), $hash));
        $this->assertNotEquals($secret, $hash);
    }

    public function testAHashDependsOnTheKey()
    {
        $hash = Identifier::hash('whsec_secret', 'another-application-key-of-32-bytes-or-more');

        $this->assertFalse(Identifier::matches('whsec_secret', $hash));
        $this->assertTrue(Identifier::matches('whsec_secret', $hash, 'another-application-key-of-32-bytes-or-more'));
    }

    public function testHashingNeedsAKey()
    {
        $this->expectException(\RuntimeException::class);

        Environment::withInstance(new Environment(null), fn () => Identifier::hash('whsec_secret'));
    }

    public function testASha256HashedSecretMatchesWithoutAKey()
    {
        $hash = Environment::withInstance(new Environment(null), fn () => Identifier::hash256('whsec_secret'));

        $this->assertSame(hash('sha256', 'whsec_secret'), $hash);
        $this->assertTrue(Identifier::matches256('whsec_secret', $hash));
        $this->assertFalse(Identifier::matches256('whsec_other', $hash));
        $this->assertNotEquals(Identifier::hash('whsec_secret'), $hash);
    }
}
