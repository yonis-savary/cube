<?php

namespace Tests\Integration;

use Cube\Test\CubeTestCase;

/**
 * @internal
 */
class PolicyTest extends CubeTestCase
{
    public function testUserPolicy()
    {
        $this->get('/user/1')->assertUnauthorized();
        $this->get('/user/2')->assertOk();
        $this->get('/user/999')->assertNotFound();
    }

    public function testModuleUserPolicy()
    {
        $this->get('/module-user/1')->assertUnauthorized();
        $this->get('/module-user/2')->assertOk();
        $this->get('/module-user/999')->assertNotFound();
    }

}
