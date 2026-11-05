<?php

namespace Cube\Tests\Units\Env;

use Cube\Env\Cache;
use Cube\Env\Cache\CacheConfiguration;
use Cube\Env\Cache\LocalDiskCache\LocalDiskCache;
use Cube\Env\Configuration;
use Cube\Env\Configuration\GenericElement;
use Cube\Env\Configuration\Import;
use Cube\Env\Session\SessionConfiguration;
use Cube\Security\Authentication\AuthenticationConfiguration;
use Cube\Tests\Units\Env\Classes\HasTemporaryStorage;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class ConfigurationTest extends TestCase
{
    use HasTemporaryStorage;

    protected function setUp(): void
    {
        $this->setUpTemporaryStorage('configuration-test-');
    }

    protected function tearDown(): void
    {
        $this->tearDownTemporaryStorage();
    }

    public function testConstructAndResolve()
    {
        $config = new Configuration(
            new GenericElement('generic-1', ['mike' => 'bob']),
            new GenericElement('generic-2', ['bob' => 'mike']),
        );
        $generic = $config->resolveGeneric('generic-1', false);
        $this->assertEquals(['mike' => 'bob'], $generic);

        $generic = $config->resolveGeneric('generic-2', false);
        $this->assertEquals(['bob' => 'mike'], $generic);
    }

    public function testAnUnknownGenericFallsBackOnTheDefault()
    {
        $config = new Configuration();

        $this->assertNull($config->resolveGeneric('unknown'));
        $this->assertEquals('fallback', $config->resolveGeneric('unknown', 'fallback'));
    }

    public function testATypedElementIsResolvedByItsClass()
    {
        $element = new SessionConfiguration('shop');
        $config = new Configuration($element);

        $this->assertSame($element, $config->resolve(SessionConfiguration::class));
        $this->assertNull($config->resolve(CacheConfiguration::class));
    }

    public function testAnElementResolvesThroughItsWholeFallbackChain()
    {
        $registered = new Configuration(new SessionConfiguration('registered'));
        $empty = new Configuration();

        $this->assertEquals('registered', SessionConfiguration::resolve($registered)->namespace);
        $this->assertEquals('given', SessionConfiguration::resolve($empty, new SessionConfiguration('given'))->namespace);
        $this->assertEquals('cube', SessionConfiguration::resolve($empty)->namespace);
    }

    /** An element without defaults fell back on new static() and died on an ArgumentCountError naming no configuration. */
    public function testAMissingElementWithoutDefaultsAsksToDeclareIt()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(AuthenticationConfiguration::class.' has required options and no default');

        AuthenticationConfiguration::resolve(new Configuration());
    }

    public function testAConfigurationFileIsLoadedElementByElement()
    {
        $file = $this->writeConfigurationFile('[
            new \Cube\Env\Configuration\GenericElement("shop", ["currency" => "EUR"]),
            new \Cube\Env\Session\SessionConfiguration("shop"),
        ]');

        $config = new Configuration();
        $config->loadFile($file);

        $this->assertEquals(['currency' => 'EUR'], $config->resolveGeneric('shop'));
        $this->assertEquals('shop', $config->resolve(SessionConfiguration::class)->namespace);
    }

    public function testLoadingAMissingFileNamesIt()
    {
        $missing = $this->storage->path('does-not-exist.php');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage($missing);

        (new Configuration())->loadFile($missing);
    }

    public function testAConfigurationFileMustReturnAnArray()
    {
        $file = $this->writeConfigurationFile('"not an array"');

        $this->expectException(\RuntimeException::class);

        (new Configuration())->loadFile($file);
    }

    public function testAConfigurationFileMustOnlyReturnElements()
    {
        $file = $this->writeConfigurationFile('["not an element"]');

        $this->expectException(\Exception::class);

        (new Configuration())->loadFile($file);
    }

    public function testAnImportBringsTheElementsOfAnotherFile()
    {
        $imported = $this->writeConfigurationFile('[
            new \Cube\Env\Configuration\GenericElement("imported", "from the other file"),
        ]');

        $config = new Configuration(new Import($imported));

        $this->assertEquals('from the other file', $config->resolveGeneric('imported'));
    }

    public function testAnImportCanBeReturnedByAConfigurationFile()
    {
        $imported = $this->writeConfigurationFile('[
            new \Cube\Env\Configuration\GenericElement("imported", "from the other file"),
        ]');
        $main = $this->writeConfigurationFile(sprintf('[
            new \Cube\Env\Configuration\Import(%s),
        ]', var_export($imported, true)));

        $config = new Configuration();
        $config->loadFile($main);

        $this->assertEquals('from the other file', $config->resolveGeneric('imported'));
    }

    public function testAnImportOfAMissingFileIsLeftOut()
    {
        $config = new Configuration(new Import($this->storage->path('does-not-exist.php')));

        $this->assertEquals('untouched', $config->resolveGeneric('imported', 'untouched'));
    }

    public function testAConfigurationMakesACacheRoundTrip()
    {
        $cache = $this->newCache();

        $original = (new Configuration(
            new GenericElement('shop', ['currency' => 'EUR']),
            new SessionConfiguration('shop'),
        ))->identify('round-trip');
        $original->putToCache($cache);

        $restored = (new Configuration())->identify('round-trip');

        $this->assertTrue($restored->loadFromCache($cache));
        $this->assertEquals(['currency' => 'EUR'], $restored->resolveGeneric('shop'));
        $this->assertEquals('shop', $restored->resolve(SessionConfiguration::class)->namespace);
    }

    public function testACachedConfigurationCanBeRemoved()
    {
        $cache = $this->newCache();
        (new Configuration(new SessionConfiguration('shop')))->identify('removed')->putToCache($cache);

        (new Configuration())->identify('removed')->removeFromCache($cache);

        $this->assertFalse((new Configuration())->identify('removed')->loadFromCache($cache));
    }

    public function testLoadingAnAbsentCacheEntrySaysSo()
    {
        $config = (new Configuration())->identify('never-cached');

        $this->assertFalse($config->loadFromCache($this->newCache()));
    }

    public function testTheCacheNeedsAnIdentifier()
    {
        $this->expectException(\InvalidArgumentException::class);

        (new Configuration())->putToCache($this->newCache());
    }

    protected function newCache(): Cache
    {
        return new Cache(new CacheConfiguration(new LocalDiskCache($this->storage->child('cache'))));
    }

    protected function writeConfigurationFile(string $returnedExpression): string
    {
        $file = uniqid('configuration-').'.php';
        $this->storage->write($file, "<?php return {$returnedExpression};");

        return $this->storage->path($file);
    }
}
