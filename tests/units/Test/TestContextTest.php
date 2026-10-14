<?php

namespace Cube\Tests\Units\Test;

use Cube\Core\RequestContext;
use Cube\Data\Database\Database;
use Cube\Env\Storage;
use Cube\Test\TestContext;
use Cube\Tests\Units\Env\Classes\HasTemporaryStorage;
use Cube\Utils\Path;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

/**
 * @internal
 */
class TestContextTest extends TestCase
{
    use HasTemporaryStorage;

    protected function setUp(): void
    {
        $this->setUpTemporaryStorage('test-context-test-');
    }

    protected function tearDown(): void
    {
        $this->tearDownTemporaryStorage();
    }

    /** replaceGlobalInstances() called Storage::getInstance($storage), which ignores its argument. */
    public function testTheGivenStorageBecomesTheGlobalOne()
    {
        $global = RequestContext::oneShot(function () {
            new TestContext($this->storage);

            return Storage::getInstance();
        });

        $this->assertSame($this->storage, $global);
    }

    /** The context resolved the database of cube.php, which then had to be reachable from where tests run. */
    public function testTheConfiguredDatabaseIsNotOpenedUpfront()
    {
        $opened = RequestContext::oneShot(function () {
            new TestContext($this->storage);

            return Database::hasInstance();
        });

        $this->assertFalse($opened);
    }

    /** Every test run left a Storage/Tests-* directory behind, test databases included. */
    public function testTheTemporaryStorageIsRemovedWhenTheProcessEnds()
    {
        $process = new Process([
            PHP_BINARY, '-r',
            'require $argv[1]; $storage = Cube\Test\TestContext::useTemporaryStorage(new Cube\Env\Storage($argv[2])); $storage->write("app.sqlite", "x"); echo $storage->getRoot();',
            Path::join(Path::getProjectPath(), 'vendor/autoload.php'),
            $this->storage->getRoot(),
        ], timeout: 30);
        $process->run();

        $this->assertNotEmpty($process->getOutput(), $process->getErrorOutput());
        $this->assertDirectoryDoesNotExist($process->getOutput());
    }
}
