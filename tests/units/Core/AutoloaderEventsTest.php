<?php

namespace Cube\Tests\Units\Core;

use Cube\Console\Command;
use Cube\Core\Autoloader;
use Cube\Core\Autoloader\Applications;
use Cube\Core\Autoloader\AutoloaderConfiguration;
use Cube\Env\Cache;
use Cube\Env\Cache\CacheConfiguration;
use Cube\Env\Cache\LocalDiskCache\LocalDiskCache;
use Cube\Env\Configuration;
use Cube\Event\Events;
use Cube\Event\Events\ApplicationsLoaded;
use Cube\Event\Events\FrameworkLoaded;
use Cube\Event\Events\PostDeployment;
use Cube\Tests\Units\Env\Classes\HasTemporaryStorage;
use PHPUnit\Framework\TestCase;

/**
 * `Autoloader::initialize()` runs again in each test, so it reinstalls its error and
 * exception handlers : they are popped back in `tearDown()` to leave PHPUnit's in place.
 *
 * @internal
 */
class AutoloaderEventsTest extends TestCase
{
    use HasTemporaryStorage;

    protected function setUp(): void
    {
        $this->setUpTemporaryStorage('autoloader-events-test-');
    }

    protected function tearDown(): void
    {
        restore_error_handler();
        restore_exception_handler();

        $this->tearDownTemporaryStorage();
    }

    /** @return array<class-string,object[]> */
    protected function initializeAndListen(?Configuration $configuration = null): array
    {
        $received = [FrameworkLoaded::class => [], ApplicationsLoaded::class => []];

        $events = new Events();
        foreach (array_keys($received) as $eventClass)
            $events->on($eventClass, function ($event) use (&$received) { $received[$event::class][] = $event; });

        $initialize = fn () => Events::withInstance(
            $events,
            fn () => Autoloader::initialize(realpath('.'), new AutoloaderConfiguration(false))
        );

        $configuration
            ? Configuration::withInstance($configuration, $initialize)
            : $initialize();

        return $received;
    }

    public function testInitializeDispatchesFrameworkLoadedThenApplicationsLoaded()
    {
        $received = $this->initializeAndListen();

        $this->assertCount(1, $received[FrameworkLoaded::class]);
        $this->assertCount(1, $received[ApplicationsLoaded::class]);
    }

    public function testApplicationsLoadedListsEveryKnownApplication()
    {
        $known = $this->initializeAndListen()[ApplicationsLoaded::class][0]->loaded;

        $app = $this->storage->child('ShopApp')->getRoot();
        restore_error_handler();
        restore_exception_handler();

        $received = $this->initializeAndListen(new Configuration(new Applications($app)));

        $this->assertEquals([...$known, $app], $received[ApplicationsLoaded::class][0]->loaded);
    }

    /**
     * A cached boot finds its applications already known and explores nothing : the event must
     * still list them, or a listener would hear a different list depending on the cache state.
     */
    public function testApplicationsLoadedListsAlreadyKnownApplicationsAgain()
    {
        $app = $this->storage->child('ShopApp')->getRoot();
        $configuration = new Configuration(new Applications($app));

        $this->initializeAndListen($configuration);
        restore_error_handler();
        restore_exception_handler();

        $received = $this->initializeAndListen($configuration);

        $this->assertContains($app, $received[ApplicationsLoaded::class][0]->loaded);
    }

    public function testApplicationsLoadedIgnoresMissingApplications()
    {
        $missing = $this->storage->path('Missing');

        $received = $this->initializeAndListen(new Configuration(new Applications($missing)));

        $this->assertNotContains($missing, $received[ApplicationsLoaded::class][0]->loaded);
    }

    public function testAnApplicationCanListenToItsOwnLoading()
    {
        $app = $this->storage->child('ShopApp');
        $app->child('Requires')->write('listeners.php', <<<'PHP'
            <?php

            Cube\Event\Events\ApplicationsLoaded::on(function ($event) {
                $GLOBALS['shop-app-heard'] = $event->loaded;
            });
            PHP);

        $this->initializeAndListen(new Configuration(new Applications($app->getRoot())));

        $this->assertContains($app->getRoot(), $GLOBALS['shop-app-heard'] ?? []);
        unset($GLOBALS['shop-app-heard']);
    }

    public function testPostDeploymentCleansTheAutoloaderCache()
    {
        $cache = new Cache(new CacheConfiguration(new LocalDiskCache($this->storage->child('Cache'))));
        $autoloaderCache = $cache->child('autoloader');

        Cache::withInstance($cache, fn () => Autoloader::initialize(realpath('.'), new AutoloaderConfiguration(true)));

        $this->assertTrue($autoloaderCache->has('apps'));

        // The listener lives in src/Helpers/setup.php, registered on the global dispatcher once
        (new PostDeployment())->dispatch();

        foreach (['classes', 'apps', 'assets', 'require', 'routes', 'views'] as $key)
            $this->assertFalse($autoloaderCache->has($key), "[{$key}] should be gone from the autoloader cache");
    }

    public function testACachedQueryIsAnsweredWithoutReadingTheClassesList()
    {
        $cacheStorage = $this->storage->child('Cache');
        $bootAndQuery = function () use ($cacheStorage) {
            $driver = new LocalDiskCache($cacheStorage);
            $commands = Cache::withInstance(new Cache(new CacheConfiguration($driver)), function () {
                Autoloader::initialize(realpath('.'), new AutoloaderConfiguration(true));
                restore_error_handler();
                restore_exception_handler();

                return Autoloader::classesThatExtends(Command::class);
            });
            $driver->save();

            return $commands;
        };

        $commands = $bootAndQuery();
        $classesListFiles = glob($cacheStorage->path('*_autoloader-class-list-*'));
        $this->assertCount(1, $classesListFiles);
        unlink($classesListFiles[0]);

        $this->assertEquals($commands, $bootAndQuery());
        $this->assertEmpty(glob($cacheStorage->path('*_autoloader-class-list-*')), 'The classes list should not be scanned again');

        // Back to an uncached autoloader : the next tests would otherwise write into the removed storage
        $this->initializeAndListen();
    }
}
