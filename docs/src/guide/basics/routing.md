# Overview of Routing

Hazaar MVC offers a robust and flexible routing system that enables seamless mapping of URLs to controllers and actions. This feature facilitates the creation of clean, intuitive URLs for your application.

Unlike other frameworks, Hazaar MVC provides multiple methods for configuring routes, ensuring flexibility and ease of use.

## Methods for Defining Routes

- **[File](#file-routes)**: Define routes in a file, ideal for routes not directly tied to a controller.
- **[Basic](#basic-routes)**: The simplest method, requiring no configuration. Controllers and their actions are automatically available as routes.
- **[Advanced](#advanced-routes)**: Similar to the basic method but supports nesting controllers in subdirectories.
- **[Annotated](#annotated-routes)**: Use annotations in controller classes to define routes, extending the advanced router.
- **[Attribute](#attribute-routes)**: Use attributes in controller classes to define routes, also extending the advanced router.
- **[JSON](#json-routes)**: Define routes in a JSON file, useful for dynamic route definitions from external configurations.

## Configuring the Router

The router configuration is specified in the `application.json` file under the `router` section:

```json
{
    "router": {
        "type": "basic"
    }
}
```

The `type` key determines the router type. Available types include:

- `file`: Define routes in a `route.php` file.
- `basic`: Automatically maps controllers to URLs with no configuration.
- `advanced`: Supports nesting controllers in subdirectories.
- `annotated`: Define routes using annotations in controller classes.
- `attribute`: Define routes using attributes in controller classes.

## File Routes

File routes are specified in a `routes.php` file in the root directory. This file contains calls to the `Router` class, which provides methods such as:

- `get`: Responds to `GET` requests.
- `post`: Responds to `POST` requests.
- `put`: Responds to `PUT` requests.
- `delete`: Responds to `DELETE` requests.

Each method accepts two arguments, with an optional third for the response type:

1. `$route`: The URL pattern.
2. `$callable`: The callable to execute when the route is matched.
3. `$responseType` (optional): Specifies the expected response type.

A callable can be a string (class name), an array (class and method), or a closure. The target controller and method must return a `Hazaar\Controller\Response` object.

Example `routes.php` file:

```php
<?php

use Hazaar\Application\Router;
use Hazaar\Controller\Response\View;
use Application\Controller\Index;
use Application\Controller\API;

Router::get('/', [Index::class, 'index']);
Router::get('/about', function() {
    return View('about');
});
Router::get('/api/v1/product', [API::class, 'listProducts']);
Router::post('/api/v1/product', [API::class, 'createProduct']);
```

### Route Parameters

Define route parameters in the URL pattern using curly braces. The parameter type can be specified using a colon (`:`) followed by the type. Available types include:

- `int`: Integer.
- `float`: Floating-point number.
- `string`: String.
- `path`: Path (including slashes).

Example:

```php
Router::get('/product/{int:id}', [API::class, 'getProduct']);
```

The `getProduct` method in the `API` controller:

```php
public function getProduct(int $id) {
    // Retrieve the product with the specified ID
}
```

### Route Response Types

Specifying a response type ensures errors or exceptions are returned in the correct format. Available response types include:

- `Hazaar\Controller\Response::TYPE_HTML`: HTML response.
- `Hazaar\Controller\Response::TYPE_JSON`: JSON response.
- `Hazaar\Controller\Response::TYPE_XML`: XML response.
- `Hazaar\Controller\Response::TYPE_TEXT`: Plain text response.

Example:

```php
Router::get('/product/{int:id}', [API::class, 'getProduct'], Response::TYPE_JSON);
```

### Loading File Routes

To load the file routes, add the following configuration to the `application.json` file:

```json
{
    "router": {
        "type": "file",
        "file": "routes.php"
    }
}
```

The `file` key specifies the path to the PHP file containing the route definitions.

## Basic Routes

The basic router uses the request URL to determine the controller and action, along with any arguments. No configuration is required, as controllers and actions are automatically mapped to URLs.

::: tip
The basic router is ideal for simple applications with a flat controller structure.
:::

::: note
The basic router does not support nested controllers or custom routes.  For more advanced routing, consider the [advanced router](#advanced-routes).
:::

A typical route format is:

```
/controller/action/arg1/arg2/argx
```

Example:

```
/auth/login/myusername/mypassword
```

This URL loads the `AuthController` and executes its `login` method with `myusername` and `mypassword` as arguments. The corresponding controller in `application/controllers/Auth.php`:

```php
namespace Application\Controller;

class Auth extends \Hazaar\Controller\Action {

    public function init() {
        // Initialization code
    }

    public function login($username, $password) {
        // Handle login
    }
}
```

::: warning
The target controller method must be public and not start with an underscore (`_`).
:::

## Advanced Routes

The advanced router supports nested controllers in subdirectories, enabling a more organized structure. Controllers are mapped to URLs based on their directory structure.

::: tip
The advanced router is ideal for applications with a complex controller hierarchy.
:::

A typical route format is:

```
/subdir/subdir/controller/action/arg1/arg2/argx
```

::: info
The advanced router has a smart controller loader that automatically loads the controller based on the URL by finding the most
appropriate controller in the directory structure and can be multiple levels deep.
:::

::: note
The advanced router does not support custom routes. For more advanced routing, consider the [annotated router](#annotated-routes),  [attribute router](#attribute-routes) or [File router](#file-routes).
:::

Example:

```
/api/v1/product/list
```

This URL loads `Application\Controller\Product` and executes its `list` method. The corresponding controller is located in `application/controllers/api/v1/product.php`:

```php  
namespace Application\Controller\Api\V1;

use Hazaar\Controller\Action;

class Product extends Action {

    public function list() {
        // List products
    }

}
```

## Attribute Routes

The attribute router uses attributes in controller classes to define routes. This method extends the advanced router, providing a more structured and organized approach to routing.

### Defining Routes

To define a route, add the `#[\Hazaar\Application\Route]` attribute to the controller class or method. The attribute accepts the following parameters:

- `$route`: The URL pattern.
- `$method` (optional): The HTTP method (GET, POST, PUT, DELETE).
- `$responseType` (optional): The expected response type.

Example:

```php
namespace Application\Controller;

use Hazaar\Controller\Action;
use Hazaar\Application\Route;

class Product extends Action {

    #[Route('/list', methods: ['GET'])]
    public function list() {
        // List products
    }

}
```

### Route Parameters

Define route parameters in the URL pattern using curly braces. 

Example:

```php
#[Route('/{int:id}', methods: ['GET'])]
public function getProduct(int $id) {
    // Retrieve the product with the specified ID
}
```

## JSON Routes

The JSON router allows you to define routes in a JSON file, providing a flexible and dynamic way to configure routes. This method is useful for applications requiring dynamic route definitions from external configurations.

### Configuration

Define routes in a `routes.json` file in the application config directory. The file contains an array of route objects, each specifying the following properties:

- `route`: The URL pattern.
- `controller`: The controller class name.
- `action`: The controller method name.
- `method` (optional): The HTTP method (GET, POST, PUT, DELETE).
- `responseType` (optional): The expected response type.

Example `routes.json` file:

```json
[
    {
        "route": "/api/v1/product/list",
        "controller": "Application\\Controller\\Product",
        "action": "list",
        "method": "GET"
    },
    {
        "route": "/api/v1/product/{int:id}",
        "controller": "Application\\Controller\\Product",
        "action": "getProduct",
        "method": "GET"
    }
]
```

### Loading Routes

To load the JSON routes, add the following configuration to the `application.json` file:

```json
{
    "router": {
        "type": "json",
        "file": "routes.json"
    }
}
```

The `file` key specifies the path to the JSON file containing the route definitions.

::: tip
The JSON router supports route parameters and response types, similar to the [file router](#file-routes).
:::

## Annotated Routes 

::: danger
The annotated router is deprecated and will be removed in a future release. Use the [attribute router](#attribute-routes) instead.
:::

The annotated router uses annotations in controller classes to define routes. This method extends the advanced router, providing a more structured and organized approach to routing.


### Defining Routes

To define a route, add the `@route` annotation to the controller class or method. The annotation accepts the following parameters:

- `$route`: The URL pattern.
- `$method` (optional): The HTTP method (GET, POST, PUT, DELETE).

Example:

```php
namespace Application\Controller;

use Hazaar\Controller\Action;

/**
 * @route("/api/v1/product/list", methods={"GET"})
 */
class Product extends Action {

    public function list() {
        // List products
    }

}
```

### Route Parameters

Define route parameters in the URL pattern using curly braces.

Example:

```php
/**
 * @route("/api/v1/product/{int:id}", methods={"GET"})
 */
public function getProduct(int $id) {
    // Retrieve the product with the specified ID
}
```

