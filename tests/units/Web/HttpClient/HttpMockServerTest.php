<?php

namespace Cube\Tests\Units\Web\HttpClient;

use Cube\Web\Http\MockServers;
use PHPUnit\Framework\TestCase;

class HttpMockServerTest extends TestCase
{
    public function testDirectRegistering() {
        $connector = new MathAppConnector();
        $connector->setMockServer(new MathAppMocker);

        $this->assertEquals(10, $connector->double(5));
    }

    public function testIndirectRegisteringThroughComponent() {
        $connector = new MathAppConnector();
        $mocker = new MathAppMocker();

        MockServers::removeInstance();
        MockServers::getInstance()->set($connector, $mocker);
        $this->assertEquals(10, $connector->double(5));

        MockServers::removeInstance();
        MockServers::getInstance()->set(MathAppConnector::class, $mocker);
        $this->assertEquals(10, $connector->double(5));

        MockServers::removeInstance();
        MockServers::getInstance()->set($connector, MathAppMocker::class);
        $this->assertEquals(10, $connector->double(5));

        MockServers::removeInstance();
        MockServers::getInstance()->set(MathAppConnector::class, MathAppMocker::class);
        $this->assertEquals(10, $connector->double(5));
    }

}