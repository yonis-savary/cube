<?php

namespace Cube\Tests\Integration;

use Cube\Test\CubeIntegrationTestCase;

/**
 * @internal
 */
class StaticServerTest extends CubeIntegrationTestCase
{
    public function testServesFiles()
    {
        $body = $this->get('/')
            ->assertOk()
            ->body()
        ;
        $this->assertStringContainsString("I'm a document", $body);

        $file = $this->get('/my-file.txt')
            ->assertOk()
            ->body()
        ;
        $this->assertEquals('Hello!', $file);
    }
}
