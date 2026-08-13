<?php

namespace Cube\Tests\Units\Core;

use Cube\Core\Exceptions\ResponseException;
use Cube\Web\Http\Response;
use Cube\Web\Http\StatusCode;
use PHPUnit\Framework\TestCase;

class ResponseExceptionTest extends TestCase
{
    public function test_it_carries_the_response_to_send()
    {
        $response = Response::notFound('No such product');

        $exception = new ResponseException('Product is missing', $response);

        $this->assertSame($response, $exception->response);
        $this->assertEquals(StatusCode::NOT_FOUND, $exception->response->getStatusCode());
    }

    public function test_it_behaves_like_a_regular_exception()
    {
        $exception = new ResponseException('Product is missing', Response::notFound());

        $this->assertEquals('Product is missing', $exception->getMessage());
        $this->assertNotEmpty($exception->getFile());
        $this->assertNotEmpty($exception->getTraceAsString());
    }

    public function test_it_can_be_caught_as_a_throwable_carrying_its_answer()
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
