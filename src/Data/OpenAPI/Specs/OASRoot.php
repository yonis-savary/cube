<?php

namespace Cube\Data\OpenAPI\Specs;

use Cube\Data\AutoDataToObject as DataAutoDataToObject;
use Cube\Data\Bunch;
use Cube\Data\Models\Model;
use Cube\Data\Models\Relations\HasMany;
use Cube\Data\OpenAPI\OpenAPIConfiguration;
use Cube\Data\OpenAPI\OpenAPIGenerationContext;
use Cube\Data\OpenAPI\Specs\Common\MakesSchemas;
use Cube\Web\Router\Router;

class OASRoot extends DataAutoDataToObject
{
    use MakesSchemas;

    public function __construct(
        public string $openapi,
        public OASInfo $info,
        public array $paths = [],
        public array $components = [],
        public array $security = []
    ){}

    public function objectKeys(): array
    {
        return ['paths', 'components'];
    }

    public function skipOnEmpty(): array
    {
        return ['components', 'security'];
    }

    public function processPaths(Router $router): void
    {
        $this->paths = (new OASPaths($router))->toArray();
    }

    public function generateModelSchemas(): void
    {
        $context = OpenAPIGenerationContext::getInstance();

        foreach ($context->sharedSchemas as $name => $schema)
            $this->components['schemas'][$name] = $schema;

        $generatedModels = [];
        while ($model = Bunch::wrap($context->usedModelRefs)->diff($generatedModels)->first()) {
            $generatedModels[] = $model;
            $this->generateModelSchema($model);
        }
    }

    /**
     * @param class-string<Model> $model
     */
    private function generateModelSchema(string $model): void
    {
        $basename = preg_replace("~.+\\\\~", "", $model);
        $this->components['schemas'][$basename] = [];
        $schema = &$this->components['schemas'][$basename];
        $this->mutateParameterWithRule($model::toResponseObjectParam(), $schema);

        $instance = new $model();
        foreach ($model::relations() as $name) {
            $relation = $instance->{$name}();
            $ref = ['$ref' => $this->getRefForClass($relation->toModel)];

            $schema['properties'][$name] = $relation instanceof HasMany
                ? ['type' => 'array', 'items' => $ref]
                : ['oneOf' => [$ref, ['type' => 'null']]];
        }

        $schema['example'] = $model::factory()->withSeed(crc32($model))->make()->toArray();
    }

    public function generateSecurityScheme(OpenAPIConfiguration $configuration) {
        if (!$scheme = $configuration->authenticationScheme)
            return;

        $name = $scheme->getComponentName();
        $arrayScheme = $scheme->toArray();

        $this->components['securitySchemes'] ??= [];
        $this->components['securitySchemes'][$name] = $arrayScheme;

        $this->security[] = [$name => []];
    }
}