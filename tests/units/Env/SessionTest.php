<?php

namespace Cube\Tests\Units\Env;

use Cube\Env\Session;
use Cube\Env\Session\Drivers\PHPGlobalSession;
use Cube\Env\Session\SessionConfiguration;
use Cube\Tests\Units\Env\Classes\UserPreferences;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class SessionTest extends TestCase
{
    public function testAnEmptyNamespaceIsRefused()
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->newSession('');
    }

    public function testZeroIsALegitimateNamespace()
    {
        $session = $this->newSession('0');
        $session->set('cart', 'content');

        $this->assertEquals('content', $_SESSION['cube']['0']['cart']);
    }

    public function testValuesAreStoredUnderTheNamespace()
    {
        $session = $this->newSession('shop');
        $session->set('cart', ['a-product']);

        $this->assertEquals(['a-product'], $_SESSION['cube']['shop']['cart']);
    }

    public function testTheNamespaceDefaultsToTheConfiguredOne()
    {
        $session = new Session(new SessionConfiguration('configured'));
        $session->set('cart', 'content');

        $this->assertEquals('content', $_SESSION['cube']['configured']['cart']);
    }

    public function testTwoNamespacesHoldTheirOwnValues()
    {
        $shop = $this->newSession();
        $backOffice = $this->newSession();

        $shop->set('cart', 'shop content');
        $backOffice->set('cart', 'back office content');

        $this->assertEquals('shop content', $shop->get('cart'));
        $this->assertEquals('back office content', $backOffice->get('cart'));
    }

    public function testSessionsSharingAConfigurationKeepTheirOwnNamespace()
    {
        $configuration = new SessionConfiguration(driver: new PHPGlobalSession());
        $shop = new Session($configuration, uniqid('shop-'));
        $backOffice = new Session($configuration, uniqid('back-office-'));

        $shop->set('cart', 'shop content');

        $this->assertFalse($backOffice->has('cart'));
    }

    public function testClearOnlyEmptiesItsOwnNamespace()
    {
        $shop = $this->newSession();
        $backOffice = $this->newSession();
        $shop->set('cart', ['a-product']);
        $shop->set('currency', 'EUR');
        $backOffice->set('cart', 'back office content');

        $shop->clear();

        $this->assertFalse($shop->has('cart'));
        $this->assertFalse($shop->has('currency'));
        $this->assertEquals('back office content', $backOffice->get('cart'));
    }

    public function testGetFallsBackOnTheGivenDefault()
    {
        $session = $this->newSession();

        $this->assertNull($session->get('cart'));
        $this->assertEquals('empty', $session->get('cart', 'empty'));
    }

    public function testHasAndUnset()
    {
        $session = $this->newSession();

        $this->assertFalse($session->has('cart'));

        $session->set('cart', null);
        $this->assertTrue($session->has('cart')); // a null value is still a set key

        $session->unset('cart');
        $this->assertFalse($session->has('cart'));
    }

    public function testUnsetRemovesEveryGivenKey()
    {
        $session = $this->newSession();
        $session->set('cart', ['a-product']);
        $session->set('currency', 'EUR');
        $session->set('theme', 'dark');

        $session->unset('cart', 'currency');

        $this->assertFalse($session->has('cart'));
        $this->assertFalse($session->has('currency'));
        $this->assertTrue($session->has('theme'));
    }

    public function testAStrawStoresItsValueUnderItsOwnKey()
    {
        $session = $this->newSession();

        UserPreferences::set(['theme' => 'dark'], $session);

        $this->assertEquals(['theme' => 'dark'], UserPreferences::get($session));
        $this->assertEquals(['theme' => 'dark'], $session->get(UserPreferences::getKey()));
    }

    public function testAStrawCanBeUnset()
    {
        $session = $this->newSession();

        UserPreferences::set(['theme' => 'dark'], $session);
        UserPreferences::unset($session);

        $this->assertNull(UserPreferences::get($session));
        $this->assertFalse($session->has(UserPreferences::getKey()));
    }

    public function testTheDefaultInstanceReadsItsNamespaceFromTheConfiguration()
    {
        $session = Session::getInstance();
        $session->set('cart', 'content');

        $this->assertEquals('content', $_SESSION['cube']['cube']['cart']);

        Session::removeInstance();
    }

    protected function newSession(?string $namespace = null): Session
    {
        return new Session(new SessionConfiguration(driver: new PHPGlobalSession()), $namespace ?? uniqid('shop-'));
    }
}
