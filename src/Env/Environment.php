<?php

namespace Cube\Env;

use Cube\Core\Component;
use Cube\Logger\Logger;
use Cube\Utils\Path;

class Environment
{
    use Component;

    protected array $content = [];

    public static function getDefaultInstance(): static
    {
        $instance = new self();
        $instance->mergeWithFile(".env");
        return $instance;
    }

    public function mergeWithFile(string $file): self
    {
        $file = Path::relative($file);

        if (!is_file($file))
        {
            Logger::getInstance()->warning("Environment: could not read [{file}]", ["file" => $file]);
            return $this;
        }

        $content = parse_ini_file($file);
        $this->content = array_merge($this->content, $content);
        return $this;
    }

    public function get(string $key, mixed $default=null): mixed
    {
        return $this->content[$key] ?? $default;
    }
}