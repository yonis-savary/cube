<?php

namespace Cube\Tests\Units\Console;

use Cube\Console\Args;
use Cube\Console\Commands\DataToObject\Integration;
use Cube\Core\Autoloader\Applications;
use Cube\Env\Configuration;
use Cube\Tests\Units\Console\Classes\GeneratesIntoATestNamespace;
use Cube\Utils\Path;
use Cube\Web\Http\HttpClient;
use Cube\Web\Http\HttpMockServer;
use Cube\Web\Http\MockServers;
use Cube\Web\Http\Request;
use Cube\Web\Http\Response;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class IntegrationTest extends TestCase
{
    use GeneratesIntoATestNamespace;

    protected const FIXTURE = 'tests/units/Data/DataToObject/Generator/Fixtures/openapi-billing.json';
    protected const PETSTORE = 'tests/units/Data/DataToObject/Generator/Fixtures/openapi-petstore.json';

    protected ?string $receivedAuthorization = null;

    protected function setUp(): void
    {
        $document = file_get_contents(Path::relative(self::FIXTURE));

        MockServers::getInstance()->set(HttpClient::class, HttpMockServer::fromArray([
            '/openapi.json' => function (Request $request) use ($document) {
                $this->receivedAuthorization = $request->getHeader('Authorization');

                return Response::ok($document);
            },
            '/index.html' => Response::ok('<html></html>'),
        ]));
    }

    protected function tearDown(): void
    {
        MockServers::removeInstance();
        $this->removeGeneratedDirectory();
    }

    /**
     * @param string[] $argv
     *
     * @return array{int,string} exit code, output
     */
    protected function callCommand(array $argv): array
    {
        ob_start();
        $code = Integration::call(Args::fromArgv($argv));

        return [$code, ob_get_clean()];
    }

    public function testADocumentIsFetchedFromAUrlWithTheGivenHeaders()
    {
        // An endpoint matching nothing stops the command right after the document is read, before anything is written
        [$code, $output] = $this->callCommand(['https://billing.example.com/openapi.json', '--header=Authorization: Bearer secret', '--endpoint=nothing']);

        $this->assertEquals(1, $code);
        $this->assertStringContainsString('No operation matches [nothing]', $output);
        $this->assertEquals('Bearer secret', $this->receivedAuthorization);
    }

    public function testAnUnsuccessfulAnswerStopsTheCommand()
    {
        [$code, $output] = $this->callCommand(['https://billing.example.com/missing.json']);

        $this->assertEquals(1, $code);
        $this->assertStringContainsString('Could not fetch https://billing.example.com/missing.json : the server answered 404', $output);
    }

    public function testAnAnswerThatIsNotJsonStopsTheCommand()
    {
        [$code, $output] = $this->callCommand(['https://billing.example.com/index.html']);

        $this->assertEquals(1, $code);
        $this->assertStringContainsString('https://billing.example.com/index.html is not a valid JSON document', $output);
    }

    public function testAHeaderNeedsAUrl()
    {
        [$code, $output] = $this->callCommand([self::FIXTURE, '--header=Authorization: Bearer secret']);

        $this->assertEquals(1, $code);
        $this->assertStringContainsString('--header only applies when the document is fetched from a URL', $output);
    }

    public function testAHeaderWithoutColonIsRejected()
    {
        [$code, $output] = $this->callCommand(['https://billing.example.com/openapi.json', '--header=Authorization']);

        $this->assertEquals(1, $code);
        $this->assertStringContainsString('Header [Authorization] is not of the form "Name: value"', $output);
    }

    public function testADocumentGeneratesItsClientAndClassesIntoTheNamespace()
    {
        $namespace = $this->generatedNamespace().'\Petstore';

        [$code, $output] = $this->callCommand([self::PETSTORE, "--namespace={$namespace}"]);

        $this->assertEquals(0, $code, $output);

        $files = $this->writtenFiles($output);
        $this->assertCount(21, $files, 'One file per class, the client and its configuration');
        $this->assertContains(Path::join($this->generatedDirectory, 'Petstore/PetstoreClient.php'), $files);
        $this->assertContains(Path::join($this->generatedDirectory, 'Petstore/PetstoreConfiguration.php'), $files);
        $this->assertContains(Path::join($this->generatedDirectory, 'Petstore/Refs/Output/Pet.php'), $files);
        $this->assertContains(Path::join($this->generatedDirectory, 'Petstore/Http/Requests/FindPetsByStatusRequest.php'), $files);
        $this->assertGeneratedFilesAreValid($files);

        $getPetById = new \ReflectionMethod("{$namespace}\\PetstoreClient", 'getPetById');
        $this->assertEquals("{$namespace}\\Http\\Responses\\GetPetByIdResponse", (string) $getPetById->getReturnType());
        $this->assertEquals('https://petstore3.swagger.io/api/v3', (new ("{$namespace}\\PetstoreConfiguration"))->url);
    }

    public function testTheDefaultNamespaceIsNamedAfterTheDocumentTitle()
    {
        $this->generatedNamespace();
        mkdir($this->generatedDirectory);

        [$code, $output] = (new Configuration(new Applications($this->generatedDirectory)))
            ->asGlobalInstance(fn () => $this->callCommand([self::PETSTORE, '--endpoint=getInventory']))
        ;

        $this->assertEquals(0, $code, $output);
        $this->assertEqualsCanonicalizing([
            Path::join($this->generatedDirectory, 'Integration/SwaggerPetstoreOpenAPI30/SwaggerPetstoreOpenAPI30Configuration.php'),
            Path::join($this->generatedDirectory, 'Integration/SwaggerPetstoreOpenAPI30/SwaggerPetstoreOpenAPI30Client.php'),
        ], $this->writtenFiles($output));
    }

    public function testEndpointsRestrictTheGenerationToWhatTheyUse()
    {
        $namespace = $this->generatedNamespace().'\Petstore';

        [$code, $output] = $this->callCommand([self::PETSTORE, "--namespace={$namespace}", '--endpoint=getPetById', '--endpoint=post /store/order']);

        $this->assertEquals(0, $code, $output);
        $this->assertEqualsCanonicalizing(array_map(fn (string $file) => Path::join($this->generatedDirectory, "Petstore/{$file}.php"), [
            'Refs/Output/Category', 'Refs/Output/Tag', 'Refs/Output/Pet', 'Refs/Output/Order', 'Refs/Input/Order',
            'Http/Responses/GetPetByIdResponse', 'Http/Requests/PlaceOrderRequest', 'Http/Responses/PlaceOrderResponse',
            'PetstoreConfiguration', 'PetstoreClient',
        ]), $this->writtenFiles($output));
    }

    public function testLenientMakesEveryPropertyOptional()
    {
        $namespace = $this->generatedNamespace().'\Petstore';

        [$code, $output] = $this->callCommand([self::PETSTORE, "--namespace={$namespace}", '--endpoint=getPetById', '--lenient']);

        $this->assertEquals(0, $code, $output);
        $this->assertStringContainsString('public ?string $name = null,', file_get_contents(Path::join($this->generatedDirectory, 'Petstore/Refs/Output/Pet.php')));
    }

    public function testADocumentThatIsNotOpenApi3IsRejected()
    {
        $file = Path::join(sys_get_temp_dir(), 'swagger-'.bin2hex(random_bytes(6)).'.json');
        file_put_contents($file, '{"swagger": "2.0", "info": {"title": "Legacy", "version": "1"}, "paths": {}}');

        try {
            [$code, $output] = $this->callCommand([$file]);
        } finally {
            unlink($file);
        }

        $this->assertEquals(1, $code);
        $this->assertStringContainsString("{$file} is not an OpenAPI 3 document", $output);
    }

    public function testANamespaceOutsideEveryPsr4PrefixIsRejected()
    {
        [$code, $output] = $this->callCommand([self::PETSTORE, '--namespace=Nowhere\Petstore']);

        $this->assertEquals(1, $code);
        $this->assertStringContainsString('Namespace [Nowhere\Petstore] is outside every PSR-4 prefix of composer.json', $output);
    }

    public function testTheClientNameOptionNamesTheClientAndItsConfiguration()
    {
        $this->generatedNamespace();
        mkdir($this->generatedDirectory);

        [$code, $output] = (new Configuration(new Applications($this->generatedDirectory)))
            ->asGlobalInstance(fn () => $this->callCommand([self::PETSTORE, '--endpoint=getInventory', '--client-name=Petstore']))
        ;

        $this->assertEquals(0, $code, $output);
        $this->assertEqualsCanonicalizing([
            Path::join($this->generatedDirectory, 'Integration/SwaggerPetstoreOpenAPI30/PetstoreConfiguration.php'),
            Path::join($this->generatedDirectory, 'Integration/SwaggerPetstoreOpenAPI30/PetstoreClient.php'),
        ], $this->writtenFiles($output), 'The namespace still comes from the title');
    }

    public function testAClientNameThatIsNotAClassNameIsRejected()
    {
        [$code, $output] = $this->callCommand([self::PETSTORE, '--client-name=pet-store']);

        $this->assertEquals(1, $code);
        $this->assertStringContainsString('--client-name [pet-store] is not a class name starting with an uppercase letter', $output);
    }
}
