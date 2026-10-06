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

    public function test_loading_events_are_dispatched_with_and_without_apcu()
    {
        $port = env('CUBE_TEST_NGINX_APCU_PORT', 9903);

        (new Request("GET", "localhost:$port/"))->fetch();
        $request = new Request("GET", "localhost:$port/ping");

        foreach ([false, true] as $expectedApcu) {
            $message = $request->fetch()->getJSON();

            $this->assertEquals($expectedApcu, $message['loaded_with_apcu']);
            $this->assertTrue($message['framework_loaded']);
            $this->assertCount(1, $message['applications_loaded']);
            $this->assertMatchesRegularExpression('~(^|/)App$~', $message['applications_loaded'][0]);
        }
    }
}
