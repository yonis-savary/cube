<?php

namespace Cube\Tests\Units\Security;

use Cube\Data\Database\Database;
use Cube\Env\Cache;
use Cube\Env\Cache\CacheConfiguration;
use Cube\Env\Cache\LocalDiskCache\LocalDiskCache;
use Cube\Event\Events;
use Cube\Security\Authentication;
use Cube\Security\Authentication\AuthenticationConfiguration;
use Cube\Security\Authentication\Events\AuthenticatedUser;
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
        $this->assertEquals($user->id(), $rememberMe->rememberedUser($token));
    }

    /** Tokens were cache keys as is : a file name in Storage/Cache was a working cookie. */
    public function testOnlyAHashOfTheTokenIsStored()
    {
        $driver = new LocalDiskCache($this->storage);
        $cache = new Cache(new CacheConfiguration($driver));
        $rememberMe = new SpyRememberMe($cache, $this->authentication, new UserRegisterConfiguration());
        $rememberMe->register(User::findWhere(['login' => 'alice']));
        $token = $rememberMe->lastCookie()['value'];

        $driver->save();

        $this->assertFalse($cache->has($token));
        $this->assertNotEmpty($this->storage->exploreFiles());
        foreach ($this->storage->exploreFiles() as $file)
            $this->assertStringNotContainsString($token, basename($file).file_get_contents($file));
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
        $rememberMe->register(new User(['id' => 404, 'login' => 'gone', 'password' => '', 'type' => 1]));
        $token = $rememberMe->lastCookie()['value'];

        $request = $this->requestWithToken($token);

        $this->assertSame($request, $rememberMe->handleRequest($request));
        $this->assertFalse($this->authentication->isLogged());
        $this->assertNull($rememberMe->rememberedUser($token));
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

        $this->assertNull($rememberMe->rememberedUser($token));

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

        $this->assertNull($rememberMe->rememberedUser($token));
    }

    public function testTheTokenCookieIsKeptAwayFromScripts()
    {
        // A remember-me token is a credential : an XSS must not be able to read it
        $this->assertTrue((new UserRegisterConfiguration())->cookieHttpOnly);
        $this->assertTrue((new UserRegisterConfiguration())->cookieSecure);
    }

    /** Tokens came from uniqid(), a clock reading anyone can guess, instead of a CSPRNG. */
    public function testTheTokenIsNotDerivedFromTheClock()
    {
        $rememberMe = $this->newRememberMe();
        $clockBefore = substr(sprintf('%08x', time()), 0, 6);

        $rememberMe->register(User::findWhere(['login' => 'alice']));

        $clockAfter = substr(sprintf('%08x', time()), 0, 6);
        $token = $rememberMe->lastCookie()['value'];

        $this->assertStringNotContainsString($clockBefore, $token);
        $this->assertStringNotContainsString($clockAfter, $token);
    }

    /** Refreshing issued a new token but left the old one valid, so a stolen cookie kept working. */
    public function testARefreshedTokenCannotBeReplayed()
    {
        $rememberMe = $this->newRememberMe(new UserRegisterConfiguration(refreshTokenOnRemember: true));
        $rememberMe->register(User::findWhere(['login' => 'alice']));
        $firstToken = $rememberMe->lastCookie()['value'];

        $rememberMe->handleRequest($this->requestWithToken($firstToken));

        $this->assertNull($rememberMe->rememberedUser($firstToken), 'The replaced token still points at the user');
    }

    /** Any cache key used to be accepted as a token, so a cookie naming another entry logged someone in. */
    public function testACookieNamingAnotherCacheEntryIsIgnored()
    {
        $this->cache->set('last-import', User::findWhere(['login' => 'alice'])->id());

        $this->newRememberMe()->handleRequest($this->requestWithToken('last-import'));

        $this->assertFalse($this->authentication->isLogged());
    }

    /**
     * With the listener docs/202 suggests, a cookie login issued a token from the listener and another
     * from the refresh : the first one was sent to the browser and stayed valid, out of forget()'s reach.
     */
    public function testALoginListenerIssuingATokenLeavesNoOrphan()
    {
        foreach ([true, false] as $refresh) {
            $rememberMe = $this->newRememberMe(new UserRegisterConfiguration(refreshTokenOnRemember: $refresh));
            $rememberMe->register(User::findWhere(['login' => 'alice']));
            $firstToken = $rememberMe->lastCookie()['value'];
            $cookiesBefore = count($rememberMe->sentCookies);

            Events::withInstance(new Events(), function (Events $events) use ($rememberMe, $firstToken) {
                $events->on(AuthenticatedUser::class, fn (AuthenticatedUser $event) => $rememberMe->register($event));

                $rememberMe->handleRequest($this->requestWithToken($firstToken));
            });

            $this->assertCount($cookiesBefore + 1, $rememberMe->sentCookies);
            $this->assertNull($rememberMe->rememberedUser($firstToken));
            $this->assertNotNull($rememberMe->rememberedUser($rememberMe->lastCookie()['value']));

            $this->authentication->logout();
        }
    }

    public function testForgetUserRevokesEveryDevice()
    {
        $user = User::findWhere(['login' => 'alice']);
        $rememberMe = $this->newRememberMe();

        $rememberMe->register($user);
        $laptop = $rememberMe->lastCookie()['value'];
        $rememberMe->register($user);
        $phone = $rememberMe->lastCookie()['value'];

        $rememberMe->forgetUser($user);

        $this->assertNull($rememberMe->rememberedUser($laptop));
        $this->assertNull($rememberMe->rememberedUser($phone));

        $rememberMe->register($user);
        $this->assertEquals($user->id(), $rememberMe->rememberedUser($rememberMe->lastCookie()['value']));
    }

    /** The token list of a user was read, modified and written back : two logins at once left one token out of forgetUser()'s reach. */
    public function testForgetUserRevokesTokensIssuedConcurrently()
    {
        $user = User::findWhere(['login' => 'alice']);
        $laptop = $this->newRememberMe();
        $phone = $this->newRememberMe();

        $laptop->register($user);
        $phone->register($user);

        $this->newRememberMe()->forgetUser($user);

        $this->assertNull($laptop->rememberedUser($laptop->lastCookie()['value']));
        $this->assertNull($phone->rememberedUser($phone->lastCookie()['value']));
    }

    /** forget() deleted whatever cache entry the cookie named. */
    public function testForgetLeavesOtherCacheEntriesAlone()
    {
        $this->cache->set('last-import', 'today');

        $this->newRememberMe()->forget($this->requestWithToken('last-import'));

        $this->assertEquals('today', $this->cache->get('last-import'));
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
