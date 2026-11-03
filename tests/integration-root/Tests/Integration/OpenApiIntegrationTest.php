<?php

namespace Tests\Integration;

use Cube\Env\Configuration;
use Cube\Env\Storage;
use Cube\Test\CubeTestCase;
use Cube\Utils\Path;
use Cube\Utils\Shell;
use Symfony\Component\Process\Process;

/**
 * The application describes itself with `make:openapi`, `make:integration` turns that document into classes,
 * and the real answers of the application must hydrate them : the three have to agree
 *
 * @internal
 */
class OpenApiIntegrationTest extends CubeTestCase
{
    protected const NAMESPACE = 'Tests\\Generated\\IntegrationApi';
    protected const DIRECTORY = 'Tests/Generated';

    public static function setUpBeforeClass(): void
    {
        $root = Path::getProjectPath();

        // A failing `php do` command still exits with 0 : a missing document is what reveals a failed make:openapi
        @unlink(Path::join($root, 'Storage', 'openapi.json'));

        foreach ([
            ['php', 'do', 'make:openapi'],
            ['php', 'do', 'make:integration', 'Storage/openapi.json', '--namespace='.self::NAMESPACE],
        ] as $command) {
            $process = new Process($command, $root);
            $process->run();

            if (!$process->isSuccessful())
                throw new \RuntimeException(implode(' ', $command)." failed :\n".$process->getOutput().$process->getErrorOutput());
        }
    }

    public static function tearDownAfterClass(): void
    {
        Shell::executeInDirectory('rm -rf '.escapeshellarg(self::DIRECTORY), Path::getProjectPath());
    }

    protected function generatedClass(string $relativeName): string
    {
        return self::NAMESPACE.'\\'.$relativeName;
    }

    public function testEveryGeneratedClassIsValidPhp()
    {
        $files = (new Storage(Path::join(Path::getProjectPath(), self::DIRECTORY)))->exploreFiles();

        $this->assertNotEmpty($files);

        foreach ($files as $file) {
            $lint = new Process([PHP_BINARY, '-l', $file]);
            $lint->run();

            $this->assertTrue($lint->isSuccessful(), $lint->getOutput().file_get_contents($file));
        }
    }

    public function testTheAnswersOfTheApplicationHydrateTheirGeneratedClasses()
    {
        $stored = $this->postJson('/product', ['name' => 'Painting', 'price_dollar' => 120.5, 'managers' => []])
            ->assertOk()
            ->json();

        $created = $this->generatedClass('Http\\Responses\\StoreProductResponse')::fromData($stored);
        $this->assertEquals('Painting', $created->name);

        // `with: ['managers']` documents the relation as required on this endpoint only
        $managers = new \ReflectionParameter([$this->generatedClass('Http\\Responses\\StoreProductResponse'), '__construct'], 'managers');
        $this->assertFalse($managers->isOptional());

        $read = $this->generatedClass('Http\\Responses\\GetProductResponse')::fromData($this->get("/product/{$created->id}")->assertOk()->json());
        $this->assertEquals($created->id, $read->id);
        $this->assertEquals(120.5, $read->priceDollar);
        $this->assertSame([], $read->managers);
    }

    public function testTheApplicationAcceptsWhatARequestClassSends()
    {
        $request = new ($this->generatedClass('Http\\Requests\\StoreProductRequest'))('Painting', null, []);

        $this->assertEquals(['name' => 'Painting', 'price_dollar' => null, 'managers' => []], $request->toArray());
        $this->postJson('/product', $request->toArray())->assertOk();
    }

    public function testTheGeneratedClientCallsTheApplicationOverHttp()
    {
        $port = $this->freePort();
        $server = new Process([PHP_BINARY, '-S', "127.0.0.1:{$port}", 'index.php'], Path::join(Path::getProjectPath(), 'Public'));
        $server->start();

        try {
            $this->waitForPort($port, $server);

            $configuration = new ($this->generatedClass('IntegrationApiConfiguration'))(url: "http://127.0.0.1:{$port}");

            [$created, $read] = (new Configuration($configuration))->asGlobalInstance(function () {
                $client = new ($this->generatedClass('IntegrationApiClient'))();

                $created = $client->storeProduct(new ($this->generatedClass('Http\\Requests\\StoreProductRequest'))('Painting', 120.5, []));

                return [$created, $client->getProduct($created->id)];
            });
        } finally {
            $server->stop();
        }

        $this->assertInstanceOf($this->generatedClass('Http\\Responses\\StoreProductResponse'), $created);
        $this->assertInstanceOf($this->generatedClass('Http\\Responses\\GetProductResponse'), $read);
        $this->assertEquals($created->id, $read->id);
        $this->assertEquals('Painting', $read->name);
        $this->assertEquals(120.5, $read->priceDollar);
    }

    protected function freePort(): int
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        $port = (int) substr(strrchr(stream_socket_get_name($socket, false), ':'), 1);
        fclose($socket);

        return $port;
    }

    protected function waitForPort(int $port, Process $server): void
    {
        for ($i = 0; $i < 50; $i++) {
            // A refused connection is the expected outcome until the server has bound its port
            if ($connection = @fsockopen('127.0.0.1', $port)) {
                fclose($connection);
                return;
            }

            usleep(100_000);
        }

        $this->fail("The application server never opened port {$port} : ".$server->getErrorOutput());
    }
}
