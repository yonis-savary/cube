<?php

namespace Cube\Utils;

use Cube\Core\Autoloader;
use Cube\Data\Bunch;

class Path
{
    /**
     * Normalize a path, making sure:
     * - use only slash '/', no backslashes '\'
     * - it do not contains any double slashes
     * - don't end with a slashes
     *
     * @param string $path Path to normalize
     * @return string Normalized path
     */
    public static function normalize(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        $path = preg_replace('/\/{2,}/', '/', $path);
        if ($path !== '/')
            $path = preg_replace('/\/$/', '', $path);

        return $path;
    }

    /**
     * Concat multiple path parts and normalize the results
     * (see `Path::normalize`)
     *
     * @param string ...$parts parts to join (null values will be ignored)
     * @return string normalized joined parts
     */
    public static function join(string ...$parts): string
    {
        $parts = array_filter($parts);
        return self::normalize(join('/', $parts));
    }


    /**
     * Make the given `$path` relative to `$reference`
     * @param string $path Relative part of the path
     * @param string $reference Reference base path (Project root is used if null)
     * @return string Joinded relative path
     */
    public static function relative(string $path, ?string $reference=null): string
    {
        $path = self::normalize($path);

        $reference ??= Autoloader::getProjectPath();
        $reference = self::normalize($reference);

        if (!str_starts_with($path, $reference))
            $path = self::join($reference, $path);

        return self::normalize($path);
    }

    /**
     * Only keep the relative part of the `$path` (relative to `$reference`)
     * @param string $path Relative part of the path
     * @param string $reference Reference base path (Project root is used if null)
     */
    public static function toRelative(string $path, ?string $reference=null): string
    {
        $path = self::normalize($path);

        $reference ??= Autoloader::getProjectPath();
        $reference = self::normalize($reference);

        $path = Text::dontStartsWith($path, $reference);
        $path = Text::dontStartsWith($path, "/");

        return self::normalize($path);
    }

    public static function pathToNamespace(string $directory): string
    {
        $loader = Autoloader::getClassLoader();

        $relDirectory = self::toRelative($directory);
        $prefixes = Bunch::of($loader->getPrefixesPsr4());
        foreach ($prefixes as $namespace => $path)
        {
            if ($relDirectory === $path)
                return $namespace;
        }

        $fallback = $relDirectory;
        $fallback = preg_replace_callback('/[a-z]-[a-z]/', fn($m) => $m[1] . strtoupper($m[2]), $fallback);
        $fallback = preg_replace_callback('/[a-z]\\/[a-z]/', fn($m) => $m[1] . strtoupper($m[2]), $fallback);
        $fallback = str_replace("/", "\\", $fallback);
        return $fallback;
    }
}