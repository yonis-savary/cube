<?php

namespace Cube\Tests\Units\Env;

use Cube\Env\Session;
use Cube\Tests\Units\Env\Classes\UserPreferences;
use PHPUnit\Framework\TestCase;

class SessionTest extends TestCase
{
    public function test_an_empty_namespace_is_refused()
    {
        $this->expectException(\InvalidArgumentException::class);

        new Session('');
    }

    public function test_zero_is_a_legitimate_namespace()
    {
        $session = new Session('0');
        $session->set('cart', 'content');

        $this->assertEquals('content', $_SESSION['0cart']);
    }

    public function test_keys_are_prefixed_with_the_namespace()
    {
        $session = new Session('shop-');
        $session->set('cart', ['a-product']);

        $this->assertEquals('shop-cart', $session->getNamespacedKey('cart'));
        $this->assertEquals(['a-product'], $_SESSION['shop-cart']);
    }

    public function test_two_namespaces_hold_their_own_values()
    {
        $shop = new Session(uniqid('shop-'));
        $backOffice = new Session(uniqid('back-office-'));

        $shop->set('cart', 'shop content');
        $backOffice->set('cart', 'back office content');

        $this->assertEquals('shop content', $shop->get('cart'));
        $this->assertEquals('back office content', $backOffice->get('cart'));
    }

    public function test_get_falls_back_on_the_given_default()
    {
        $session = new Session(uniqid('shop-'));

        $this->assertNull($session->get('cart'));
        $this->assertEquals('empty', $session->get('cart', 'empty'));
    }

    public function test_has_and_unset()
    {
        $session = new Session(uniqid('shop-'));

        $this->assertFalse($session->has('cart'));

        $session->set('cart', null);
        $this->assertTrue($session->has('cart')); // a null value is still a set key

        $session->unset('cart');
        $this->assertFalse($session->has('cart'));
    }

    public function test_a_straw_stores_its_value_under_its_own_key()
    {
        $session = new Session(uniqid('shop-'));

        UserPreferences::set(['theme' => 'dark'], $session);

        $this->assertEquals(['theme' => 'dark'], UserPreferences::get($session));
        $this->assertEquals(['theme' => 'dark'], $session->get(UserPreferences::getKey()));
    }

    public function test_a_straw_can_be_unset()
    {
        $session = new Session(uniqid('shop-'));

        UserPreferences::set(['theme' => 'dark'], $session);
        UserPreferences::unset($session);

        $this->assertNull(UserPreferences::get($session));
        $this->assertFalse($session->has(UserPreferences::getKey()));
    }

    public function test_the_default_instance_reads_its_namespace_from_the_configuration()
    {
        $session = Session::getInstance();
        $session->set('cart', 'content');

        $this->assertEquals('cubecart', $session->getNamespacedKey('cart'));
        $this->assertEquals('content', $_SESSION['cubecart']);

        Session::removeInstance();
    }
}
