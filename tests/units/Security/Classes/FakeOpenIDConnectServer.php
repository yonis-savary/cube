<?php

namespace Cube\Tests\Units\Security\Classes;

use Cube\Web\Http\HttpMockServer;
use Cube\Web\Http\Request;
use Cube\Web\Http\Response;
use Cube\Web\Router\Route;
use Cube\Web\Router\Router;

/**
 * An OpenID Connect provider answering discovery, token and userinfo, and keeping what it was sent.
 */
class FakeOpenIDConnectServer extends HttpMockServer
{
    public const ISSUER = 'https://id.example.test';
    public const ACCESS_TOKEN = 'oidc-access-token';

    public string $announcedIssuer = self::ISSUER;
    public ?string $tokenError = null;
    public int $discoveryRequests = 0;

    /** @var array<int,array<string,mixed>> */
    public array $tokenRequests = [];

    /** @var array<string,mixed> */
    public array $claims = [
        'sub' => '110248495921238986420',
        'email' => 'alice@example.test',
        'email_verified' => true,
        'name' => 'Alice Martin',
    ];

    public function routes(Router $router): void
    {
        $router->addRoutes(
            Route::get('/.well-known/openid-configuration', fn () => $this->discovery()),
            Route::post('/token', fn (Request $request) => $this->token($request)),
            Route::get('/userinfo', fn (Request $request) => $this->userinfo($request)),
        );
    }

    protected function discovery(): Response
    {
        ++$this->discoveryRequests;

        return Response::json([
            'issuer' => $this->announcedIssuer,
            'authorization_endpoint' => self::ISSUER.'/authorize',
            'token_endpoint' => self::ISSUER.'/token',
            'userinfo_endpoint' => self::ISSUER.'/userinfo',
        ]);
    }

    protected function token(Request $request): Response
    {
        $this->tokenRequests[] = $request->post();

        if ($this->tokenError)
            return Response::json(['error' => $this->tokenError, 'error_description' => 'Refused by the fake server'], 400);
        return Response::json(['access_token' => self::ACCESS_TOKEN, 'token_type' => 'Bearer', 'expires_in' => 3599]);
    }

    protected function userinfo(Request $request): Response
    {
        if ('Bearer '.self::ACCESS_TOKEN !== $request->getHeader('authorization'))
            return Response::unauthorized();
        return Response::json($this->claims);
    }
}
