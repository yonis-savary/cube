<?php

namespace Cube\Data\OpenAPI\Specs;

use Cube\Data\AutoDataToObject as DataAutoDataToObject;
use Cube\Data\Bunch;
use Cube\Data\OpenAPI\OpenAPIGenerationContext;
use Cube\Data\OpenAPI\Specs\Common\MakesSchemas;
use Cube\Data\OpenAPI\Specs\Common\ModelRef;
use Cube\Web\Router\Router;

class OASRoot extends DataAutoDataToObject
{
    use MakesSchemas;
    use ModelRef;

    public function __construct(
        public string $openapi,
        public OASInfo $info,
        public array $paths = [],
        public array $components = []
    ){}

    public function objectKeys(): array
    {
        return ['paths', 'components'];
    }

    public function skipOnEmpty(): array
    {
        return ['paths', 'components'];
    }

    public function processPaths(Router $router): void
    {
        $this->paths = (new OASPaths($router))->toArray();
    }

    public function generateModelSchemas(): void
    {
        $context = OpenAPIGenerationContext::getInstance();

        if (!count($context->usedModelRefs))
            return;

        $this->components['schemas'] ??= [];

        $modelsToAdd = Bunch::of($context->usedModelRefs)->uniques()->toArray();
        foreach ($modelsToAdd as $model) {
            $basename = preg_replace("~.+\\\\~", "", $model);
            $this->components['schemas'][$basename] = [];
            $schema = &$this->components['schemas'][$basename];
            $this->mutateParameterWithRule($model::toObjectParam(), $schema);
        }
    }
}