<?php

return [
    'production' => [
        'app' => [
            'name' => 'PHPUnit - Test Application',
            'version' => '1.0.0',
            'locale' => 'en_AU.UTF-8',
            'layout' => 'application',
            'theme' => [
                'name' => 'test',
            ],
            'debug' => false,
            'defaultController' => 'Index',
            'runtimePath' => '/var/hazaar',
        ],
        'paths' => [
            'model' => 'models',
            'view' => 'views',
            'controller' => 'controllers',
        ],
        'middleware' => [
            'global' => [
                'App\Middleware\Example',
            ],
            'aliases' => [
                'auth' => 'App\Middleware\Auth',
            ],
        ],
        'cache' => [
            'backend' => 'file',
        ],
        'php' => [
            'date' => [
                'timezone' => 'UTC',
            ],
            'display_startup_errors' => 0,
            'display_errors' => 0,
        ],
        'view' => [
            'helper' => [
                'load' => [
                    'bootstrap' => [
                        'theme' => 'cyborg',
                    ],
                ],
            ],
        ],
        'import' => [
            'imports.json',
        ],
    ],
    'development' => [
        'include' => 'production',
        'app' => [
            'debug' => true,
        ],
        'php' => [
            'display_startup_errors' => 1,
            'display_errors' => 1,
        ],
    ],
    'gitlab' => [
        'include' => 'production',
    ],
];
