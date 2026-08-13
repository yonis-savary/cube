<?php

namespace Cube\Tests\Units\Env;

use Cube\Env\Cache;
use Cube\Env\Cache\CacheConfiguration;
use Cube\Env\Cache\LocalDiskCache\LocalDiskCache;
use Cube\Env\Configuration;
use Cube\Env\Configuration\GenericElement;
use Cube\Env\Configuration\Import;
use Cube\Env\Session\SessionConfiguration;
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

    public function test_an_unknown_generic_falls_back_on_the_default()
    {
        $config = new Configuration();

        $this->assertNull($config->resolveGeneric('unknown'));
        $this->assertEquals('fallback', $config->resolveGeneric('unknown', 'fallback'));
    }

    public function test_a_typed_element_is_resolved_by_its_class()
    {
        $element = new SessionConfiguration('shop');
        $config = new Configuration($element);

        $this->assertSame($element, $config->resolve(SessionConfiguration::class));
        $this->assertNull($config->resolve(CacheConfiguration::class));
    }

    public function test_an_element_resolves_through_its_whole_fallback_chain()
    {
        $registered = new Configuration(new SessionConfiguration('registered'));
        $empty = new Configuration();

        $this->assertEquals('registered', SessionConfiguration::resolve($registered)->namespace);
        $this->assertEquals('given', SessionConfiguration::resolve($empty, new SessionConfiguration('given'))->namespace);
        $this->assertEquals('cube', SessionConfiguration::resolve($empty)->namespace);
    }

    public function test_a_configuration_file_is_loaded_element_by_element()
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

    public function test_loading_a_missing_file_names_it()
    {
        $missing = $this->storage->path('does-not-exist.php');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage($missing);

        (new Configuration())->loadFile($missing);
    }

    public function test_a_configuration_file_must_return_an_array()
    {
        $file = $this->writeConfigurationFile('"not an array"');

        $this->expectException(\RuntimeException::class);

        (new Configuration())->loadFile($file);
    }

    public function test_a_configuration_file_must_only_return_elements()
    {
        $file = $this->writeConfigurationFile('["not an element"]');

        $this->expectException(\Exception::class);

        (new Configuration())->loadFile($file);
    }

    public function test_an_import_brings_the_elements_of_another_file()
    {
        $imported = $this->writeConfigurationFile('[
            new \Cube\Env\Configuration\GenericElement("imported", "from the other file"),
        ]');

        $config = new Configuration(new Import($imported));

        $this->assertEquals('from the other file', $config->resolveGeneric('imported'));
    }

    public function test_an_import_can_be_returned_by_a_configuration_file()
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

    public function test_an_import_of_a_missing_file_is_left_out()
    {
        $config = new Configuration(new Import($this->storage->path('does-not-exist.php')));

        $this->assertEquals('untouched', $config->resolveGeneric('imported', 'untouched'));
    }

    public function test_a_configuration_makes_a_cache_round_trip()
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

    public function test_loading_an_absent_cache_entry_says_so()
    {
        $config = (new Configuration())->identify('never-cached');

        $this->assertFalse($config->loadFromCache($this->newCache()));
    }

    public function test_the_cache_needs_an_identifier()
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
