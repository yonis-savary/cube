<?php

namespace Cube\Tests\Units\Web\Classes;

use Cube\Web\Websocket\Channel\Channel;
use Psr\Http\Message\RequestInterface;

class PrivateChannel extends Channel
{
    public function getRoute(): string
    {
        return '/private/{id}';
    }

    public function authorize(array $slugs, RequestInterface $request): ?string
    {
        return 'Bearer token-'.$slugs['id'] === $request->getHeaderLine('Authorization')
            ? null
            : 'Invalid token';
    }
}
