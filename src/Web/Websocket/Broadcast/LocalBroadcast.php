<?php

namespace Cube\Web\Websocket\Broadcast;

use Cube\Web\Websocket\Servers\WebsocketRouter;

/**
 * Replaces `Broadcast` inside the websocket process : a blocking HTTP call to
 * itself could never be answered, as the event loop would be busy waiting for it.
 */
class LocalBroadcast extends Broadcast
{
    public function __construct(
        BroadcastConfiguration $configuration,
        protected readonly WebsocketRouter $router
    ) {
        parent::__construct($configuration);
    }

    /**
     * @param array<string,mixed> $data
     */
    public function emit(string $event, array $data): bool
    {
        if (!$channel = $this->router->getChannel($data['__class'] ?? ''))
            return false;

        unset($data['__class']);
        $channel->dispatch('/'.trim($event, '/'), $data);

        return true;
    }
}
