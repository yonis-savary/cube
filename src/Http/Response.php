<?php

namespace YonisSavary\Cube\Http;

class Response extends HttpMessage
{
    protected int $statusCode;

    public function __construct(
        int $statusCode=StatusCode::NO_CONTENT,
        string $body="",
        array $headers=[]
    )
    {
        $this->statusCode = $statusCode;
        $this->setBody($body);
        $this->setHeaders($headers);
    }

    public function display(bool $sendHeaders=true)
    {
        if ($sendHeaders)
        {
            foreach ($this->headers as $name => $value)
                header("$name: $value");
        }

        echo $this->getBody();
    }

    public function exit(bool $sendHeaders=true)
    {
        $this->display($sendHeaders);
        die;
    }
}