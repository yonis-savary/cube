<?php 

namespace Cube;

use Cube\Web\Html\AssetsInserter;
use Cube\Web\Html\Renderer;

function render(string $viewName, array $context=[]) {
    return Renderer::getInstance()->render($viewName, $context);
}

function asset(string $assetName) {
    return AssetsInserter::getInstance()->insert($assetName);
}

function e(mixed $value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}