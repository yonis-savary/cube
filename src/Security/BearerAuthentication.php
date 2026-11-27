<?php

namespace Cube\Security;

use Cube\Core\Component;
use Cube\Web\Http\Request;
use Cube\Web\Http\Response;
use Cube\Web\Middleware;

/** Authenticates a request by its `Authorization: Bearer` header, for that request only : nothing reaches the session */
class BearerAuthentication implements Middleware
{
    use Component;

    public function __construct(
        protected Authentication $authentication
    ) {}

    public static function getDefaultInstance(): static
    {
        return new static(Authentication::getInstance());
    }

    public static function handle(Request $request, \Closure $next): Request|Response
    {
        $handled = self::getInstance()->handleRequest($request);

        return $handled instanceof Response
            ? $handled
            : $next($handled);
    }

    public function handleRequest(Request $request): Request|Response
    {
        if (null === $token = self::tokenOf($request))
            return $request;
        if ('' !== $token && $this->authentication->attemptBearerToken($token))
            return $request;
        // RFC 6750 §3 : a refused token is answered with the scheme and the invalid_token error code
        return Response::unauthorized('Invalid bearer token')
            ->withHeaders(['www-authenticate' => 'Bearer error="invalid_token"'])
        ;
    }

    /**
     * @return ?string `null` when the request carries no bearer token, whatever other scheme it uses
     */
    public static function tokenOf(Request $request): ?string
    {
        // RFC 7235 §2.1 : the scheme name is case-insensitive
        if (!preg_match('/^Bearer(?: +(.*))?$/i', trim($request->getHeader('authorization') ?? ''), $matches))
            return null;
        return trim($matches[1] ?? '');
    }
}
