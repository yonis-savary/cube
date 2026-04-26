<?php

namespace Cube\Data\OpenAPI;

use Cube\Core\Component;
use Cube\Data\OpenAPI\Specs\OASInfo;
use Cube\Data\OpenAPI\Specs\OASRoot;
use Cube\Utils\Path;
use Cube\Web\Router\Router;

class OpenAPIGenerator
{
    use Component;

    public function __construct(
        protected OpenAPIConfiguration $configuration
    ){}

    /**
     * @return string Path of generated JSON file
     */
    public function generate(?Router $router=null): string {
        $router ??= Router::getInstance();
        $config = &$this->configuration;

        $context = new OpenAPIGenerationContext($config);
        OpenAPIGenerationContext::setInstance($context);

        $infos = new OASInfo($config->title, $config->version);
        $root = new OASRoot('3.1.0', $infos);
        $root->processPaths($router);
        $root->generateModelSchemas();


        $outputFile = $config->outputFile;

        $context->log(
            str_repeat("-", 40),
            "    Generating file " . Path::toRelative($outputFile),
            str_repeat("-", 40),
        );
        file_put_contents($outputFile, $root->toJSON($config->jsonFlags));
        return $outputFile;
    }
}