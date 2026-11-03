<?php

namespace Cube\Tests\Units\Data\DataToObject\Generator;

use Cube\Data\DataToObject\Generator\Sources\OpenApiSource;
use Cube\Env\Configuration;
use Cube\Utils\Path;
use Cube\Web\Http\HttpMockServer;
use Cube\Web\Http\MockServers;
use Cube\Web\Http\Request;
use Cube\Web\Http\Response;
use Cube\Web\Router\Route;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class ClientWriterTest extends TestCase
{
    use GeneratesClasses;

    /** @var Request[] */
    protected array $received = [];

    protected function source(): OpenApiSource
    {
        return new OpenApiSource(json_decode(file_get_contents(Path::join(__DIR__, 'Fixtures', 'openapi-billing.json')), true, flags: JSON_THROW_ON_ERROR));
    }

    protected function generateClient(): object
    {
        $source = $this->source();
        $this->writer()->write($source->toClasses());
        $this->clientWriter()->write($source->toClient());
        $this->autoloadGeneratedClasses();

        $client = new ($this->generatedClass('ApiClient'))();

        $remember = fn (Response $response) => function (Request $request) use ($response) {
            $this->received[] = $request;

            return $response;
        };

        MockServers::getInstance()->set($client::class, HttpMockServer::fromRoutes(
            Route::post('/products', $remember(Response::json(['id' => 7, 'label' => 'Pen', 'category' => ['id' => 2]]))),
            Route::get('/products', $remember(Response::json([['id' => 7, 'label' => 'Pen', 'category' => ['id' => 2]]]))),
            Route::get('/invoices/{int:id}', $remember(Response::json(['created_at' => '2026-01-01', 'number' => 'F-1', 'status' => 'paid', 'lines' => []]))),
        ));

        return $client;
    }

    protected function tearDown(): void
    {
        MockServers::removeInstance();

        if ($this->generatedClassesLoader)
            spl_autoload_unregister($this->generatedClassesLoader);

        $this->tearDownTemporaryStorage();
    }

    protected function withToken(string $token, callable $callback): mixed
    {
        return (new Configuration(new ($this->generatedClass('ApiConfiguration'))(bearerAuth: $token)))->asGlobalInstance($callback);
    }

    public function testTheClientAndItsConfigurationAreNamedAfterTheNamespace()
    {
        $client = $this->source()->toClient();

        $this->assertStringContainsString('class ApiClient extends HttpClient', $this->clientWriter()->renderClient($client));
        $this->assertStringContainsString("public readonly ?string \$url = 'https://eu.billing.example.com/v1',", $this->clientWriter()->renderConfiguration($client));
        $this->assertStringContainsString('public readonly ?string $bearerAuth = null,', $this->clientWriter()->renderConfiguration($client));
    }

    public function testAnOperationNamesItsPathParametersAndQueryArguments()
    {
        $source = $this->clientWriter()->renderClient($this->source()->toClient());

        $this->assertStringContainsString('public function getInvoicesId(int $id): GetInvoicesIdResponse', $source);
        $this->assertStringContainsString('public function createProduct(CreateProductRequest $request, ?bool $dryRun = null): CreateProductResponse', $source);
        $this->assertStringContainsString('public function listProducts(?ListProductsRequest $request = null): array', $source);
        $this->assertStringContainsString('@return list<Product>', $source);
    }

    public function testABodyIsSentAsJsonWithTheKeysOfTheDocument()
    {
        $client = $this->generateClient();

        $request = new ($this->generatedClass('Http\\Requests\\CreateProductRequest'))('Pen');
        $request->unitPrice = 1.5;

        $product = $client->createProduct($request, dryRun: true);

        $this->assertInstanceOf($this->generatedClass('Http\\Responses\\CreateProductResponse'), $product);
        $this->assertEquals(7, $product->id);

        $sent = $this->received[0];
        $this->assertEquals(['label' => 'Pen', 'unit-price' => 1.5], json_decode($sent->getBody(), true));
        $this->assertEquals(['dry_run' => 1], $sent->get());
    }

    public function testTheConfigurationGivesTheUrlAndTheCredentials()
    {
        $client = $this->generateClient();

        $this->assertEquals('https://eu.billing.example.com/v1', $client->baseURL());

        $this->withToken('secret', fn () => $client->getInvoicesId(12));
        $this->assertEquals('Bearer secret', $this->received[0]->getHeader('Authorization'));

        $client->getInvoicesId(12);
        $this->assertNull($this->received[1]->getHeader('Authorization'), 'No credential is configured');
    }

    public function testAnOperationWithAnEmptySecurityRequirementSendsNoCredential()
    {
        $client = $this->generateClient();

        $this->withToken('secret', fn () => $client->listProducts());

        $this->assertNull($this->received[0]->getHeader('Authorization'));
    }

    public function testAQueryRequestIsSentInTheUrlAndAListIsHydrated()
    {
        $client = $this->generateClient();

        $request = new ($this->generatedClass('Http\\Requests\\ListProductsRequest'))();
        $request->labelContains = 'pe';

        $products = $client->listProducts($request);

        $this->assertInstanceOf($this->generatedClass('Refs\\Output\\Product'), $products[0]);
        $this->assertEquals(['label-contains' => 'pe'], $this->received[0]->get());
    }

    public function testPathParametersAreEncodedIntoThePath()
    {
        $client = $this->generateClient();

        $invoice = $client->getInvoicesId(12);

        $this->assertEquals('F-1', $invoice->number);
        $this->assertEquals('/invoices/12', $this->received[0]->getPath());
    }
}
