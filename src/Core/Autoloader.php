<?php

namespace Cube\Core;

use Composer\Autoload\ClassLoader;
use Cube\Core\Autoloader\Applications;
use Cube\Core\Autoloader\AutoloaderConfiguration;
use Cube\Data\Bunch;
use Cube\Env\Cache;
use Cube\Env\Storage;
use Cube\Web\Http\Request;
use Cube\Web\Http\Response;
use Cube\Env\Logger\Logger;
use Cube\Event\Events\ApplicationsLoaded;
use Cube\Event\Events\FrameworkLoaded;
use Cube\Utils\Path;
use Cube\Utils\Shell;
use InvalidArgumentException;
use RuntimeException;

/**
 * @template Classmap of array<string,class-string>
 */
class Autoloader
{
    protected static ?AutoloaderConfiguration $configuration = null;

    protected static array $knownApplications = [];
    protected static array $assetsFiles = [];
    protected static array $requireFiles = [];
    protected static array $routesFiles = [];
    protected static array $viewFiles = [];

    /** @var array<int,string> */
    protected static array $addedExploredDirectories = [];

    /** @var array<class-string>|null */
    protected static ?array $classesList = null;
    protected static ?string $projectPath = null;

    protected static ?ClassLoader $loader;
    protected static ?array $classIndex = null;
    protected static ?Cache $autoloadCache = null;

    public static bool $loadedThroughApcu = false;
    protected static bool $booted = false;

    protected const APCU_KEY = __DIR__.'.autoload';

    public static function hasApcu(): bool
    {
        return function_exists('apcu_enabled') && apcu_enabled();
    }

    public static function clearApcuCache(): void
    {
        if (self::hasApcu())
            apcu_delete(new \APCUIterator('/^'.preg_quote(self::APCU_KEY, '/').'/'));
    }

    protected static function autoloadCache(): Cache
    {
        return self::$autoloadCache ??= Cache::getInstance()->child('autoloader');
    }

    public static function cleanCache(): void
    {
        self::autoloadCache()->clear();
        self::clearApcuCache();
    }

    public static function tryToLoadThroughApcu(): bool
    {
        if (!self::hasApcu())
            return false;

        $success = false;
        $autoloadFullData = apcu_fetch(self::APCU_KEY, $success);

        if (!$success)
            return false;

        if (!(is_array($autoloadFullData) && count($autoloadFullData)))
            return false;

        list(
            self::$knownApplications,
            self::$assetsFiles,
            self::$requireFiles,
            self::$routesFiles,
            self::$viewFiles,
            self::$projectPath,
        ) = $autoloadFullData;

        Path::resolveProjectPath(self::$projectPath);

        self::$loadedThroughApcu = true;
        return true;
    }

    public static function saveToApcu(): void
    {
        if (!(self::$booted && self::$configuration->cached && self::hasApcu()))
            return;

        apcu_store(self::APCU_KEY, [
            self::$knownApplications,
            self::$assetsFiles,
            self::$requireFiles,
            self::$routesFiles,
            self::$viewFiles,
            Path::getProjectPath(),
        ]);
    }

    public static function initialize(?string $forceProjectPath = null, ?AutoloaderConfiguration $configuration = null)
    {
        self::$booted = false;
        self::$configuration = null;
        self::forgetClassIndex();
        self::registerErrorHandlers();
        self::$loader = self::findClassLoader();


        $cubeSrc = (new Storage(__DIR__))->parent();
        $cubeHelpers = $cubeSrc->child('Helpers');
        foreach ($cubeHelpers->files() as $helperFile) {
            include_once $helperFile;
        }

        Path::resolveProjectPath($forceProjectPath);

        self::$configuration = $configuration ??= AutoloaderConfiguration::resolve();
        self::$autoloadCache = null;

        if ($configuration->cached) {
            if (self::tryToLoadThroughApcu()) {
                self::forgetClassIndex();
                (new FrameworkLoaded)->dispatch();
                self::includeRequireFiles();
                self::$booted = true;
                (new ApplicationsLoaded(self::$knownApplications))->dispatch();
                return;
            }

            self::$knownApplications = &self::autoloadCache()->getReference('apps', []);
            self::$assetsFiles = &self::autoloadCache()->getReference('assets', []);
            self::$requireFiles = &self::autoloadCache()->getReference('require', []);
            self::$routesFiles = &self::autoloadCache()->getReference('routes', []);
            self::$viewFiles = &self::autoloadCache()->getReference('views', []);
        }

        (new FrameworkLoaded)->dispatch();

        self::loadApplications();
        self::forgetClassIndex();
        self::includeRequireFiles();
        self::$booted = true;

        (new ApplicationsLoaded(self::$knownApplications))->dispatch();

        self::saveToApcu();
    }

    protected static function findClassLoader(): ClassLoader
    {
        foreach (spl_autoload_functions() as $autoloadFunction) {
            // Composer registers its loader as [ClassLoader, 'loadClass']
            if (!is_array($autoloadFunction))
                continue;

            $possibleLoader = $autoloadFunction[0] ?? null;
            if ($possibleLoader instanceof ClassLoader)
                return $possibleLoader;
        }

        throw new RuntimeException('Could not find composer ClassLoader among the registered autoloaders');
    }

    public static function includeRequireFiles(): void
    {
        foreach (self::$requireFiles as $file) {
            include_once Path::relative($file);
        }
    }

    public static function registerErrorHandlers(): void
    {
        /*
         * To use the same code a the exception handler,
         * we transform the error into an `ErrorException`
         */
        set_error_handler(function (int $code, string $message, string $file, int $line) {
            $exception = new \ErrorException($message, $code, 1, $file, $line);
            if (($code & E_DEPRECATED) || ($code & E_USER_DEPRECATED)) {
                $logger = Logger::forFile('warnings.csv');
                $logger->logThrowable($exception);
                return true;
            }

            return false;
        });

        /*
         * Exception kill the request if not handled :
         * - For web users : a simple 'Internal Server Error' is displayed (+ An error message in a debug environment)
         * - For CLI users : a message is displayed telling that an error occurred
         */
        set_exception_handler(function (\Throwable $exception) {
            while (ob_get_level()) {
                ob_end_clean();
            }

            try {
                $logger = Logger::forFile('fatal.csv');
                $logger->logThrowable($exception);

                if ('cli' === php_sapi_name()) {
                    exit(
                        "\n"
                        .'Oops ! Caught a '.$exception::class." \n"
                        .$exception->getMessage().' at '.$exception->getFile().':'.$exception->getLine()."\n"
                        ."Please read your logs for more informations \n"
                    );
                }

                $response = Response::fromThrowable($exception);
                Shell::logRequestAndResponseToStdOut(Request::fromGlobals(), $response);
                $response->exit();
            } catch (\Throwable $_) {
                // In case everything went wrong (even logging/events) !

                http_response_code(500);
                echo "Internal Server Error\n";

                exit;
            }
        });
    }


    public static function getRoutesFiles(): array
    {
        return self::$routesFiles;
    }

    public static function getAssetsFiles(): array
    {
        return self::$assetsFiles;
    }

    public static function getViewFiles(): array 
    {
        return self::$viewFiles;
    }

    public static function getRequireFiles(): array
    {
        return self::$requireFiles;
    }

    public static function getClassLoader(): ClassLoader
    {
        return self::$loader;
    }

    public static function addToExploreMap(string $path): void
    {
        $directory = realpath(is_dir($path) ? $path : Path::relative($path));
        if (!($directory && is_dir($directory)))
            throw new InvalidArgumentException("Cannot explore [{$path}] : it is not a directory");

        self::$addedExploredDirectories[] = $directory;
        self::forgetClassIndex();
    }

    /** @return array<int,string> */
    public static function getExploredDirectories(): array
    {
        return Bunch::of([dirname(__DIR__), ...self::$knownApplications, ...self::$addedExploredDirectories])
            ->map(realpath(...))
            ->filter()
            ->uniques()
            ->sort()
            ->get();
    }

    protected static function exploredDirectoriesKey(): string
    {
        return md5(implode("\n", self::getExploredDirectories()));
    }

    protected static function forgetClassIndex(): void
    {
        $forgotten = null;
        self::$classIndex = &$forgotten;
        self::$classesList = null;
    }

    protected static function loadClassIndex(): void
    {
        if (self::$classIndex !== null)
            return;

        if (!self::$configuration?->cached) {
            self::$classIndex = [];
            return;
        }

        $key = self::exploredDirectoriesKey();
        $hasApcu = self::hasApcu();
        $success = false;
        $index = $hasApcu ? apcu_fetch(self::APCU_KEY.'.classes.'.$key, $success) : null;
        if ($success) {
            self::$classIndex = $index;
            return;
        }

        self::$classIndex = &self::autoloadCache()->getReference('classes-'.$key, []);
        self::saveClassIndexToApcu();
    }

    protected static function saveClassIndexToApcu(): void
    {
        if (!(self::$configuration?->cached && self::hasApcu()))
            return;

        apcu_store(self::APCU_KEY.'.classes.'.self::exploredDirectoriesKey(), self::$classIndex);
    }

    /**
     * @return array<class-string>
     */
    public static function classesList(): array
    {
        return self::$classesList ??= self::loadClassesList();
    }

    /** @return array<class-string> */
    protected static function loadClassesList(): array
    {
        // The configuration being loaded can already query classes, before it says whether to cache them
        if (!self::$configuration?->cached)
            return self::scanClassesList();

        $key = self::exploredDirectoriesKey();
        $hasApcu = self::hasApcu();
        $success = false;
        $list = $hasApcu ? apcu_fetch(self::APCU_KEY.'.classes-list.'.$key, $success) : null;
        if ($success)
            return $list;

        $list = self::autoloadCache()->getOrSet('class-list-'.$key, self::scanClassesList(...));
        if ($hasApcu)
            apcu_store(self::APCU_KEY.'.classes-list.'.$key, $list);

        return $list;
    }

    /** @return class-string[] */
    protected static function scanClassesList(): array
    {
        $loader = self::getClassLoader();
        $exploredDirectories = self::getExploredDirectories();

        $classMapFiles = (new Bunch($loader->getClassMap()))
            ->map(realpath(...))
            ->filter()
            ->toArray();

        $isExplored = fn (string $file) => Bunch::of($exploredDirectories)
            ->any(fn ($directory) => Path::isInside($file, $directory));

        $classes = Bunch::fromKeys((new Bunch($classMapFiles))->filter($isExplored)->get());

        if (!$loader->isClassMapAuthoritative()) {
            $classes->push(...self::classesInPsr4Directories($loader, $exploredDirectories, array_flip($classMapFiles)));
        }

        return $classes->uniques()->values()->get();
    }

    /**
     * @param string[] $exploredDirectories
     * @param Classmap $classMapFiles
     * @return class-string[]
     */
    protected static function classesInPsr4Directories(ClassLoader $loader, array $exploredDirectories, array $classMapFiles): array
    {
        return Bunch::unzip($loader->getPrefixesPsr4())
        ->flatMap(function($pair) use ($exploredDirectories, $classMapFiles) {
            [$namespace, $prefixDirectories] = $pair;

            return Bunch::of($prefixDirectories)->map(realpath(...))
                ->filter()
                ->flatMap(fn($prefixDirectory) => self::classesOfPsr4Prefix($namespace, $prefixDirectory, $exploredDirectories, $classMapFiles));
        })
        ->get();
    }

    /**
     * @param string[] $exploredDirectories
     * @param Classmap $classMapFiles
     * @return class-string[]
     */
    protected static function classesOfPsr4Prefix(string $namespace, string $prefixDirectory, array $exploredDirectories, array $classMapFiles): array
    {
        return Bunch::of($exploredDirectories)
            ->map(fn($directory) => match (true) {
                Path::isInside($prefixDirectory, $directory) => $prefixDirectory,
                Path::isInside($directory, $prefixDirectory) => $directory,
                default => null,
            })
            ->filter()
            ->flatMap(fn($root) => self::classesInPsr4Directory($namespace, $prefixDirectory, $root, $classMapFiles))
            ->get();
    }

    /**
     * @param Classmap $classMapFiles
     * @return class-string[]
     */
    protected static function classesInPsr4Directory(string $namespace, string $prefixDirectory, string $root, array $classMapFiles): array
    {
        return Bunch::of((new Storage($root))->exploreFiles())
            ->filter(fn ($file) => str_ends_with($file, '.php'))
            ->filter(fn ($file) => !isset($classMapFiles[$file]))
            ->filter(self::declaresTypeNamedAfterFile(...))
            ->map(fn ($path) => $namespace.Path::toRelative($path, $prefixDirectory))
            ->map(fn ($path) => str_replace('/', '\\', $path))
            ->map(fn ($path) => preg_replace('/\.php$/', '', $path))
            ->values()
            ->get()
        ;
    }

    protected static function declaresTypeNamedAfterFile(string $file): bool
    {
        $expectedName = preg_quote(pathinfo($file, PATHINFO_FILENAME), '/');

        return (bool) preg_match("/\\b(class|interface|trait|enum)\\s+{$expectedName}\\b/", file_get_contents($file));
    }

    public static function extends(mixed $class, string $parentClass, bool $considerSelfAsExtending = true): bool
    {
        if (is_string($class) && (!self::classExists($class))) {
            return false;
        }

        $className = is_string($class) ? $class: $class::class;

        if ($considerSelfAsExtending && ($parentClass === $className)) {
            return true;
        }

        if ($parents = self::classParents($class)) {
            return in_array($parentClass, $parents);
        }

        return false;
    }

    public static function implements(mixed $class, string $interface): bool
    {
        if (!self::classExists($class)) {
            return false;
        }

        if ($implements = self::classImplements($class)) {
            return in_array($interface, $implements);
        }

        return false;
    }

    public static function uses(mixed $class, string $trait): bool
    {
        if (!self::classExists($class)) {
            return false;
        }

        if ($traits = self::classUses($class)) {
            return in_array($trait, $traits);
        }

        return false;
    }

    /**
     * @template TClass
     *
     * @param class-string<TClass> $parentClass
     *
     * @return array<class-string<TClass>>
     */
    public static function classesThatExtends(string $parentClass, bool $rejectAbstracts = true): array
    {
        self::loadClassIndex();
        self::$classIndex['extends'] ??= [];

        return self::filterClassesWithCache(
            self::$classIndex['extends'],
            ((string) $parentClass).($rejectAbstracts ? '' : '-r'),
            fn ($class) => self::extends($class, $parentClass, false),
            $rejectAbstracts
        );
    }

    /**
     * @template TInterface
     *
     * @param class-string<TInterface> $interface
     *
     * @return array<TInterface>
     */
    public static function classesThatImplements(string $interface, bool $rejectAbstracts = true): array
    {
        self::loadClassIndex();
        self::$classIndex['implements'] ??= [];

        return self::filterClassesWithCache(
            self::$classIndex['implements'],
            ((string) $interface).($rejectAbstracts ? '' : '-r'),
            fn ($class) => self::implements($class, $interface),
            $rejectAbstracts
        );
    }

    /**
     * @template TTrait
     *
     * @param class-string<TTrait> $trait
     *
     * @return array<TTrait>
     */
    public static function classesThatUses(string $trait, bool $rejectAbstracts = true): array
    {
        self::loadClassIndex();
        self::$classIndex['uses'] ??= [];

        return self::filterClassesWithCache(
            self::$classIndex['uses'],
            ((string) $trait).($rejectAbstracts ? '-r' : ''),
            fn ($class) => self::uses($class, $trait),
            $rejectAbstracts
        );
    }

    public static function classExists(string $class, bool $autoload = true): bool
    {
        try {
            return class_exists($class, $autoload)
                || interface_exists($class, $autoload)
                || trait_exists($class, $autoload);
        } catch (\Throwable $_) {
            return false;
        }
    }

    public static function classParents(mixed $class, bool $autoload = true): array
    {
        try {
            return class_parents($class, $autoload);
        } catch (\Throwable $_) {
            return [];
        }
    }

    public static function classImplements(mixed $class, bool $autoload = true): array
    {
        try {
            return class_implements($class, $autoload);
        } catch (\Throwable $_) {
            return [];
        }
    }

    /**
     * @return string[] Every trait `$class` uses, through its parents and the traits it uses
     */
    public static function classUses(mixed $class, bool $autoload = true): array
    {
        try {
            $traits = [];
            foreach ([$class, ...class_parents($class, $autoload)] as $classOrParent) {
                array_push($traits, ...array_values(class_uses($classOrParent, $autoload)));
            }

            foreach ($traits as $trait) {
                array_push($traits, ...self::classUses($trait, $autoload));
            }

            return array_values(array_unique($traits));
        } catch (\Throwable $_) {
            return [];
        }
    }

    protected static function loadApplications(): void
    {
        $apps = Applications::resolve();

        $appsToExplore = array_diff($apps->paths, self::$knownApplications);

        foreach ($appsToExplore as $app) {
            if (!is_dir($app)) {
                Logger::getInstance()->warning('Cannot load {app} directory, target is not a directory', ['app' => $app]);
                continue;
            }

            self::$knownApplications[] = $app;

            $appStorage = new Storage($app);
            foreach ($appStorage->directories() as $directory) {
                $dirName = basename($directory);

                switch ($dirName) {
                    case 'Routes':
                    case 'Router':
                        array_push(self::$routesFiles, ...(new Storage($directory))->exploreFiles());
                        break;

                    case 'Assets':
                        array_push(self::$assetsFiles, ...(new Storage($directory))->exploreFiles());
                        break;

                    case 'Requires':
                    case 'Includes':
                    case 'Helpers':
                    case 'Schedules':
                    case 'Cron':
                        array_push(self::$requireFiles, ...(new Storage($directory))->exploreFiles());
                        break;

                    case 'Views':
                        array_push(self::$viewFiles, ...(new Storage($directory))->exploreFiles());
                        break;
                }
            }
        }
    }

    /**
     * @return array<class-string>
     */
    protected static function filterClassesWithCache(array &$holder, string $identifier, callable $filter, bool $rejectAbstracts = true)
    {
        if ($preprocessed = $holder[$identifier] ?? false) {
            return $preprocessed;
        }

        $classes = Bunch::of(self::classesList())->filter($filter);

        if ($rejectAbstracts) {
            $classes = $classes->filter(function ($class) {
                $reflection = new \ReflectionClass($class);

                return !$reflection->isAbstract();
            });
        }

        $holder[$identifier] = $classes->values()->get();
        self::saveClassIndexToApcu();

        return $holder[$identifier];
    }
}
