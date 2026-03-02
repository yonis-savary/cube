<?php 

namespace Cube\Tests\Apcu;

use Cube\Web\Http\Request;
use PHPUnit\Framework\TestCase;

use function Cube\env;

class ApcuTest extends TestCase
{
    public function test_appLoadWithApcu() {
        $port = env('CUBE_TEST_NGINX_APCU_PORT', 9903);

        $request = new Request("GET", "localhost:$port/ping");

        $firstMessage = $request->fetch()->getJSON();
        $this->assertEquals("OK", $firstMessage['message']);
        $this->assertFalse($firstMessage['loaded_with_apcu']);

        $secondMessage = $request->fetch()->getJSON();
        $this->assertEquals("OK", $secondMessage['message']);
        $this->assertTrue($secondMessage['loaded_with_apcu']);
    }
}