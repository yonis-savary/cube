<?php

namespace Cube\Tests\Units\Env\Session\Drivers;

use Cube\Env\Session\Drivers\PHPGlobalSession;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class PHPGlobalSessionTest extends TestCase
{
    public function testInitializeStartsThePhpSession()
    {
        $this->newDriver();

        $this->assertEquals(PHP_SESSION_ACTIVE, session_status());
    }

    public function testValuesAreStoredUnderTheNamespace()
    {
        $this->newDriver('shop')->set('cart', ['a-product']);

        $this->assertEquals(['a-product'], $_SESSION['cube']['shop']['cart']);
    }

    public function testGetFallsBackOnTheGivenDefault()
    {
        $driver = $this->newDriver();

        $this->assertNull($driver->get('cart'));
        $this->assertEquals('empty', $driver->get('cart', 'empty'));

        $driver->set('cart', 'content');
        $this->assertEquals('content', $driver->get('cart', 'empty'));
    }

    public function testHasAndDelete()
    {
        $driver = $this->newDriver();

        $this->assertFalse($driver->has('cart'));

        $driver->set('cart', null);
        $this->assertTrue($driver->has('cart')); // a null value is still a set key

        $driver->delete('cart');
        $this->assertFalse($driver->has('cart'));
    }

    public function testClearOnlyEmptiesItsNamespace()
    {
        $shop = $this->newDriver();
        $backOffice = $this->newDriver();
        $shop->set('cart', ['a-product']);
        $backOffice->set('cart', 'back office content');

        $shop->clear();

        $this->assertFalse($shop->has('cart'));
        $this->assertTrue($backOffice->has('cart'));
    }

    /** Behind a proxy terminating TLS, $_SERVER['HTTPS'] is empty : the cookie was never sent as Secure. */
    public function testTheCookieIsSecureBehindATlsTerminatingProxy()
    {
        $server = $_SERVER;
        try {
            unset($_SERVER['HTTPS']);
            $this->assertFalse((new PHPGlobalSession())->isCookieSecure());

            $_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https';
            $this->assertTrue((new PHPGlobalSession())->isCookieSecure());
            $this->assertFalse((new PHPGlobalSession(secureCookie: false))->isCookieSecure());

            unset($_SERVER['HTTP_X_FORWARDED_PROTO']);
            $this->assertTrue((new PHPGlobalSession(secureCookie: true))->isCookieSecure());
        } finally {
            $_SERVER = $server;
        }
    }

    protected function newDriver(?string $namespace = null): PHPGlobalSession
    {
        $driver = new PHPGlobalSession();
        $driver->initialize($namespace ?? uniqid('shop-'));

        return $driver;
    }
}
