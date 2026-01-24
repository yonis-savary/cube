<?php

namespace Tests\Integration;

use Cube\Test\CubeTestCase;

/**
 * @internal
 */
class StaticServerTest extends CubeTestCase
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
