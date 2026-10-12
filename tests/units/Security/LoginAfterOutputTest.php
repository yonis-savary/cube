<?php

namespace Cube\Tests\Units\Security;

use Cube\Web\Helpers\CubeServer;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class LoginAfterOutputTest extends TestCase
{
    /** Once output started the session id could not be regenerated, and the login went on with a possibly planted one. */
    public function testALoginAfterOutputIsRefused()
    {
        $port = random_int(20001, 30000);
        $server = new CubeServer($port, __DIR__.'/LoginAfterOutputServer');

        $body = file_get_contents("http://localhost:{$port}/");

        $this->assertEquals('output before the login, refused', $body);

        unset($server);
    }
}
