<?php

namespace Cube\Http;

abstract class HttpMessage
{
    protected array $headers = [];
    protected string $body = "";

    public function isJSON(): bool
    {
        return str_contains($this->getHeader("content-type", ""), "application/json");
    }

    public function headerName(string $name): string
    {
        return strtolower(trim($name));
    }

    public function setHeader(string $name, string $value): self
    {
        $this->headers[$this->headerName($name)] = $value;
        return $this;
    }

    public function setHeaders(array $data): void
    {
        foreach ($data as $key => $value)
            $this->setHeader($key, $value);
    }

    public function getHeader(string $name, ?string $default=null): ?string
    {
        return $this->headers[$this->headerName($name)] ?? $default;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function setBody(string $body): void
    {
        $this->body = $body;
    }
}