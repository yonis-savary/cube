<?php

namespace Cube\Tests\Units\Security;

use Cube\Data\Database\Database;
use Cube\Event\Events;
use Cube\Security\Authentication;
use Cube\Security\Authentication\AuthenticationConfiguration;
use Cube\Security\Authentication\Events\AuthenticatedUser;
use Cube\Security\Authentication\Events\FailedAuthentication;
use Cube\Security\Authentication\Events\LoggedInUser;
use Cube\Security\Authentication\Events\LoggedOutUser;
use Cube\Security\Authentication\PasswordAuthentication;
use Cube\Tests\Units\Database\Providers\SQLiteProvider;
use Cube\Tests\Units\Models\User;
use Cube\Tests\Units\Security\Classes\ExposedPasswordAuthentication;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

/**
 * Authentication does not talk to the database itself, it goes through a provider, so one
 * driver is enough here : the multi-driver ground is covered by ModelTest.
 *
 * @internal
 */
class AuthenticationTest extends TestCase
{
    protected static ?Database $database = null;

    /**
     * One database for the whole class : building an empty one costs half a second, and no
     * test here changes a user that another one reads.
     */
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
    }

    protected function tearDown(): void
    {
        $this->newAuthentication()->logout();
    }

    public static function tearDownAfterClass(): void
    {
        self::$database = null;
        Database::removeInstance();
    }

    public function testAttemptWithTheRightPassword()
    {
        $authentication = $this->newAuthentication();

        $this->assertTrue($authentication->attempt('alice', 'correct-horse'));
        $this->assertTrue($authentication->isLogged());
    }

    public function testAttemptWithAWrongPassword()
    {
        $authentication = $this->newAuthentication();

        $this->assertFalse($authentication->attempt('alice', 'wrong-password'));
        $this->assertFalse($authentication->isLogged());
    }

    public function testAttemptWithAnUnknownLogin()
    {
        $authentication = $this->newAuthentication();

        $this->assertFalse($authentication->attempt('bob', 'correct-horse'));
        $this->assertFalse($authentication->isLogged());
    }

    public function testAttemptWithoutAPassword()
    {
        $authentication = $this->newAuthentication();

        $this->assertFalse($authentication->attempt('alice'));
        $this->assertFalse($authentication->isLogged());
    }

    public function testTheAuthenticatedUserIsGivenBack()
    {
        $authentication = $this->newAuthentication();
        $authentication->attempt('alice', 'correct-horse');

        $user = $authentication->user();

        $this->assertInstanceOf(User::class, $user);
        $this->assertEquals('alice', $user->login);
        $this->assertEquals($user->id(), $authentication->userId());
    }

    public function testReadingTheUserWhileLoggedOut()
    {
        $this->expectException(\RuntimeException::class);

        $this->newAuthentication()->user();
    }

    public function testUserIdIsFalseWhileLoggedOut()
    {
        $this->assertFalse($this->newAuthentication()->userId());
    }

    public function testAFailedAttemptClosesAnOpenSession()
    {
        $authentication = $this->newAuthentication();
        $authentication->attempt('alice', 'correct-horse');

        $authentication->attempt('alice', 'wrong-password');

        $this->assertFalse($authentication->isLogged());
    }

    public function testLogout()
    {
        $authentication = $this->newAuthentication();
        $authentication->attempt('alice', 'correct-horse');

        $authentication->logout();

        $this->assertFalse($authentication->isLogged());
        $this->assertFalse($authentication->userId());
    }

    public function testLoginById()
    {
        $authentication = $this->newAuthentication();
        $id = User::findWhere(['login' => 'alice'])->id();

        $this->assertTrue($authentication->loginById($id));
        $this->assertEquals('alice', $authentication->user()->login);
    }

    public function testLoginByAnUnknownId()
    {
        $authentication = $this->newAuthentication();

        $this->assertFalse($authentication->loginById(404));
        $this->assertFalse($authentication->isLogged());
    }

    public function testAnyOfTheGivenLoginFieldsIsAccepted()
    {
        $authentication = $this->newAuthentication(
            new PasswordAuthentication(User::class, ['login', 'password'], 'password')
        );

        $this->assertTrue($authentication->attempt('alice', 'correct-horse'));
    }

    public function testASaltFieldTakesPartInTheHash()
    {
        // The salt column here is 'login', so what gets verified is password + login
        User::insertArray([
            'login' => 'salted',
            'password' => password_hash('secret'.'salted', PASSWORD_DEFAULT),
            'type' => 1,
        ]);

        $authentication = $this->newAuthentication(
            new PasswordAuthentication(User::class, 'login', 'password', 'login')
        );

        $this->assertTrue($authentication->attempt('salted', 'secret'));
        $this->assertFalse($authentication->attempt('salted', 'secretsalted'));
    }

    public function testASuccessfulAttemptAnnouncesItself()
    {
        $seen = [];

        Events::withInstance(new Events(), function (Events $events) use (&$seen) {
            $events->on(AuthenticatedUser::class, function () use (&$seen) { $seen[] = 'authenticated'; });
            $events->on(LoggedInUser::class, function () use (&$seen) { $seen[] = 'logged-in'; });
            $events->on(FailedAuthentication::class, function () use (&$seen) { $seen[] = 'failed'; });

            $this->newAuthentication()->attempt('alice', 'correct-horse');
        });

        $this->assertEquals(['authenticated', 'logged-in'], $seen);
    }

    public function testAFailedAttemptIsAnnouncedOnce()
    {
        $failures = 0;

        Events::withInstance(new Events(), function (Events $events) use (&$failures) {
            $events->on(FailedAuthentication::class, function () use (&$failures) { ++$failures; });

            $this->newAuthentication()->attempt('alice', 'wrong-password');
        });

        $this->assertEquals(1, $failures);
    }

    public function testLoggingOutAnnouncesItself()
    {
        $seen = [];

        Events::withInstance(new Events(), function (Events $events) use (&$seen) {
            $authentication = $this->newAuthentication();
            $authentication->attempt('alice', 'correct-horse');

            $events->on(LoggedOutUser::class, function (LoggedOutUser $event) use (&$seen) {
                $seen[] = $event->authenticatedUser->login;
            });

            $authentication->logout();
        });

        $this->assertEquals(['alice'], $seen);
    }

    public function testLoggingOutWhileLoggedOutAnnouncesNothing()
    {
        $calls = 0;

        Events::withInstance(new Events(), function (Events $events) use (&$calls) {
            $events->on(LoggedOutUser::class, function () use (&$calls) { ++$calls; });

            $this->newAuthentication()->logout();
        });

        $this->assertEquals(0, $calls);
    }

    public function testAnAccountWithoutAUsableHashIsRefused()
    {
        User::insertArray(['login' => 'no-hash', 'password' => '', 'type' => 1]);

        $authentication = $this->newAuthentication();

        $this->assertFalse($authentication->attempt('no-hash', ''));
        $this->assertFalse($authentication->attempt('no-hash', 'anything'));
        $this->assertFalse($authentication->isLogged());
    }

    public function testTheDecoyIsARealHashOfTheCurrentAlgorithm()
    {
        $decoy = ExposedPasswordAuthentication::publicDecoyHash();

        $this->assertEquals(PASSWORD_DEFAULT, password_get_info($decoy)['algo']);
        $this->assertSame($decoy, ExposedPasswordAuthentication::publicDecoyHash());
    }

    /**
     * Rejecting an unknown identifier without verifying anything answers in a fraction of the
     * time a known one takes, which is enough to enumerate the accounts that exist. Both paths
     * are dominated by one bcrypt round, so the ratio below leaves a wide margin : a provider
     * that skipped the decoy would answer in under a millisecond.
     */
    public function testRejectingAnUnknownLoginCostsWhatARealOneCosts()
    {
        $authentication = $this->newAuthentication();
        $authentication->attempt('warmup', 'x'); // the decoy is built on first use

        $knownLogin = $this->timeOf(fn () => $authentication->attempt('alice', 'wrong-password'));
        $unknownLogin = $this->timeOf(fn () => $authentication->attempt('nobody-here', 'wrong-password'));

        $this->assertGreaterThan($knownLogin * 0.2, $unknownLogin);
    }

    public function testAProviderMustBeGivenAModelClass()
    {
        $this->expectException(\InvalidArgumentException::class);

        new PasswordAuthentication(\stdClass::class, 'login', 'password');
    }

    protected function timeOf(callable $callback): float
    {
        $start = microtime(true);
        $callback();

        return microtime(true) - $start;
    }

    /** Logging in kept the pre-login session id, leaving the session open to fixation. */
    #[RunInSeparateProcess]
    public function testLoggingInRenewsTheSessionId()
    {
        $sessionIdBeforeLogin = session_id();

        $this->assertTrue($this->newAuthentication()->attempt('alice', 'correct-horse'));

        $this->assertNotEquals($sessionIdBeforeLogin, session_id());
    }

    /** The session copy of the user used to carry its password hash. */
    public function testThePasswordHashIsNotKeptInTheSession()
    {
        $this->assertTrue($this->newAuthentication()->attempt('alice', 'correct-horse'));

        $this->assertArrayNotHasKey('password', $this->newAuthentication()->user()->toArray());
    }

    protected function newAuthentication(?PasswordAuthentication $provider = null): Authentication
    {
        $provider ??= new PasswordAuthentication(User::class, 'login', 'password');

        return new Authentication(new AuthenticationConfiguration($provider));
    }
}
