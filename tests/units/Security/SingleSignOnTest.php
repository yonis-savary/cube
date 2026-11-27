<?php

namespace Cube\Tests\Units\Security;

use Cube\Core\Exceptions\ResponseException;
use Cube\Env\Cache;
use Cube\Env\Cache\CacheConfiguration;
use Cube\Env\Cache\LocalDiskCache\LocalDiskCache;
use Cube\Env\Session;
use Cube\Env\Session\SessionConfiguration;
use Cube\Security\SingleSignOn;
use Cube\Security\SingleSignOn\IdentityProvider;
use Cube\Security\SingleSignOn\IdentityProviderClient;
use Cube\Security\SingleSignOn\Providers\OpenIDConnectProvider;
use Cube\Security\SingleSignOn\SingleSignOnConfiguration;
use Cube\Test\FakeIdentityProvider;
use Cube\Tests\Units\Env\Classes\HasTemporaryStorage;
use Cube\Tests\Units\Security\Classes\FakeOpenIDConnectServer;
use Cube\Web\Http\HttpMockServer;
use Cube\Web\Http\MockServers;
use Cube\Web\Http\Request;
use Cube\Web\Http\StatusCode;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class SingleSignOnTest extends TestCase
{
    use HasTemporaryStorage;

    public const REDIRECT_URI = 'https://app.example.test/auth/company/callback';

    protected FakeOpenIDConnectServer $openIdServer;
    protected SingleSignOn $singleSignOn;

    protected function setUp(): void
    {
        $this->setUpTemporaryStorage('single-sign-on-test-');
        Cache::setInstance(new Cache(new CacheConfiguration(new LocalDiskCache($this->storage))));

        $this->openIdServer = new FakeOpenIDConnectServer();
        $this->useServer($this->openIdServer);

        $this->singleSignOn = new SingleSignOn(new SingleSignOnConfiguration(
            new OpenIDConnectProvider('company', FakeOpenIDConnectServer::ISSUER, 'client-id', 'client-secret', self::REDIRECT_URI),
            new OpenIDConnectProvider('partner', FakeOpenIDConnectServer::ISSUER, 'partner-client-id', 'partner-client-secret', 'https://app.example.test/auth/partner/callback'),
        ));

        (new Session(SessionConfiguration::resolve(), $this->singleSignOn->getScope()))->clear();
    }

    protected function tearDown(): void
    {
        MockServers::removeInstance();
        Cache::removeInstance();
        $this->tearDownTemporaryStorage();
    }

    public function testTheRedirectionCarriesWhatTheProviderNeeds()
    {
        $response = $this->singleSignOn->redirect('company');

        $this->assertEquals(StatusCode::FOUND, $response->getStatusCode());
        $this->assertStringStartsWith(FakeOpenIDConnectServer::ISSUER.'/authorize?', $response->getHeader('location'));

        $query = $this->queryOf($response->getHeader('location'));
        $this->assertEquals('code', $query['response_type']);
        $this->assertEquals('client-id', $query['client_id']);
        $this->assertEquals(self::REDIRECT_URI, $query['redirect_uri']);
        $this->assertEquals('openid email profile', $query['scope']);
        $this->assertEquals('S256', $query['code_challenge_method']);
        $this->assertMatchesRegularExpression('~^[0-9a-f]{32}$~', $query['state']);
        $this->assertArrayNotHasKey('client_secret', $query);
    }

    public function testASignInGivesTheIdentityBack()
    {
        $query = $this->redirectionQuery('company');

        $identity = $this->singleSignOn->callback($this->callbackRequest($query['state']), 'company');

        $this->assertNotNull($identity);
        $this->assertEquals('company', $identity->provider);
        $this->assertEquals('110248495921238986420', $identity->id);
        $this->assertEquals('alice@example.test', $identity->email);
        $this->assertTrue($identity->emailVerified);
        $this->assertEquals('Alice Martin', $identity->name);
        $this->assertEquals('Alice Martin', $identity->raw['name']);
    }

    public function testTheCodeIsExchangedWithTheVerifierOfTheChallenge()
    {
        $query = $this->redirectionQuery('company');

        $this->singleSignOn->callback($this->callbackRequest($query['state'], 'the-code'), 'company');

        $tokenRequest = $this->openIdServer->tokenRequests[0];
        $this->assertEquals('authorization_code', $tokenRequest['grant_type']);
        $this->assertEquals('the-code', $tokenRequest['code']);
        $this->assertEquals('client-id', $tokenRequest['client_id']);
        $this->assertEquals('client-secret', $tokenRequest['client_secret']);
        $this->assertEquals(self::REDIRECT_URI, $tokenRequest['redirect_uri']);

        $challenge = rtrim(strtr(base64_encode(hash('sha256', $tokenRequest['code_verifier'], true)), '+/', '-_'), '=');
        $this->assertEquals($query['code_challenge'], $challenge);
    }

    public function testAnotherStateIsRefusedWithoutReachingTheProvider()
    {
        $this->redirectionQuery('company');

        $this->assertNull($this->singleSignOn->callback($this->callbackRequest(str_repeat('0', 32)), 'company'));
        $this->assertCount(0, $this->openIdServer->tokenRequests);
    }

    public function testAMissingStateIsRefused()
    {
        $this->redirectionQuery('company');

        $this->assertNull($this->singleSignOn->callback(new Request('GET', '/callback', ['code' => 'the-code']), 'company'));
    }

    /** Someone else's callback URL, opened in your browser, would otherwise sign you in as them. */
    public function testACallbackWithoutARedirectionIsRefused()
    {
        $this->assertNull($this->singleSignOn->callback($this->callbackRequest(str_repeat('0', 32)), 'company'));
        $this->assertCount(0, $this->openIdServer->tokenRequests);
    }

    public function testACallbackIsUsedOnlyOnce()
    {
        $query = $this->redirectionQuery('company');

        $this->assertNotNull($this->singleSignOn->callback($this->callbackRequest($query['state']), 'company'));
        $this->assertNull($this->singleSignOn->callback($this->callbackRequest($query['state']), 'company'));
    }

    public function testARefusedConsentGivesNothing()
    {
        $query = $this->redirectionQuery('company');
        $request = new Request('GET', '/callback', ['error' => 'access_denied', 'state' => $query['state']]);

        $this->assertNull($this->singleSignOn->callback($request, 'company'));
        $this->assertCount(0, $this->openIdServer->tokenRequests);
    }

    public function testACodeRefusedByTheProviderGivesNothing()
    {
        $this->openIdServer->tokenError = 'invalid_grant';
        $query = $this->redirectionQuery('company');

        $this->assertNull($this->singleSignOn->callback($this->callbackRequest($query['state']), 'company'));
    }

    public function testAClientRefusedByTheProviderIsAConfigurationError()
    {
        $this->openIdServer->tokenError = 'invalid_client';
        $query = $this->redirectionQuery('company');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('invalid_client');

        $this->singleSignOn->callback($this->callbackRequest($query['state']), 'company');
    }

    public function testTheStateOfOneProviderDoesNotOpenAnother()
    {
        $query = $this->redirectionQuery('company');

        $this->assertNull($this->singleSignOn->callback($this->callbackRequest($query['state']), 'partner'));
    }

    public function testTheDiscoveryDocumentIsReadOnce()
    {
        $this->redirectionQuery('company');
        $this->redirectionQuery('company');

        $this->assertEquals(1, $this->openIdServer->discoveryRequests);
    }

    public function testADiscoveryDocumentOfAnotherIssuerIsRejected()
    {
        $this->openIdServer->announcedIssuer = 'https://attacker.example.test';

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('https://attacker.example.test');

        $this->singleSignOn->redirect('company');
    }

    public function testAnEmailVerifiedClaimSentAsAStringIsRead()
    {
        $this->openIdServer->claims['email_verified'] = 'true';
        $query = $this->redirectionQuery('company');

        $this->assertTrue($this->singleSignOn->callback($this->callbackRequest($query['state']), 'company')->emailVerified);
    }

    public function testAnUnverifiedEmailIsSaidSo()
    {
        $this->openIdServer->claims['email_verified'] = false;
        $query = $this->redirectionQuery('company');

        $this->assertFalse($this->singleSignOn->callback($this->callbackRequest($query['state']), 'company')->emailVerified);
    }

    public function testAnUnknownProviderIsNotFound()
    {
        try {
            $this->singleSignOn->redirect('myspace');
            $this->fail('An unknown provider name was accepted');
        } catch (ResponseException $exception) {
            $this->assertEquals(StatusCode::NOT_FOUND, $exception->response->getStatusCode());
        }
    }

    public function testProvidersSurviveTheConfigurationCache()
    {
        $provider = OpenIDConnectProvider::google('client-id', 'client-secret', self::REDIRECT_URI);

        $this->assertEquals($provider, unserialize(serialize($provider)));
    }

    public function testTheFakeProviderSignsInItsIdentity()
    {
        $fake = (new FakeIdentityProvider('google'))->withIdentity('42', 'alice@example.test', name: 'Alice');
        $singleSignOn = new SingleSignOn(new SingleSignOnConfiguration($fake));

        $singleSignOn->redirect('google');
        $identity = $singleSignOn->callback(new Request('GET', '/callback', $fake->callbackParameters()), 'google');

        $this->assertEquals('google', $identity->provider);
        $this->assertEquals('42', $identity->id);
        $this->assertTrue($identity->emailVerified);
    }

    public function testTheFakeProviderWithoutIdentityRefusesTheCode()
    {
        $fake = new FakeIdentityProvider('google');
        $singleSignOn = new SingleSignOn(new SingleSignOnConfiguration($fake));

        $singleSignOn->redirect('google');

        $this->assertNull($singleSignOn->callback(new Request('GET', '/callback', $fake->callbackParameters()), 'google'));
    }

    public function testAProviderCanBeGivenDirectly()
    {
        $provider = $this->singleSignOn->provider('company');
        $this->assertInstanceOf(IdentityProvider::class, $provider);

        $query = $this->queryOf($this->singleSignOn->redirect($provider)->getHeader('location'));

        $this->assertNotNull($this->singleSignOn->callback($this->callbackRequest($query['state']), $provider));
    }

    protected function useServer(HttpMockServer $server): void
    {
        MockServers::removeInstance();
        MockServers::getInstance()->set(IdentityProviderClient::class, $server);
    }

    /**
     * @return array<string,string>
     */
    protected function redirectionQuery(string $provider): array
    {
        return $this->queryOf($this->singleSignOn->redirect($provider)->getHeader('location'));
    }

    /**
     * @return array<string,string>
     */
    protected function queryOf(string $url): array
    {
        parse_str(parse_url($url, PHP_URL_QUERY), $query);

        return $query;
    }

    protected function callbackRequest(string $state, string $code = 'authorization-code'): Request
    {
        return new Request('GET', '/auth/callback', ['code' => $code, 'state' => $state]);
    }
}
