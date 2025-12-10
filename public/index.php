<?php

declare(strict_types=1);

[$container, $router, $dispatch] = require dirname(__DIR__) . '/bootstrap.php';

(require dirname(__DIR__) . '/routes.php')($router, $container);

$dispatch();
