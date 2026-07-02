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

        $tmpFile = tempnam(sys_get_temp_dir(), 'fakefile');
        $this->assertFileExists($tmpFile);
        $this->get($tmpFile)
            ->assertNotFound();
        
        $file = $this->get('/etc/passwd')
            ->assertOk()
            ->body()
        ;
        $this->assertEquals('Hello from passwd', $file);
    }
}
