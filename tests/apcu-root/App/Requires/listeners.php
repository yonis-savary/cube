<?php

use Cube\Event\Events\ApplicationsLoaded;

ApplicationsLoaded::on(function (ApplicationsLoaded $event) {
    $GLOBALS['heard'][ApplicationsLoaded::class] = $event->loaded;
});
