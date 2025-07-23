# Middleware

## Introduction

Middleware in Hazaar Framework provides a flexible mechanism to process HTTP requests and responses at various stages of your application's lifecycle. Middleware components allow you to intercept, modify, or act upon requests before they reach your controllers, and responses before they are sent to the client. This enables features such as authentication, logging, request modification, and more, in a clean and reusable way.

::: note
 All middleware classes must be placed in the `/work/app/middleware` directory. Middleware is not loaded automatically; instead, global middleware is explicitly defined in your application configuration under the `middleware.global` property. You may use class names or aliases, which are mapped in the `middleware.aliases` property.
:::

## Types of Middleware

Hazaar supports three types of middleware:

* *Global Middleware* - Only middleware listed in the `middleware.global` array in your config is loaded and executed for every request. The array can contain class names or aliases. Aliases are defined in the `middleware.aliases` property and map to class names.
* *Route Middleware* - Route middleware is attached to specific routes and only executed for requests matching those routes. This is useful for applying authentication, authorization, or other logic to selected endpoints.
* *Controller Middleware* - Middleware can also be registered directly within a controller's constructor. This allows you to apply middleware to specific controller actions, with fine-grained control using `only` and `except` methods.

::: tip
Global middleware runs for every request. Route middleware runs only for requests matching the route. Controller middleware runs for specific actions within a controller.
:::

## How Middleware Works

Middleware components implement the `Hazaar\Middleware\Interface\Middleware` interface, which requires a `handle` method. Each middleware receives the current `Request` object and a `$next` callable. The `$next` callable represents the next middleware in the stack or the final controller action.

When a request is handled, the middleware stack is executed in the order it was registered. Each middleware can:

- Inspect or modify the incoming request.
- Call `$next($request)` to pass control to the next middleware or controller.
- Inspect or modify the response returned by the next middleware/controller.
- Short-circuit the stack by returning a response directly.

Middleware is registered with the `MiddlewareDispatcher`, which manages the stack and execution order.

## Global Middleware

Global middleware must be defined in your application configuration, for example:

```json
{
  "middleware": {
    "global": [
      "App\\Middleware\\AddHeader",
      "auth" // alias example
    ],
    "aliases": {
      "auth": "App\\Middleware\\RequireAuth"
    }
  }
}
```

Only the middleware listed in the `global` array will be loaded and executed for every request. Aliases allow you to use short names in your config.

### Example 1: Modifying a Request

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

### Example 2: Logging After Response

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

### Example 3: Early Return (Short-Circuiting)

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

### Registering Middleware

To use middleware, you can also register it manually with the `MiddlewareDispatcher` if needed:

```php
$dispatcher = new \Hazaar\Middleware\MiddlewareDispatcher();
$dispatcher->add(new \App\Middleware\AddHeader());
$dispatcher->add(new \App\Middleware\LogToDatabase());
$dispatcher->add(new \App\Middleware\RequireHeader());
```

## Route Middleware

Route middleware allows you to attach middleware to specific routes, rather than applying it globally to all requests. This is useful for applying authentication, authorization, or other logic only to certain endpoints.

Route middleware is registered on a route using the `middleware()` chaining method call. The middleware will only be executed for requests matching that route. You can use either class names or aliases (defined in your config under `middleware.aliases`).

#### Example: File Route Middleware

```php
Router::get('/admin', [AdminController::class, 'dashboard'])
    ->middleware('auth'); // 'auth' is an alias defined in config
```

#### Example: JSON Route Middleware

```json
[
    {
        "route": "/admin",
        "controller": "Application\\Controller\\Admin",
        "action": "dashboard",
        "method": "GET",
        "middleware": "auth"
    }
]
```

Aliases are mapped to middleware class names in your configuration:

```json
{
  "middleware": {
    "aliases": {
      "auth": "App\\Middleware\\RequireAuth"
    }
  }
}
```

Route middleware is executed after global middleware and before the controller action. You can specify a middleware class name or alias per route. Each middleware should implement the `Hazaar\Middleware\Interface\Middleware` interface.

::: tip
Use route middleware for logic that only applies to specific endpoints, such as authentication, permission checks, or request validation. Aliases help keep your route definitions clean and maintainable.
:::

## Route Middleware Summary

Middleware provides a powerful way to encapsulate cross-cutting concerns in your Hazaar Framework application. By chaining middleware, you can keep your controllers clean and focused on business logic, while handling concerns like authentication, logging, and request/response manipulation in reusable components.
Route middleware is executed after global middleware and before the controller action. You can specify one or more middleware classes or aliases per route. Each middleware should implement the `Hazaar\Middleware\Interface\Middleware` interface.

::: tip
Use route middleware for logic that only applies to specific endpoints, such as authentication, permission checks, or request validation. Aliases help keep your route definitions clean and maintainable.
:::

## Controller Middleware

Controller middleware allows you to attach middleware directly to controller actions by registering them in the controller's constructor. This provides fine-grained control over which actions the middleware applies to, using the `only` and `except` methods.

To register middleware in a controller, use the `middleware()` method inside the constructor. You can specify the middleware class name or alias. The `only()` and `except()` methods allow you to restrict the middleware to specific actions.

### Example: Registering Middleware in a Controller

```php
// filepath: /work/app/controllers/Main.php
<?php

namespace App\Controller;

use App\Middleware\Auth;
use App\Middleware\Throttle;
use Hazaar\Controller\Action;

class Main extends Action
{
    public function __construct()
    {
        // Apply the Auth middleware only to the 'index' action
        $this->middleware(Auth::class)->only('index');

        // Apply the Throttle middleware to all actions except 'profile'
        $this->middleware(Throttle::class)->except('profile');

        $this->cacheAction('index', 60); // Cache the index action for 60 seconds
    }

    public function index(): void
    {
        $this->view('index');
    }

    public function profile(): void
    {
        $this->view('profile');
    }
}
```

In this example:
- `Auth` middleware is applied only to the `index` action using `only('index')`.
- `Throttle` middleware is applied to all actions except `profile` using `except('profile')`.

You can chain multiple middleware registrations and use `only()` or `except()` to control their scope. This approach keeps your middleware logic close to the controller actions they affect, making your codebase easier to understand and maintain.

### Summary Table

| Method                | Description                                                        |
|-----------------------|--------------------------------------------------------------------|
| `middleware($class)`  | Registers middleware for the controller.                           |
| `only($actions)`      | Restricts middleware to specified actions (string or array).       |
| `except($actions)`    | Excludes middleware from specified actions (string or array).      |

## Summary

Middleware provides a powerful way to encapsulate cross-cutting concerns in your Hazaar Framework application. By chaining middleware, you can keep your controllers clean and focused on business logic, while handling concerns like authentication, logging, and request/response manipulation in reusable components.
