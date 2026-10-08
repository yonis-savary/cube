<?php

namespace Cube\Tests\Units\Data\OpenAPI;

use Cube\Utils\Path;
use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class OpenAPISpecificationTest extends TestCase
{
    const OPENAPI_SCHEMA = 'https://spec.openapis.org/oas/3.1/schema/2022-10-07';

    const SCHEMA_FILES = [
        'https://spec.openapis.org/oas/3.1/schema/2022-10-07' => 'oas-3.1-schema.json',
        'https://spec.openapis.org/oas/3.1/dialect/base' => 'oas-3.1-dialect-base.json',
        'https://spec.openapis.org/oas/3.1/meta/base' => 'oas-3.1-meta-base.json',
        'https://json-schema.org/draft/2020-12/schema' => 'draft-2020-12.json',
        'https://json-schema.org/draft/2020-12/meta/core' => 'draft-2020-12-core.json',
        'https://json-schema.org/draft/2020-12/meta/applicator' => 'draft-2020-12-applicator.json',
        'https://json-schema.org/draft/2020-12/meta/unevaluated' => 'draft-2020-12-unevaluated.json',
        'https://json-schema.org/draft/2020-12/meta/validation' => 'draft-2020-12-validation.json',
        'https://json-schema.org/draft/2020-12/meta/meta-data' => 'draft-2020-12-meta-data.json',
        'https://json-schema.org/draft/2020-12/meta/format-annotation' => 'draft-2020-12-format-annotation.json',
        'https://json-schema.org/draft/2020-12/meta/content' => 'draft-2020-12-content.json',
    ];

    public static function generatedDocuments(): array
    {
        $documents = [];
        foreach (glob(Path::join(__DIR__, 'Fixtures', 'OAD*.json')) as $file)
            $documents[basename($file)] = [$file];

        return $documents;
    }

    protected function getValidator(): Validator
    {
        $validator = new Validator();
        // opis writes schema defaults into the data, which unevaluatedProperties then rejects
        $validator->parser()->setOption('allowDefaults', false);

        foreach (self::SCHEMA_FILES as $id => $file)
            $validator->resolver()->registerFile($id, Path::join(__DIR__, 'Fixtures', 'Schemas', $file));

        return $validator;
    }

    #[DataProvider('generatedDocuments')]
    public function testGeneratedDocumentFollowsTheSpecification(string $file)
    {
        $document = json_decode(file_get_contents($file), flags: JSON_THROW_ON_ERROR);

        $result = $this->getValidator()->validate($document, self::OPENAPI_SCHEMA);

        $errors = $result->isValid() ? [] : (new ErrorFormatter())->format($result->error());
        $this->assertTrue($result->isValid(), basename($file) . " is not a valid OpenAPI 3.1 document : " . json_encode($errors, JSON_PRETTY_PRINT));
    }
}
