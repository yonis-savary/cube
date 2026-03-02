<?php

namespace Cube\Data\OpenAPI\Specs\Common;

use Cube\Data\Models\Model;
use Cube\Data\OpenAPI\OpenAPIGenerationContext;

trait ModelRef
{
    /**
     * @param class-string<Model> $modelClass
     */
    public function getRefForClass(string $modelClass)
    {
        OpenAPIGenerationContext::getInstance()->usedModelRefs[] = $modelClass;
        $basename = preg_replace("~.+\\\\~", "", $modelClass);
        return "#/components/schemas/$basename";
    }
}