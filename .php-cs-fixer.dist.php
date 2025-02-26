<?php

$finder = (new PhpCsFixer\Finder())
    ->in([
        __DIR__ . "/src",
        __DIR__ . "/tests",
    ])
;

return (new PhpCsFixer\Config())
    ->setParallelConfig(new PhpCsFixer\Runner\Parallel\ParallelConfig(4, 20))
    ->setRules([
        '@PhpCsFixer' => true
    ])
    ->setFinder($finder)
;