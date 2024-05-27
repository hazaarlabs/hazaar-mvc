<?php

use Application\Controllers\Test;

$route->get('/test/{word}', [Test::class, 'bar']);
