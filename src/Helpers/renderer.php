<?php 

namespace Cube;

use Cube\Web\Html\Renderer;

function render(string $viewName, array $context=[]) {
    return Renderer::getInstance()->render($viewName, $context);
}