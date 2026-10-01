<?php

namespace Cube\Env;

use Cube\Core\Component;
use Cube\Env\Logger\Logger;
use Cube\Utils\Path;

class Environment
{
    use Component;

    // What the normal INI mode turned these words into : the raw mode, which reads `=` and `!`, keeps them as written
    protected const INI_KEYWORDS = [
        'true' => '1', 'on' => '1', 'yes' => '1',
        'false' => '', 'off' => '', 'no' => '', 'none' => '', 'null' => '',
    ];

    protected array $content = [];

    public function __construct(?string $file = null)
    {
        $this->content = array_merge(getenv(), $_ENV);

        if ($file) {
            $this->mergeWithFile($file);
        }
    }

    public static function getDefaultInstance(): static
    {
        $instance = new static();
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
        $safeFileContent = preg_replace("~^#.+~m", "", $fileContent); # Support for comments

        $content = parse_ini_string($safeFileContent, false, INI_SCANNER_RAW);

        if (false === $content) {
            Logger::getInstance()->warning('Environment: could not parse [{file}], it is left out', ['file' => $file]);

            return $this;
        }

        $content = array_map(fn (string $value) => self::INI_KEYWORDS[strtolower($value)] ?? $value, $content);

        $this->content = array_merge($this->content, $content);

        return $this;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->content[$key] ?? $default;
    }

    public function set(string $key, string $value): void {
        $this->content[$key] = $value;
    }
}
