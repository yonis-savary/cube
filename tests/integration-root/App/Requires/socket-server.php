<?php

use Cube\Web\Servers\Events\SocketServerSetup;

SocketServerSetup::on(
    fn (SocketServerSetup $event) => $event->router->group('/internal', requires: ['App/Socket/routes.php'])
);
