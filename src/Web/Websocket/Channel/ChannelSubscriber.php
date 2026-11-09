<?php

namespace Cube\Web\Websocket\Channel;

use Ratchet\ConnectionInterface;

class ChannelSubscriber
{
    private int $windowStart = 0;
    private int $messagesInWindow = 0;

    /**
     * @param array<string,string> $slugs Slug values of the subscribed path
     */
    public function __construct(
        public string $path,
        public ConnectionInterface $connection,
        public array $slugs = [],
    ) {}

    /**
     * @param array<string,mixed> $data
     */
    public function send(array $data): void
    {
        $this->connection->send(json_encode($data, JSON_THROW_ON_ERROR));
    }

    /**
     * @return bool `true` while this subscriber stays under the limit of the current one-minute window
     */
    public function consumeMessageAllowance(int $maxMessagesPerMinute, ?int $now = null): bool
    {
        $now ??= time();

        if ($now - $this->windowStart >= 60) {
            $this->windowStart = $now;
            $this->messagesInWindow = 0;
        }

        return ++$this->messagesInWindow <= $maxMessagesPerMinute;
    }
}
