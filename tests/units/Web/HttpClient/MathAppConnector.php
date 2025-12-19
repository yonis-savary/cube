<?php

namespace Cube\Tests\Units\Web\HttpClient;

use Cube\Web\Http\HttpClient;

class MathAppConnector extends HttpClient
{
    public function baseURL(): ?string
    {
        return "http://some-math-api/";
    }

    public function double(int $number) : int {
        $response = $this->get("/double/$number");
        return (int) $response->getJSON()['result'];
    }
}