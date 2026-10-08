<?php

namespace Cube\Tests\Units\Web;

use Cube\Env\Storage;
use Cube\Tests\Units\Models\__cubeMigration;
use Cube\Tests\Units\Models\Product;
use Cube\Web\ModelAPI\ModelAPI;
use Cube\Web\ModelAPI\ModelAPIGenerator;
use PHPUnit\Framework\TestCase;

class ModelAPIGeneratorTest extends TestCase
{
    const NAMESPACE = 'Cube\\Tests\\Units\\Web\\GeneratedApis';

    protected Storage $storage;

    protected function setUp(): void
    {
        $this->storage = Storage::getInstance()->child(uniqid('apis-'));
    }

    protected function tearDown(): void
    {
        foreach ($this->storage->files() as $file)
            unlink($file);

        rmdir($this->storage->getRoot());
    }

    public function testAnApiIsWrittenForAModel()
    {
        $file = (new ModelAPIGenerator())->generateInto($this->storage, self::NAMESPACE, Product::class);

        $this->assertEquals($this->storage->path('ProductAPI.php'), $file);

        require $file;
        $apiClass = self::NAMESPACE.'\\ProductAPI';
        $api = new $apiClass();

        $this->assertInstanceOf(ModelAPI::class, $api);
        $this->assertEquals(Product::class, $api->getModelClass());
    }

    public function testAnExistingApiIsLeftUntouched()
    {
        $this->storage->write('ProductAPI.php', '<?php // overridden by hand');

        $file = (new ModelAPIGenerator())->generateInto($this->storage, self::NAMESPACE, Product::class);

        $this->assertNull($file);
        $this->assertEquals('<?php // overridden by hand', $this->storage->read('ProductAPI.php'));
    }

    public function testFrameworkTablesAreSkipped()
    {
        $file = (new ModelAPIGenerator())->generateInto($this->storage, self::NAMESPACE, __cubeMigration::class);

        $this->assertNull($file);
        $this->assertFalse($this->storage->exists('__cubeMigrationAPI.php'));
    }
}
