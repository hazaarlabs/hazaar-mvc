# Middleware

## Introduction

Middleware in Hazaar Framework provides a flexible mechanism to process HTTP requests and responses at various stages of your application's lifecycle. Middleware components allow you to intercept, modify, or act upon requests before they reach your controllers, and responses before they are sent to the client. This enables features such as authentication, logging, request modification, and more, in a clean and reusable way.

::: note
 By default, Hazaar Framework automatically loads all middleware classes found in the `/work/app/middleware` directory during the application bootstrap process. You do not need to manually register middleware from this directory; simply place your middleware PHP files there and they will be included in the middleware stack in alphabetical order.
:::

## How Middleware Works

Middleware components implement the `Hazaar\Middleware\Interface\Middleware` interface, which requires a `handle` method. Each middleware receives the current `Request` object and a `$next` callable. The `$next` callable represents the next middleware in the stack or the final controller action.

When a request is handled, the middleware stack is executed in the order it was registered. Each middleware can:

- Inspect or modify the incoming request.
- Call `$next($request)` to pass control to the next middleware or controller.
- Inspect or modify the response returned by the next middleware/controller.
- Short-circuit the stack by returning a response directly.

Middleware is registered with the `MiddlewareDispatcher`, which manages the stack and execution order.

## Example 1: Modifying a Request

This example demonstrates a middleware that adds a custom header to the request before passing it to the next handler.

```php
// filepath: /work/app/middleware/AddHeader.php
<?php

namespace App\Middleware;

use Hazaar\Application\Request;
use Hazaar\Controller\Response;
use Hazaar\Middleware\Interface\Middleware;

class AddHeader implements Middleware
{
    public function handle(Request $request, callable $next): Response
    {
        // Add a custom header to the request
        $request->setHeader('X-Custom-Header', 'Added by middleware');

        // Continue to the next middleware/controller
        return $next($request);
    }
}
```

## Example 2: Logging After Response

This example shows a middleware that logs request and response information to a database after the response has been generated, using `Hazaar\DBI\Adapter` to insert into a `log` table.

```php
// filepath: /work/app/middleware/LogToDatabase.php
<?php

namespace App\Middleware;

use Hazaar\Application\Request;
use Hazaar\Controller\Response;
use Hazaar\Middleware\Interface\Middleware;
use Hazaar\DBI\Adapter;

class LogToDatabase implements Middleware
{
    public function handle(Request $request, callable $next): Response
    {
        // Call the next middleware/controller and get the response
        $response = $next($request);

        // Log request and response details to the database using Hazaar\DBI\Adapter
        $db = new Adapter();
        $db->table('log')->insert([
            'path' => $request->getPath(),
            'status' => $response->getStatusCode(),
            'timestamp' => time(),
        ]);

        return $response;
    }
}
```

## Example 3: Early Return (Short-Circuiting)

This example demonstrates a middleware that checks for a required header and returns a Bad Request response if the header is missing, preventing further middleware or controller execution.

```php
// filepath: /work/app/middleware/RequireHeader.php
<?php

namespace App\Middleware;

use Hazaar\Application\Request;
use Hazaar\Controller\Response;
use Hazaar\Controller\Response\HTTP\BadRequest;
use Hazaar\Middleware\Interface\Middleware;

class RequireHeader implements Middleware
{
    public function handle(Request $request, callable $next): Response
    {
        // Check for a required header
        if (!$request->getHeader('X-Required-Header')) {
            // Return a Bad Request response and stop further processing
            return new BadRequest('Missing required header: X-Required-Header');
        }

        // Continue to the next middleware/controller
        return $next($request);
    }
}
```

## Registering Middleware

To use middleware, you can also register it manually with the `MiddlewareDispatcher` if needed:

```php
$dispatcher = new \Hazaar\Middleware\MiddlewareDispatcher();
$dispatcher->add(new \App\Middleware\AddHeader());
$dispatcher->add(new \App\Middleware\LogToDatabase());
$dispatcher->add(new \App\Middleware\RequireHeader());
```

Or load all middleware from a directory (not usually necessary, as this is handled automatically):

```php
$dispatcher->loadMiddleware('/work/app/middleware');
```

## Summary

Middleware provides a powerful way to encapsulate cross-cutting concerns in your Hazaar Framework application. By chaining middleware, you can keep your controllers clean and focused on business logic, while handling concerns like authentication, logging, and request/response manipulation in reusable components.
