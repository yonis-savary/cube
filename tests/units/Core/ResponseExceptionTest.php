<?php

namespace Cube\Tests\Units\Core;

use Cube\Core\Exceptions\ResponseException;
use Cube\Web\Http\Response;
use Cube\Web\Http\StatusCode;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class ResponseExceptionTest extends TestCase
{
    public function testItCarriesTheResponseToSend()
    {
        $response = Response::notFound('No such product');

        $exception = new ResponseException('Product is missing', $response);

        $this->assertSame($response, $exception->response);
        $this->assertEquals(StatusCode::NOT_FOUND, $exception->response->getStatusCode());
    }

    public function testItBehavesLikeARegularException()
    {
        $exception = new ResponseException('Product is missing', Response::notFound());

        $this->assertEquals('Product is missing', $exception->getMessage());
        $this->assertNotEmpty($exception->getFile());
        $this->assertNotEmpty($exception->getTraceAsString());
    }

    public function testItCanBeCaughtAsAThrowableCarryingItsAnswer()
    {
        $deepService = function () {
            throw new ResponseException('Unauthorized', Response::unauthorized('Token expired'));
        };

        try {
            $deepService();
            $this->fail('The exception should have been thrown');
        } catch (ResponseException $exception) {
            $this->assertEquals(StatusCode::UNAUTHORIZED, $exception->response->getStatusCode());
            $this->assertStringContainsString('Token expired', $exception->response->getBody());
        }
    }
}
