<?php

namespace Cube\Tests\Units\Web\Classes;

use Cube\Web\Http\Rules\Param;
use Cube\Web\Http\Rules\Rule;
use Cube\Web\Websocket\Channel\Channel;
use Cube\Web\Websocket\Channel\ChannelSubscriber;
use Psr\Http\Message\RequestInterface;

class VoteChannel extends Channel
{
    public function getRoute(): string
    {
        return '/vote/{id}';
    }

    public function authorize(array $slugs, RequestInterface $request): ?string
    {
        return null;
    }

    public function getMessageRule(): ?Rule
    {
        return Param::object(['score' => Param::integer()]);
    }

    public function onMessage(ChannelSubscriber $from, array $data): void
    {
        if (0 === $data['score'])
            intdiv(1, $data['score']);

        $this->emit(['poll' => $from->slugs['id'], 'score' => $data['score']], [$from->slugs['id']]);
    }
}
