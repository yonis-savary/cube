<?php

use Cube\Event\Events\FrameworkLoaded;

/*
 * Loaded through composer's `autoload.files` : `FrameworkLoaded` is dispatched before the
 * applications `Requires/` files are included, so only code running before
 * `Autoloader::initialize()` can hear it.
 */
FrameworkLoaded::on(function () {
    $GLOBALS['heard'][FrameworkLoaded::class] = true;
});
