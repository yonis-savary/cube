<?php

namespace Cube\Tests\Units\Test;

use Cube\Test\ResponseAssert;
use Cube\Web\Http\Response;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class ResponseAssertTest extends TestCase
{
    /** 206 and 506 were asserted through misspelled names, `assertPatientContent` and `assertVariantAlsoNegociates`. */
    public function testStatusAssertionsAreNamedAfterTheirStatus()
    {
        (new ResponseAssert(Response::partialContent()))->assertPartialContent();
        (new ResponseAssert(Response::variantAlsoNegotiates()))->assertVariantAlsoNegotiates();
    }

    public function testJsonDecodesAsAnAssociativeArray()
    {
        $assert = new ResponseAssert(Response::json(['product' => ['name' => 'Glass']]));

        $this->assertSame(['product' => ['name' => 'Glass']], $assert->json());
    }

    /** The flag was passed as json_decode()'s $associative argument, so an invalid body silently gave null */
    public function testJsonThrowsOnAnInvalidBody()
    {
        $assert = new ResponseAssert(new Response(200, '{invalid'));

        $this->expectException(\JsonException::class);
        $assert->json();
    }
}
