<?php

namespace Cube\Tests\Units\Web\Classes;

use Cube\Web\Websocket\Channel\Channel;
use Psr\Http\Message\RequestInterface;

class AnnouncementChannel extends Channel
{
    public function getRoute(): string
    {
        return '/announcements';
    }

    public function authorize(array $slugs, RequestInterface $request): ?string
    {
        return 'Bearer reader' === $request->getHeaderLine('Authorization')
            ? null
            : 'Invalid token';
    }
}
