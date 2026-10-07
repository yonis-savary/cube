<?php

use Cube\Event\Events\ApplicationsLoaded;
use Cube\Event\Events\PreRouting;

ApplicationsLoaded::on(function (ApplicationsLoaded $event) {
    $GLOBALS['heard'][ApplicationsLoaded::class] = $event->loaded;
});

PreRouting::on(function () {
    $GLOBALS['heard'][PreRouting::class] = true;
});
