<?php

namespace Cube\Tests\Units\Security;

use Cube\Data\Database\Database;
use Cube\Env\Cache;
use Cube\Env\Cache\CacheConfiguration;
use Cube\Env\Cache\LocalDiskCache\LocalDiskCache;
use Cube\Event\Events;
use Cube\Security\Authentication;
use Cube\Security\Authentication\AuthenticationConfiguration;
use Cube\Security\Authentication\PasswordAuthentication;
use Cube\Security\RememberMe\RememberedUser;
use Cube\Security\RememberMe\UserRegisterConfiguration;
use Cube\Tests\Units\Database\Providers\SQLiteProvider;
use Cube\Tests\Units\Env\Classes\HasTemporaryStorage;
use Cube\Tests\Units\Models\User;
use Cube\Tests\Units\Security\Classes\SpyRememberMe;
use Cube\Web\Http\Request;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class RememberMeTest extends TestCase
{
    use HasTemporaryStorage;

    protected static ?Database $database = null;

    protected Cache $cache;
    protected Authentication $authentication;

    protected function setUp(): void
    {
        if (!self::$database) {
            self::$database = (new SQLiteProvider())->getEmptyDatabase();
            Database::setInstance(self::$database);

            User::insertArray([
                'login' => 'alice',
                'password' => password_hash('correct-horse', PASSWORD_DEFAULT),
                'type' => 1,
            ]);
        }

        Database::setInstance(self::$database);

        $this->setUpTemporaryStorage('remember-me-test-');
        $this->cache = new Cache(new CacheConfiguration(new LocalDiskCache($this->storage)));
        $this->authentication = new Authentication(new AuthenticationConfiguration(
            new PasswordAuthentication(User::class, 'login', 'password')
        ));
    }

    protected function tearDown(): void
    {
        $this->authentication->logout();
        $this->cache->clear();
        $this->tearDownTemporaryStorage();
    }

    public static function tearDownAfterClass(): void
    {
        self::$database = null;
        Database::removeInstance();
    }

    public function testRegisterKeepsTheUserBehindAToken()
    {
        $rememberMe = $this->newRememberMe();
        $user = User::findWhere(['login' => 'alice']);

        $rememberMe->register($user);

        $token = $rememberMe->lastCookie()['value'];

        $this->assertNotEmpty($token);
        $this->assertEquals($user->id(), $this->cache->get($token));
    }

    public function testARequestWithoutACookieIsLeftAlone()
    {
        $request = new Request('GET', '/');

        $this->assertSame($request, $this->newRememberMe()->handleRequest($request));
        $this->assertFalse($this->authentication->isLogged());
    }

    public function testAnUnknownTokenIsLeftAlone()
    {
        $request = $this->requestWithToken('a-token-nobody-knows');

        $this->newRememberMe()->handleRequest($request);

        $this->assertFalse($this->authentication->isLogged());
    }

    public function testAKnownTokenLogsTheUserBackIn()
    {
        $rememberMe = $this->newRememberMe();
        $user = User::findWhere(['login' => 'alice']);
        $rememberMe->register($user);

        $rememberMe->handleRequest($this->requestWithToken($rememberMe->lastCookie()['value']));

        $this->assertTrue($this->authentication->isLogged());
        $this->assertEquals('alice', $this->authentication->user()->login);
    }

    public function testARememberedUserAnnouncesItself()
    {
        $rememberMe = $this->newRememberMe();
        $rememberMe->register(User::findWhere(['login' => 'alice']));
        $token = $rememberMe->lastCookie()['value'];

        $seen = [];
        Events::withInstance(new Events(), function (Events $events) use (&$seen, $rememberMe, $token) {
            $events->on(RememberedUser::class, function (RememberedUser $event) use (&$seen) {
                $seen[] = $event->userData->login;
            });

            $rememberMe->handleRequest($this->requestWithToken($token));
        });

        $this->assertEquals(['alice'], $seen);
    }

    /**
     * A token can outlive the user it points to : logging in fails, and user() used to throw
     * on every single request carrying that cookie.
     */
    public function testATokenPointingAtAGoneUserIsDropped()
    {
        $rememberMe = $this->newRememberMe();
        $rememberMe->register(User::findWhere(['login' => 'alice']));
        $token = $rememberMe->lastCookie()['value'];

        $this->cache->set($token, 404);

        $request = $this->requestWithToken($token);

        $this->assertSame($request, $rememberMe->handleRequest($request));
        $this->assertFalse($this->authentication->isLogged());
        $this->assertFalse($this->cache->has($token));
    }

    public function testAnAlreadyLoggedUserIsNotLookedUpAgain()
    {
        $this->authentication->attempt('alice', 'correct-horse');

        $rememberMe = $this->newRememberMe();
        $request = $this->requestWithToken('a-token-nobody-knows');

        $this->assertSame($request, $rememberMe->handleRequest($request));
        $this->assertTrue($this->authentication->isLogged());
    }

    public function testRefreshingTheTokenOnRemember()
    {
        $rememberMe = $this->newRememberMe(new UserRegisterConfiguration(refreshTokenOnRemember: true));
        $rememberMe->register(User::findWhere(['login' => 'alice']));
        $firstToken = $rememberMe->lastCookie()['value'];

        $rememberMe->handleRequest($this->requestWithToken($firstToken));

        $this->assertNotEquals($firstToken, $rememberMe->lastCookie()['value']);
    }

    public function testKeepingTheSameTokenOnRemember()
    {
        $rememberMe = $this->newRememberMe(new UserRegisterConfiguration(refreshTokenOnRemember: false));
        $rememberMe->register(User::findWhere(['login' => 'alice']));
        $firstToken = $rememberMe->lastCookie()['value'];

        $rememberMe->handleRequest($this->requestWithToken($firstToken));

        $this->assertEquals($firstToken, $rememberMe->lastCookie()['value']);
    }

    public function testForgetDropsTheTokenAndTellsTheBrowserToDoTheSame()
    {
        $rememberMe = $this->newRememberMe();
        $rememberMe->register(User::findWhere(['login' => 'alice']));
        $token = $rememberMe->lastCookie()['value'];

        $rememberMe->forget($this->requestWithToken($token));

        $this->assertFalse($this->cache->has($token));

        $cookie = $rememberMe->lastCookie();
        $this->assertEquals('', $cookie['value']);
        $this->assertLessThan(time(), $cookie['expiresAt']);
    }

    public function testForgetAcceptsARawToken()
    {
        $rememberMe = $this->newRememberMe();
        $rememberMe->register(User::findWhere(['login' => 'alice']));
        $token = $rememberMe->lastCookie()['value'];

        $rememberMe->forget($token);

        $this->assertFalse($this->cache->has($token));
    }

    public function testTheTokenCookieIsKeptAwayFromScripts()
    {
        // A remember-me token is a credential : an XSS must not be able to read it
        $this->assertTrue((new UserRegisterConfiguration())->cookieHttpOnly);
        $this->assertTrue((new UserRegisterConfiguration())->cookieSecure);
    }

    protected function newRememberMe(?UserRegisterConfiguration $configuration = null): SpyRememberMe
    {
        return new SpyRememberMe(
            $this->cache,
            $this->authentication,
            $configuration ?? new UserRegisterConfiguration()
        );
    }

    protected function requestWithToken(string $token): Request
    {
        $cookieName = (new UserRegisterConfiguration())->cookieName;

        return new Request('GET', '/', cookies: [$cookieName => $token]);
    }
}
