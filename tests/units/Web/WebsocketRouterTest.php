<?php

namespace Cube\Tests\Units\Web;

use App\Channels\ProductChannel;
use Cube\Web\Websocket\Servers\WebsocketRouter;
use PHPUnit\Framework\TestCase;
use React\Http\Message\ServerRequest;

/**
 * @internal
 */
class WebsocketRouterTest extends TestCase
{
    /** The broadcast HTTP endpoint relays any POST to subscribers, with no credential asked. */
    public function testAnAnonymousBroadcastIsRefused()
    {
        $request = new ServerRequest(
            'POST',
            'http://localhost:8089/product/1',
            ['Content-Type' => 'application/json'],
            json_encode(['__class' => ProductChannel::class, 'message' => 'forged'])
        );

        $response = ((new WebsocketRouter())->getHttpServerCallback())($request);

        $this->assertGreaterThanOrEqual(400, $response->getStatusCode());
    }
}
