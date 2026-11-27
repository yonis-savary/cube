<?php

namespace Cube\Tests\Units\Security;

use Cube\Data\Database\Database;
use Cube\Security\Authentication;
use Cube\Security\Authentication\AuthenticationConfiguration;
use Cube\Security\BearerAuthentication;
use Cube\Tests\Units\Database\Providers\SQLiteProvider;
use Cube\Tests\Units\Models\User;
use Cube\Tests\Units\Security\Classes\BearerTokenAuthentication;
use Cube\Web\Http\Request;
use Cube\Web\Http\Response;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class BearerAuthenticationTest extends TestCase
{
    protected static ?Database $database = null;

    protected Authentication $authentication;
    protected BearerAuthentication $bearerAuthentication;

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

        $this->authentication = new Authentication(new AuthenticationConfiguration(new BearerTokenAuthentication()));
        $this->bearerAuthentication = new BearerAuthentication($this->authentication);
    }

    protected function tearDown(): void
    {
        $this->authentication->logout();
    }

    public static function tearDownAfterClass(): void
    {
        self::$database = null;
        Database::removeInstance();
    }

    public function testARequestWithoutAuthorizationIsLeftAlone()
    {
        $request = new Request();

        $this->assertSame($request, $this->bearerAuthentication->handleRequest($request));
        $this->assertFalse($this->authentication->isLogged());
    }

    public function testAnotherSchemeIsLeftAlone()
    {
        $request = $this->requestWithAuthorization('Basic '.base64_encode('alice:correct-horse'));

        $this->assertSame($request, $this->bearerAuthentication->handleRequest($request));
        $this->assertFalse($this->authentication->isLogged());
    }

    public function testAValidTokenAuthenticatesTheRequest()
    {
        $request = $this->requestWithAuthorization('Bearer '.BearerTokenAuthentication::ALICE_TOKEN);

        $this->assertSame($request, $this->bearerAuthentication->handleRequest($request));
        $this->assertEquals('alice', $this->authentication->user()->login);
    }

    public function testTheSchemeIsCaseInsensitive()
    {
        $request = $this->requestWithAuthorization('bearer '.BearerTokenAuthentication::ALICE_TOKEN);

        $this->assertSame($request, $this->bearerAuthentication->handleRequest($request));
        $this->assertTrue($this->authentication->isLogged());
    }

    public function testAnInvalidTokenIsAnsweredUnauthorized()
    {
        $response = $this->bearerAuthentication->handleRequest($this->requestWithAuthorization('Bearer forged-token'));

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(401, $response->getStatusCode());
        $this->assertEquals('Bearer error="invalid_token"', $response->getHeader('WWW-Authenticate'));
        $this->assertFalse($this->authentication->isLogged());
    }

    public function testAnEmptyTokenIsAnsweredUnauthorized()
    {
        $response = $this->bearerAuthentication->handleRequest($this->requestWithAuthorization('Bearer'));

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testTheTokenIsReadFromTheHeader()
    {
        $this->assertEquals('abc.def-ghi', BearerAuthentication::tokenOf($this->requestWithAuthorization('Bearer   abc.def-ghi ')));
        $this->assertNull(BearerAuthentication::tokenOf($this->requestWithAuthorization('Bearerabc')));
        $this->assertNull(BearerAuthentication::tokenOf(new Request()));
    }

    protected function requestWithAuthorization(string $authorization): Request
    {
        return new Request('GET', '/', headers: ['Authorization' => $authorization]);
    }
}
