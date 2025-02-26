<?php

namespace Cube;

/**
 * @return float Time in microseconds
 */
function measureTimeOf(callable $callback): float
{
    $start = hrtime(true);

    ($callback)();

    return (hrtime(true) - $start) / 1000;
}
