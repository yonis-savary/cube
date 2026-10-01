<?php

namespace Cube\Utils;

use Cube\Core\Autoloader;
use Cube\Data\Bunch;

class Path
{
    protected static ?string $projectPath = null;

    /**
     * Normalize a path, making sure:
     * - use only slash '/', no backslashes '\'
     * - it do not contains any double slashes
     * - don't end with a slashes
     *
     * @param string $path Path to normalize
     *
     * @return string Normalized path
     */
    public static function normalize(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        $path = preg_replace('/\/{2,}/', '/', $path);
        if ('/' !== $path) {
            $path = preg_replace('/\/$/', '', $path);
        }

        return $path;
    }

    /**
     * Concat multiple path parts and normalize the results
     * (see `Path::normalize`).
     *
     * @param string ...$parts parts to join (null values will be ignored)
     *
     * @return string normalized joined parts
     */
    public static function join(string ...$parts): string
    {
        $parts = array_filter($parts, fn (string $part) => '' !== $part);

        return self::normalize(join('/', $parts));
    }

    /**
     * Make the given `$path` relative to `$reference`.
     *
     * @param string $path      Relative part of the path
     * @param string $reference Reference base path (Project root is used if null)
     *
     * @return string Joinded relative path
     */
    public static function relative(string $path, ?string $reference = null): string
    {
        $path = self::normalize($path);

        $reference ??= self::getProjectPath();
        $reference = self::normalize($reference);

        if (!self::isInside($path, $reference)) {
            $path = self::join($reference, $path);
        }

        return self::normalize($path);
    }

    /**
     * Same as `Path::relative()`, but `.` and `..` are resolved inside `$reference`, which the result never leaves
     */
    public static function confined(string $path, string $reference): string
    {
        $path = self::normalize($path);
        $reference = self::normalize($reference);

        if (self::isInside($path, $reference)) {
            $path = substr($path, strlen($reference));
        }

        $segments = [];
        foreach (explode('/', $path) as $segment) {
            if ('' === $segment || '.' === $segment) {
                continue;
            }

            if ('..' === $segment) {
                array_pop($segments);
                continue;
            }

            $segments[] = $segment;
        }

        return self::join($reference, ...$segments);
    }

    public static function isInside(string $path, string $directory): bool
    {
        return $path === $directory
            || str_starts_with($path, rtrim($directory, '/').'/');
    }

    /**
     * Only keep the relative part of the `$path` (relative to `$reference`).
     *
     * @param string $path      Relative part of the path
     * @param string $reference Reference base path (Project root is used if null)
     */
    public static function toRelative(string $path, ?string $reference = null): string
    {
        $path = self::normalize($path);

        $reference ??= self::getProjectPath();
        $reference = self::normalize($reference);

        $path = Text::dontStartsWith($path, $reference);
        $path = Text::dontStartsWith($path, '/');

        return self::normalize($path);
    }

    public static function pathToNamespace(string|\Stringable $directory): string
    {
        $directory = realpath((string) $directory) ?: (string) $directory;
        $directory = self::normalize($directory);

        $matchingNamespace = null;
        $matchingRoot = '';
        foreach (Autoloader::getClassLoader()->getPrefixesPsr4() as $namespace => $roots) {
            foreach ($roots as $root) {
                $root = realpath($root) ?: $root;
                $root = self::normalize($root);

                if (!self::isInside($directory, $root) || strlen($root) <= strlen($matchingRoot))
                    continue;

                $matchingNamespace = $namespace;
                $matchingRoot = $root;
            }
        }

        if (null !== $matchingNamespace) {
            $subNamespace = str_replace('/', '\\', trim(substr($directory, strlen($matchingRoot)), '/'));

            return rtrim($matchingNamespace.$subNamespace, '\\');
        }

        return Bunch::fromExplode('/', self::toRelative($directory))
            ->filter()
            ->map(fn (string $segment) => str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $segment))))
            ->join('\\');
    }

    public static function resolveProjectPath(?string $forceProjectPath = null): void
    {
        if ($forceProjectPath) {
            self::$projectPath = $forceProjectPath;
            return;
        }

        try {
            while (!is_dir('./vendor/yonis-savary/cube')) {
                chdir('..');
            }

            self::$projectPath = getcwd();
        } catch (\Throwable $_) {
            throw new \Exception('Could not resolve project root path');
        }
    }

    public static function getProjectPath(): string
    {
        if (is_null(self::$projectPath)) {
            self::resolveProjectPath();
        }

        return self::$projectPath;
    }
}
