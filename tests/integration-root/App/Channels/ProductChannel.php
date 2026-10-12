<?php

namespace App\Channels;

use Cube\Web\Websocket\Channel\Channel;
use Psr\Http\Message\RequestInterface;

class ProductChannel extends Channel
{
    public function getRoute(): string
    {
        return "/product/{id}";
    }

    public function authorize(array $slugs, RequestInterface $request): ?string
    {
        return null;
    }
}
