<?php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../playwell/app.php';

$app = new \Playwell\App();

$app->route([
    ['GET', '/users', 'foo.php'],
    ['ANY', '/user/{id:\d+}', 'foo.php'],
]);

return $app;
