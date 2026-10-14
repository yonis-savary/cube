<?php

namespace Cube\Tests\Units\Env;

use Cube\Tests\Units\Env\Classes\SupportInbox;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class UserComponentTest extends TestCase
{
    /** `getDefaultInstance()` called `self::getAuthentication()`, so a subclass could not bring its own. */
    public function testASubclassCanProvideItsOwnAuthentication()
    {
        $this->assertEquals(42, SupportInbox::getDefaultInstance()->owner);
    }
}
