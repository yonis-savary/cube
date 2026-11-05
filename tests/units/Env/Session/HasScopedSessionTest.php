<?php

namespace Cube\Tests\Units\Env\Session;

use Cube\Env\Session\HasScopedSession;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class HasScopedSessionTest extends TestCase
{
    /** The scope mixed in the trait's own directory : moving the install path emptied every scoped session. */
    public function testTheScopeOnlyDependsOnTheClass()
    {
        $scoped = new class {
            use HasScopedSession;
        };

        $this->assertEquals(md5($scoped::class), $scoped->getScope());
    }
}
