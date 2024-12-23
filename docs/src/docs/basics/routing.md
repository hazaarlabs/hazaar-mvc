# Overview of Routing

Hazaar offers a robust and flexible routing system that enables seamless mapping of URLs to controllers and actions. This feature facilitates the creation of clean, intuitive URLs for your application.

Unlike other frameworks, Hazaar provides multiple methods for configuring routes, ensuring flexibility and ease of use. The router can be configured using the `application.json` file, with options to define routes in a PHP file, JSON file, or using attributes in controller classes. Hazaar also provides multiple automatic routing options, including basic and advanced routing methods.

By providing a variety of routing options, Hazaar allows developers to choose the most suitable method based on their application requirements and preferences.

## Router Modules

- **[File](#file-routes)**: Define routes in a `route.php` file, ideal for routes not directly tied to a controller.
- **[Basic](#basic-routes)**: The simplest method, requiring no configuration. Controllers and their actions are automatically available as routes.
- **[Advanced](#advanced-routes)**: Similar to the basic method but supports nesting controllers in subdirectories.
- **[Attribute](#attribute-routes)**: Use attributes in controller classes to define routes, also extending the advanced router.
- **[Annotated](#annotated-routes)**: Use annotations in controller classes to define routes, extending the advanced router.
- **[JSON](#json-routes)**: Define routes in a JSON file, useful for dynamic route definitions from external configurations.

::: warning
While the **annotated** router is still available and fully functional, it's use is discouraged in favor of the attribute router that provides a more efficient and structured way to define routes using PHP 8 attributes.  Annotated routes use PHPDoc comments which require additional parsing and are less efficient.
:::

## Configuring the Router

The router configuration is specified in the `application.json` file under the `router` section:

```json
{
    "router": {
        "type": "basic",
        "controller": "main",
        "action": "index"
    }
}
```

The `type` key determines the router type. Available types include:

- `file`: Define routes in a `route.php` file.
- `basic`: Automatically maps controllers to URLs with no configuration.
- `advanced`: Supports nesting controllers in subdirectories.
- `attribute`: Define routes using attributes in controller classes.
- `annotated`: Define routes using annotations in controller classes.
- `json`: Define routes in a JSON configuration file.

The `controller` and `action` keys specify the default controller and action to use when no route is matched.  Or if the controller is matched but no action is specified then the method defined in the `action` key will be executed.

## Callables

A callable is a PHP callback that can be used to execute a function or method. A callable can be a string (class name), an array (class and method with optional arguments), or a closure. 

The target controller and method must return a [`Hazaar\Controller\Response`](/api/class/Hazaar/Controller/Response) based object, such as [`Hazaar\Controller\Response\Json`](/api/class/Hazaar/Controller/Response/Json) or [`Hazaar\Controller\Response\View`](/api/class/Hazaar/Controller/Response/View).

### Class Method

A class method callable is defined as an array containing the class name and method name:

```php
[ControllerClass::class, 'actionMethod']
```

::: warning
The target controller method must be non-`static` and `public` and not start with an underscore (`_`).
:::

### Class Method with Arguments

A class method callable with arguments is defined as an array containing the class name, method name, and arguments:

```php
[ControllerClass::class, 'actionMethod', 'arg1', 'arg2']
```

This callable executes the `actionMethod` method in the `ControllerClass` class with `arg1` and `arg2` as arguments.  This is useful for passing additional data to the controller method or supplying default values that can be overridden by the URL.

### Closure

A closure callable is defined as an anonymous function:

```php
function() {
    return new Hazaar\Controller\Response\Json(['message' => 'Hello, World!']);
}
```

### Class Strings

A class string callable is defined as a string containing the class name:

```php
'ControllerClass'
```

This callable executes the default method in the `ControllerClass` class.

```php
'ControllerClass::actionMethod'
```

This callable executes the `actionMethod` method in the `ControllerClass` class.

```php
'ControllerClass::actionMethod(arg1, arg2)'
```

## Response Types

The response type is determined automatically by the return value of the controller method and is useful for ensuring that the error or exception response is returned in the correct format.

::: danger
Avoid using `mixed` return types as the router will not be able to determine the actual response type and will default to `HTML`.
:::

Available response types include:

- `Hazaar\Controller\Response::TYPE_HTML`: HTML response.  This is the default response type if no other type can be determined.
- `Hazaar\Controller\Response::TYPE_JSON`: JSON response.
- `Hazaar\Controller\Response::TYPE_XML`: XML response.
- `Hazaar\Controller\Response::TYPE_TEXT`: Plain text response.
- `Hazaar\Controller\Response::TYPE_BINARY`: Binary response.  Used if the response is a file download or other binary data such as a PDF.

## Route Parameters

Define route parameters in the URL pattern using curly braces `{}`. The parameter type can be specified using a colon (`:`) followed by the type.  Such as `{int:id}` or `{string:name}`.  This is not supported by the basic or advanced routers as they do not support custom routes.

Available types include:

- `int`: Integer.
- `float`: Floating-point number.
- `string`: String.
- `bool`: Boolean.

Values are matched based on the type specified. For example, `{int:id}` will only match integer values. If the value does not match the specified type, the route will not be matched.  The value is then passed to the controller method as an argument of the converted type using the name specified in the URL.

::: important
Values are passed as arguments to the controller method by name.  So a route of `/product/{int:id}` would pass the `id` value to the controller method's `$id` argument.

```php
public function getProduct(int $id) {
    // Retrieve the product with the specified ID
}
```
:::

Example:

::: code-tabs

@tab File

```php
Router::get('/product/{int:id}', [API::class, 'getProduct']);
```

@tab JSON

```json
[
    {
        "route": "/product/{int:id}",
        "controller": "Application\\Controller\\Product",
        "action": "getProduct",
        "method": "GET"
    }
]
```

@tab Attribute

```php
#[Route('/product/{int:id}', methods: ['GET'])]
public function getProduct(int $id) {
    // Retrieve the product with the specified ID
}
```

@tab Annotated

```php
/**
 * @route("/product/{int:id}", methods={"GET"})
 */
public function getProduct(int $id) {
    // Retrieve the product with the specified ID
}
```

:::

### Default Values

Default values can be specified for route parameters by providing a default value in the callable.  This is useful for providing default values when the parameter is not provided in the URL.  The array of default values is passed as the third argument to the callable and can 
be either a numeric array or an associative array.

If the default values are numeric, they are passed to the controller method in the order they are defined.  If the default values are associative, they are passed to the controller method by name.

#### Numeric Array

Numeric arrays are passed to the controller method in the order they are defined.

```php
Router::get('/product/{int:id}', [API::class, 'getProduct', [1234]]);
```

#### Associative Array

Associative arrays are passed to the controller method by name.

```php
Router::get('/product/{int:id}', [API::class, 'getProduct', ['id' => 1234]]);
```

## Router Modules

Developers can choose from multiple routing methods based on their application requirements and preferences. The router configuration is specified in the `application.json` file under the `router` section and determines the routing method to use.  

The available routing methods are described here.

### File Routes

File routes allow you to define routes in a PHP file, providing a flexible and dynamic way to configure routes. This method allows you to define routes that are not directly tied to a controller.  It also allows programmatic route definition, such as loading routes from a database or other external source.

File routes are specified in a `routes.php` file in the root directory. This file contains calls to the [`Hazaar\Application\Router`](/api/class/Hazaar/Application/Router) class, which provides methods such as:

- `get`: Responds to `GET` requests.
- `post`: Responds to `POST` requests.
- `put`: Responds to `PUT` requests.
- `delete`: Responds to `DELETE` requests.

Each method accepts two arguments, with an optional third for the response type:

1. `$route`: The URL pattern.
2. `$callable`: The callable to execute when the route is matched.
3. `$responseType` (optional): Specifies the expected response type.

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

Here, the root URL (`/`) loads the `Index` controller and executes its `index` method. The `/about` URL returns a view directly from a closure, and the `/api/v1/product` URL loads the `API` controller and executes its `listProducts` method if a `GET` request is received.  If a `POST` request is received, the `createProduct` method is executed.

#### Loading File Routes

To load the file routes from a file other than the default `route.php` file, add the `file` key to the `router` configuration in the `application.json` file:

```json
{
    "router": {
        "type": "file",
        "file": "myCustomRouteFile.php"
    }
}
```

The `file` key specifies the path to the PHP file containing the route definitions.

#### Loading Routes Programmatically

Routes can also be loaded programmatically using the `Router` class. This is useful for dynamic route definitions from external sources such as a database.

Example:

```php
use Hazaar\Application\Router;
use Hazaar\DBI\Adapter;

$routes = Adapter::getInstance()->table('routes')->find();

foreach($routes as $route) {
    Router::get($route['url'], [$route['controller'], $route['action']]);
}
```
::: tip
When loading routes programmatically from a database or other external source, routes are loaded during the bootstrapping process and can impact performance. Consider caching routes to improve performance.

Alternatively for the the best performance, it is possible to use PHP in **worker mode** to pre-load the routes during the bootstrap process.  This can provide the best performance as the routes are pre-loaded and do not need to be reloaded on each request.  FrankenPHP provides this functionality out of the box and is 100% supported by Hazaar.  

See the [FrankenPHP documentation](https://frankenphp.dev/docs/worker/) for more information.
:::

### Basic Routes

The basic router uses the request URL to determine the controller and action, along with any arguments. No configuration is required, as controllers and actions are automatically mapped to URLs.

This router is the simplest and fastest way to perform routing in Hazaar as there is minimal processing required.  However, it is limited in it's ability to handle complex routing scenarios such as nested controllers, custom routes, or route aliases.

::: tip
The basic router is ideal for simple applications with a flat controller structure that require the most efficient routing possible.
:::

A typical route format is:

```
/controller/action/arg1/arg2/argx
```

Example:

```
/product/get/1234
```

This URL loads the `Application\Controller\Product` controller and executes its `get` method with `1234` as an argument. The corresponding controller in `application/controllers/Product.php`:

```php
namespace Application\Controller;

use Hazaar\Controller\Basic;
use Hazaar\Controller\Response\Json;
use Hazaar\DBI\Adapter;

class Product extends Basic {

    public function init(): void
    {
        // Initialization code
    }

    public function get(int $productId): Json
    {
        $db = Adapter::getInstance();
        return new Json($db->table('product')->find(['id' => $productId]));
    }
}
```

### Advanced Routes

The advanced router supports nested controllers in subdirectories, enabling a more organized structure. Controllers are mapped to URLs based on their directory structure.

::: tip
The advanced router is ideal for applications with a complex controller hierarchy, have a large number of controllers, require a more organized structure, or require the use of route aliases.
:::

A typical route format is:

```
/subdir/subdir/controller/action/arg1/arg2/argx
```

Here, `controller` is the name of the controller class stored in the `application/controllers/subdir/subdir/controller.php` file, and `action` is the public method on the controller that will receive the request.

::: info
The advanced router has a smart controller loader that automatically loads the controller based on the URL by finding the most
appropriate controller in the directory structure and can be multiple levels deep.
:::

::: note
The advanced router does not support custom routes. For more advanced routing, consider the [attribute router](#attribute-routes) or [file router](#file-routes).
:::

#### Aliases

The advanced router supports aliases, allowing you to define custom URLs for controllers. Aliases are defined in the `aliases` section of the `application.json` file:

```json
{
    "router": {
        "type": "advanced",
        "aliases": {
            "product": "product/get"
        }
    }
}
```

Here, the `/product` URL is aliased to `/product/get`.  Once the URL has been translated to it's aliased form, the advanced router will then attempt to load the controller and execute the action as normal.

::: tip
Aliases can be used to inject additional arguments into the URL, such as the alias `/example` which can point to `/product/get/1234`.
The advanced router will then load the `Product` controller and execute the `get` method with `1234` as an argument.
:::

### Attribute Routes

The attribute router uses attributes in controller classes to define routes. This method extends the advanced router, providing a more structured and organized approach to routing.

#### Defining Routes

To define a route, add the `#[\Hazaar\Application\Route]` attribute to the controller class or method to define the actions available on this controller.  Routing to the 
controller itself is handled by the [Advanced Router](#advanced-routes) to limit the number of controller files processed.

The attribute accepts the following parameters:

- `$route`: The URL pattern.
- `$method` (optional): The HTTP method (GET, POST, PUT, DELETE).
- `$responseType` (optional): The expected response type.

Example:

```php
namespace Application\Controller\Api\V1;

use Hazaar\Application\Route;

class Product extends Basic {

    #[Route('/list', methods: ['GET'])]
    public function listProducts(): Json
    {
        // List products
    }

    #[Route('/{int:id}', methods: ['GET'])]
    public function getProduct(int $id): Json
    {
        // Retrieve the product with the specified ID
    }

}
```

Here, the `listProducts` method is accessible via a `GET` request to `/api/v1/product/list`, and the `getProduct` method is accessible via a `GET` request to `/api/v1/product/{id}`.

#### Route Parameters

Define route parameters in the URL pattern using curly braces. 

Example:

```php
namespace Application\Controller;

use Hazaar\Application\Route;
use Hazaar\Controller\Basic;
use Hazaar\Controller\Response\Json;
use Hazaar\DBI\Adapter;

class Product extends Basic {

    #[Route('/', methods: ['GET'])]
    public function listProducts(): Json
    {
        // List products
    }

    #[Route('/{int:id}', methods: ['GET'])]
    public function getProduct(int $id): Json
    {
        $db = Adapter::getInstance();
        return new Json($db->table('product')->find(['id' => $productId]));
    }

    #[Route('/{int:id}', methods: ['POST'])]
    public function updateProduct() {
        // Update product
    }

}
```

In this example we have defined 3 routes on the `Application\Controller\Product` controller.  They are:

- `GET` request to `/product`.  This can be used to retrive a list of products.
- `GET` request to `/product/1234`.  This can be used to retrieve a single product with the ID `1234`.
- `POST` request to `/product/1234`.  This can be used to update a single product with the ID `1234`.

### JSON Routes

The JSON router allows you to define routes in a JSON file, providing a flexible and dynamic way to configure routes. This method is useful for applications requiring dynamic route definitions from external configurations.

#### Configuration

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

#### Loading Routes

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

### Annotated Routes 

::: danger
The annotated router is deprecated and will be removed in a future release. Use the [attribute router](#attribute-routes) instead.
:::

The annotated router uses annotations in controller classes to define routes. This method extends the advanced router, providing a more structured and organized approach to routing.


#### Defining Routes

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

#### Route Parameters

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

