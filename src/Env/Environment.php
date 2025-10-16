<?php

namespace Cube\Env;

use Cube\Core\Component;
use Cube\Env\Logger\Logger;
use Cube\Utils\Path;

use function Cube\debug;

class Environment
{
    use Component;

    protected array $content = [];

    public function __construct(?string $file = null)
    {
        $this->content = $_ENV;
        if ($file) {
            return $this->mergeWithFile($file);
        }
    }

    public static function getDefaultInstance(): static
    {
        $instance = new self();
        $instance->mergeWithFile('.env');

        return $instance;
    }

    public function mergeWithFile(string $file): self
    {
        $file = Path::relative($file);

        if (!is_file($file)) {
            Logger::getInstance()->warning('Environment: could not read [{file}]', ['file' => $file]);

            return $this;
        }

        $fileContent = file_get_contents($file);
        $safeFileContent = preg_replace("~^#.+~", "", $fileContent); # Support for comments

        $content = parse_ini_string($safeFileContent);
        $this->content = array_merge($this->content, $content);

        return $this;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->content[$key] ?? $default;
    }
}
