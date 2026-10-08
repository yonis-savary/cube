<?php

$server = stream_socket_server("unix://{$argv[1]}");
$connection = stream_socket_accept($server, 5);

$requestLine = trim(fgets($connection));
while (trim(fgets($connection)) !== '');

fwrite($connection, "HTTP/1.1 200 OK\r\nContent-Length: ".strlen($requestLine)."\r\nConnection: close\r\n\r\n{$requestLine}");

fclose($connection);
fclose($server);
unlink($argv[1]);
