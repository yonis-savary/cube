<?php

namespace Cube\Tests\Units\Env\Session;

use Cube\Env\Session\HasScopedSession;
use PHPUnit\Framework\TestCase;

class HasScopedSessionTest extends TestCase
{
    /** The scope mixed in the trait's own directory : moving the install path emptied every scoped session. */
    public function test_the_scope_only_depends_on_the_class()
    {
        $scoped = new class {
            use HasScopedSession;
        };

        $this->assertEquals(md5($scoped::class), $scoped->getScope());
    }
}
