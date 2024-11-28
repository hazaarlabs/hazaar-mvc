<?php

use Application\Controllers\Api;
use Application\Controllers\Test;
use Hazaar\Application\Router;

// @var Router $route
Router::get('/test/{word}', [Test::class, 'bar']);
Router::get('/test', [Test::class, 'foo']);
Router::get('/test/{id}', [Api::class, 'testGET']);
Router::get('/foo/{word}', [Test::class, 'bar']);
