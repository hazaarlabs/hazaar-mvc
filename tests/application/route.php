<?php

use Application\Controllers\Test;
use Hazaar\Application\Router\Custom as Router;

/** @var Router $route */
$route->get('/test/{word}', [Test::class, 'bar']);
$route->get('/test', [Test::class, 'foo']);