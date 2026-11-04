<?php

namespace Cube\Tests\Units\Data\DataToObject\Generator;

use Cube\Data\DataToObject\Generator\Sources\OpenApiSource;
use Cube\Utils\Path;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

/**
 * @internal
 */
class OpenApiSourceTest extends TestCase
{
    use GeneratesClasses;

    protected function billingDocument(): array
    {
        return json_decode(file_get_contents(Path::join(__DIR__, 'Fixtures', 'openapi-billing.json')), true, flags: JSON_THROW_ON_ERROR);
    }

    protected function documentWithSchema(array $schema): array
    {
        return ['openapi' => '3.1.0', 'components' => ['schemas' => ['Contact' => $schema]]];
    }

    public function testSchemasAreGeneratedInTheDirectionTheyAreUsed()
    {
        $sources = $this->render((new OpenApiSource($this->billingDocument()))->toClasses());

        $this->assertEqualsCanonicalizing([
            'Refs\\Output\\Category', 'Refs\\Output\\Product', 'Refs\\Output\\Timestamped', 'Refs\\Output\\Invoice', 'Refs\\Output\\InvoiceLinesItem',
            'Refs\\Input\\Category',
            'Http\\Requests\\ListProductsRequest', 'Http\\Requests\\CreateProductRequest', 'Http\\Responses\\CreateProductResponse', 'Http\\Responses\\GetInvoicesIdResponse',
        ], array_keys($sources));

        $this->assertStringContainsString('class Category extends DataToObject', $sources['Refs\\Output\\Category']);
        $this->assertStringContainsString('class Category extends AutoDataToObject', $sources['Refs\\Input\\Category']);
    }

    public function testRequiredAndNullableDecideTheParameterType()
    {
        $sources = $this->render((new OpenApiSource($this->billingDocument()))->toClasses());

        $this->assertStringContainsString('public int $id,', $sources['Refs\\Output\\Product']);
        $this->assertStringContainsString('public Category $category,', $sources['Refs\\Output\\Product']);
        $this->assertStringContainsString('public ?array $tags = null,', $sources['Refs\\Output\\Product']);
        $this->assertStringContainsString('public ?Category $parent = null,', $sources['Refs\\Output\\Category']);
        $this->assertStringContainsString('public ?string $paidAt = null,', $sources['Refs\\Output\\Invoice']);

        $contact = $this->render((new OpenApiSource($this->documentWithSchema([
            'type' => 'object',
            'required' => ['email', 'phone'],
            'properties' => [
                'email' => ['type' => ['string', 'null']],
                'phone' => ['anyOf' => [['type' => 'string'], ['type' => 'null']]],
            ],
        ])))->toClasses())['Refs\\Output\\Contact'];

        $this->assertStringContainsString('public ?string $email,', $contact);
        $this->assertStringContainsString('public ?string $phone,', $contact);
    }

    public function testOptionalParametersComeAfterTheRequiredOnes()
    {
        $source = $this->render((new OpenApiSource($this->billingDocument()))->toClasses())['Refs\\Output\\Invoice'];

        $this->assertLessThan(strpos($source, '$paidAt'), strpos($source, '$lines'));
    }

    public function testLenientMakesEveryPropertyOptionalAndNullable()
    {
        $sources = $this->render((new OpenApiSource($this->billingDocument()))->toClasses(), lenient: true);

        $this->assertStringContainsString('public ?int $id = null,', $sources['Refs\\Output\\Product']);
        $this->assertStringContainsString('public ?Category $category = null,', $sources['Refs\\Output\\Product']);
    }

    public function testSchemasThatAreNotObjectsAreInlined()
    {
        $sources = $this->render((new OpenApiSource($this->billingDocument()))->toClasses());

        $this->assertArrayNotHasKey('Refs\\Output\\Status', $sources);
        $this->assertStringContainsString('public string $status,', $sources['Refs\\Output\\Invoice']);
        $this->assertStringContainsString("@param 'draft'|'paid' \$status", $sources['Refs\\Output\\Invoice']);
        $this->assertStringContainsString('@param array<string,string>|null $attributes', $sources['Refs\\Output\\Product']);
        $this->assertStringContainsString('public mixed $payer = null,', $sources['Refs\\Output\\Invoice']);
    }

    public function testAllOfMergesThePropertiesOfEachPart()
    {
        $source = $this->render((new OpenApiSource($this->billingDocument()))->toClasses())['Refs\\Output\\Invoice'];

        $this->assertStringContainsString('public string $createdAt,', $source);
        $this->assertStringContainsString('public string $number,', $source);
    }

    public function testReadOnlyPropertiesAreNeverSentAndWriteOnlyOnesNeverReceived()
    {
        $sources = $this->render((new OpenApiSource($this->billingDocument()))->toClasses());

        $this->assertStringNotContainsString('internalNote', $sources['Refs\\Output\\Product']);
        $this->assertStringNotContainsString('reference', $sources['Http\\Requests\\CreateProductRequest']);
    }

    public function testARequestClassOnlyRequiresItsRequiredProperties()
    {
        $source = $this->render((new OpenApiSource($this->billingDocument()))->toClasses())['Http\\Requests\\CreateProductRequest'];

        $this->assertStringContainsString('class CreateProductRequest extends AutoDataToObject', $source);
        $this->assertStringContainsString("public function __construct(\n        public string \$label,\n    ) {}", $source);
        $this->assertStringContainsString('    public float $unitPrice;', $source);
        $this->assertStringContainsString("'unitPrice' => 'unit-price',", $source);
    }

    public function testQueryParametersOfAnOperationWithoutBodyMakeItsRequestClass()
    {
        $source = $this->render((new OpenApiSource($this->billingDocument()))->toClasses())['Http\\Requests\\ListProductsRequest'];

        $this->assertStringContainsString('    public int $page;', $source);
        $this->assertStringContainsString("'labelContains' => 'label-contains',", $source);
    }

    public function testAResponseReferringToASchemaExtendsItsClass()
    {
        $sources = $this->render((new OpenApiSource($this->billingDocument()))->toClasses());

        $this->assertStringContainsString('class CreateProductResponse extends Product', $sources['Http\\Responses\\CreateProductResponse']);
        $this->assertStringContainsString('use '.$this->generatedClass('Refs\\Output\\Product').';', $sources['Http\\Responses\\CreateProductResponse']);
        $this->assertArrayNotHasKey('Http\\Responses\\ListProductsResponse', $sources, 'A list response has no class of its own');
    }

    public function testSelectedEndpointsOnlyGenerateTheSchemasTheyUse()
    {
        $classes = (new OpenApiSource($this->billingDocument(), ['createProduct']))->toClasses();

        $this->assertEqualsCanonicalizing(
            ['Refs\\Output\\Category', 'Refs\\Output\\Product', 'Refs\\Input\\Category', 'Http\\Requests\\CreateProductRequest', 'Http\\Responses\\CreateProductResponse'],
            array_keys($this->render($classes)),
        );
    }

    public function testEndpointsAreMatchedByOperationIdOrMethodAndPath()
    {
        $source = new OpenApiSource($this->billingDocument(), ['createProduct', 'get /invoices/{id}', 'DELETE /products']);

        $this->assertEquals(['DELETE /products'], $source->unmatchedEndpoints());
    }

    public function testAClassThatExtendsAnotherWithTheSameNameUsesItsFullName()
    {
        $document = $this->billingDocument();
        $document['components']['schemas']['CreateProductRequest'] = $document['paths']['/products']['post']['requestBody']['content']['application/json']['schema'];
        $document['paths']['/products']['post']['requestBody']['content']['application/json']['schema'] = ['$ref' => '#/components/schemas/CreateProductRequest'];

        $source = $this->render((new OpenApiSource($document))->toClasses())['Http\\Requests\\CreateProductRequest'];

        $this->assertStringContainsString('class CreateProductRequest extends \\'.$this->generatedClass('Refs\\Input\\CreateProductRequest'), $source);
    }

    public function testKeysGivingTheSamePropertyNameAreNumbered()
    {
        $contact = $this->render((new OpenApiSource($this->documentWithSchema([
            'type' => 'object',
            'properties' => ['first_name' => ['type' => 'string'], 'firstName' => ['type' => 'string'], '_first_name' => ['type' => 'string']],
        ])))->toClasses())['Refs\\Output\\Contact'];

        $this->assertStringContainsString('$firstName = null,', $contact);
        $this->assertStringContainsString('$firstName2 = null,', $contact);
        $this->assertStringContainsString('$_firstName = null,', $contact);
    }

    public function testGeneratedClassesHydrateAPayload()
    {
        $this->writeAndAutoload((new OpenApiSource($this->billingDocument()))->toClasses());

        $invoice = $this->generatedClass('Http\\Responses\\GetInvoicesIdResponse')::fromData([
            'created_at' => '2026-01-01T00:00:00Z',
            'number' => 'F-2026-001',
            'status' => 'paid',
            'lines' => [['product' => ['id' => 1, 'label' => 'Pen', 'category' => ['id' => 2, 'parent' => ['id' => 3]]], 'quantity' => 4]],
        ]);

        $this->assertInstanceOf($this->generatedClass('Refs\\Output\\Invoice'), $invoice);
        $this->assertEquals('2026-01-01T00:00:00Z', $invoice->createdAt);
        $this->assertEquals(3, $invoice->lines[0]->product->category->parent->id);
        $this->assertNull($invoice->paidAt);
    }

    public function testANullableObjectReceivedAsNullIsHydratedAsNull()
    {
        $this->writeAndAutoload((new OpenApiSource($this->billingDocument()))->toClasses());

        $category = $this->generatedClass('Refs\\Output\\Category')::fromData(['id' => 2, 'parent' => null]);

        $this->assertNull($category->parent);
    }

    public function testGeneratedRequestsSendTheKeysOfTheDocument()
    {
        $this->writeAndAutoload((new OpenApiSource($this->billingDocument()))->toClasses());

        $request = new ($this->generatedClass('Http\\Requests\\CreateProductRequest'))('Pen');
        $request->unitPrice = 1.5;
        $request->category = new ($this->generatedClass('Refs\\Input\\Category'))(2);

        $this->assertEquals(['label' => 'Pen', 'unit-price' => 1.5, 'category' => ['id' => 2]], $request->toArray());
    }

    public static function cubeDocuments(): array
    {
        $documents = [];
        foreach (glob(Path::join(__DIR__, '..', '..', 'OpenAPI', 'Fixtures', 'OAD*.json')) as $file)
            $documents[basename($file)] = [$file];

        return $documents;
    }

    #[DataProvider('cubeDocuments')]
    public function testDocumentsGeneratedByCubeGiveValidClasses(string $file)
    {
        $source = new OpenApiSource(json_decode(file_get_contents($file), true));
        $files = [...$this->writer()->write($source->toClasses()), ...$this->clientWriter()->write($source->toClient())];

        $this->assertContainsOnlyString($files);
        foreach ($files as $generated) {
            $lint = new Process([PHP_BINARY, '-l', $generated]);
            $lint->run();

            $this->assertTrue($lint->isSuccessful(), $lint->getOutput().file_get_contents($generated));
        }
    }

    public function testExternalReferencesAreRejected()
    {
        $this->expectExceptionMessage('Unsupported reference [common.json#/Address]');

        (new OpenApiSource($this->documentWithSchema([
            'type' => 'object',
            'properties' => ['address' => ['$ref' => 'common.json#/Address']],
        ])))->toClasses();
    }

    public function testARelativeServerUrlIsResolvedAgainstWhereTheDocumentWasFetched()
    {
        $document = ['openapi' => '3.0.4', 'servers' => [['url' => '/api/v3']], 'paths' => []];

        $this->assertEquals('https://petstore.example.com/api/v3', (new OpenApiSource($document, documentUrl: 'https://petstore.example.com/api/v3/openapi.json'))->toClient()->url);
        $this->assertNull((new OpenApiSource($document))->toClient()->url, 'A file gives no base to resolve against');

        $document['servers'] = [['url' => '../v4/']];
        $this->assertEquals('https://petstore.example.com/api/v4', (new OpenApiSource($document, documentUrl: 'https://petstore.example.com/api/v3/openapi.json'))->toClient()->url);
    }

    public function testEachOperationAcceptsItsOwnSecuritySchemes()
    {
        $operations = [];
        foreach ((new OpenApiSource($this->billingDocument()))->toClient()->operations as $operation)
            $operations[$operation->name] = $operation->security;

        $this->assertEquals(['BearerAuth'], $operations['CreateProduct'], 'The document requirement applies by default');
        $this->assertEquals([], $operations['ListProducts'], 'security: [] requires nothing');
    }

    public function testTheInlineObjectsOfAnEndpointGoToASubNamespaceOfItsName()
    {
        $response = [
            'type' => 'object',
            'properties' => [
                'owner' => ['type' => 'object', 'properties' => [
                    'login' => ['type' => 'string'],
                    'address' => ['type' => 'object', 'properties' => ['city' => ['type' => 'string']]],
                ]],
                'members' => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => ['login' => ['type' => 'string']]]],
            ],
        ];
        $document = ['openapi' => '3.1.0', 'paths' => ['/directory' => ['get' => [
            'operationId' => 'getDirectory',
            'responses' => ['200' => ['description' => '', 'content' => ['application/json' => ['schema' => $response]]]],
        ]]]];

        $sources = $this->render((new OpenApiSource($document))->toClasses());

        $this->assertEqualsCanonicalizing([
            'Http\\Responses\\GetDirectoryResponse',
            'Http\\Responses\\GetDirectoryResponse\\Owner',
            'Http\\Responses\\GetDirectoryResponse\\OwnerAddress',
            'Http\\Responses\\GetDirectoryResponse\\MembersItem',
        ], array_keys($sources));
        $this->assertStringContainsString('public ?Owner $owner = null,', $sources['Http\\Responses\\GetDirectoryResponse']);
    }
}
