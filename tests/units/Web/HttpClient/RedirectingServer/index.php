<?php

$port = $_SERVER['SERVER_PORT'];

$redirections = [
    '/created' => [201, '/elsewhere'],
    '/loop' => [302, '/loop'],
    '/file' => [302, 'file:///etc/passwd'],
    '/to-same-host' => [302, '/headers'],
    '/to-other-host' => [302, "http://127.0.0.1:{$port}/headers"],
    '/with-query' => [302, '/query?page=2'],
];

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

if ($redirection = $redirections[$path] ?? null) {
    http_response_code($redirection[0]);
    header('Location: '.$redirection[1]);
    echo "answered {$path}";
    return;
}

echo match ($path) {
    '/headers' => json_encode(array_change_key_case(getallheaders())),
    '/query' => 'page '.($_GET['page'] ?? 'missing'),
    default => "answered {$path}",
};
