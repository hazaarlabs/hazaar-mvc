<?php

use App\Controller\Test;
use Hazaar\Application\Router;

// @var Router $route
Router::get('/test/{word}', [Test::class, 'bar']);
Router::get('/test', [Test::class, 'foo']);
Router::get('/foo/{word}', [Test::class, 'bar']);
