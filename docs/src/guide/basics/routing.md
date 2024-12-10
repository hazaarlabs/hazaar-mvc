# Overview of Routing

Hazaar MVC offers a robust and flexible routing system that enables seamless mapping of URLs to controllers and actions. This feature facilitates the creation of clean, intuitive URLs for your application.

Unlike other frameworks, Hazaar MVC provides multiple methods for configuring routes, ensuring flexibility and ease of use.

## Methods for Defining Routes

- **[File](#file-routes)**: Define routes in a `route.php` file, ideal for routes not directly tied to a controller.
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
- `json`: Define routes in a JSON configuration file.

## File Routes

File routes are specified in a `routes.php` file in the root directory. This file contains calls to the `Hazaar\Application\Router` class, which provides methods such as:

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

## Basic Routes

The basic router uses the request URL to determine the controller and action, along with any arguments. No configuration is required, as controllers and actions are automatically mapped to URLs.
This router is the simplest and fastest way to perform routing in Hazaar MVC as there is minimal processing required.

::: tip
The basic router is ideal for simple applications with a flat controller structure that require the most efficient routing possible.
:::

::: warning
The basic router does not support nested controllers or routing aliases.  For more advanced routing, look at the [advanced router](#advanced-routes).
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

::: warning
The target controller method must be non-`static` and `public` and not start with an underscore (`_`).
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

Here, `controller` is the name of the controller class stored in the `application/controllers/subdir/subdir/controller.php` file, and `action` is the public method on the controller that will receive the request.

::: info
The advanced router has a smart controller loader that automatically loads the controller based on the URL by finding the most
appropriate controller in the directory structure and can be multiple levels deep.
:::

::: note
The advanced router does not support custom routes. For more advanced routing, consider the [annotated router](#annotated-routes),  [attribute router](#attribute-routes) or [File router](#file-routes).
:::

### Checking the Request Method

If the controller method should only process a particular type of request, then the [`Hazaar\Application\Request\HTTP`](/api/class/Hazaar/Application/Request/HTTP) object needs to be used to check the 
current request type and provides a number of ways to do this.  

The request object is accessible on the controller using `$this->request` and provides methods such as:

- isGet() - Returns true if the request uses the `GET` method.
- isPost() - Returns true if the request uses the `POST` method.
- getMethod() - Returns the name of the request method for this request.  

::: tip
`Hazaar\Application\Request::getMethod()` is available in CLI requests as well, but will always be `GET`.
:::

```php
public function action(): Response
{
    if($this->request->isPOST()){
        // Store the update and return status
    }elseif($this->request->isGET()){
        // Return the product
    }
    
    return BadRequest;
}
```

### Example

In this example our controller has a `get` method that returns a product formatted as JSON.  The URL would be:

```
/api/v1/product/get/1234
```

This URL loads `Application\Controller\Product` and executes its `list` method. The corresponding controller is located in `application/controllers/api/v1/product.php`:

```php
namespace Application\Controller\Api\V1;

use Hazaar\Controller\Basic;
use Hazaar\Controller\Response;
use Hazaar\Controller\Response\Json;
use Hazaar\Controller\Response\HTTP\BadRequest;
use Hazaar\DBI\Adapter;

class Product extends Basic {

    public function init(): void
    {
        // Initialization code
    }

    public function get(int $productId): Response
    {
        if(!$this->request->isGET()){
            return new BadRequest;
        }
        $db = Adapter::getInstance();

        return new Json($db->table('product')->find(['id' => $productId]));
    }
}
```

## Attribute Routes

The attribute router uses attributes in controller classes to define routes. This method extends the advanced router, providing a more structured and organized approach to routing.

### Defining Routes

To define a route, add the `#[\Hazaar\Application\Route]` attribute to the controller class or method to define the actions available on this controller.  Routing to the 
controller itself is handled by the [Advanced Router](#advanced-routes) to limit the number of controller files processed.

The attribute accepts the following parameters:

- `$route`: The URL pattern.
- `$method` (optional): The HTTP method (GET, POST, PUT, DELETE).
- `$responseType` (optional): The expected response type.

Example:

```php
#[Route('/', methods: ['GET'])]
public function listProducts(): Json
{
    // List products
}
```

### Route Parameters

Define route parameters in the URL pattern using curly braces. 

Example:

```php
#[Route('/{int:id}', methods: ['GET'])]
public function getProduct(int $id): Json
{
    // Retrieve the product with the specified ID
}
```

### Full Example

In this example we have defined 3 routes on the `Application\Controller\Product` controller.  They are:

- `GET` request to `/product`.  This can be used to retrive a list of products.
- `GET` request to `/product/1234`.  This can be used to retrieve a single product with the ID `1234`.
- `POST` request to `/product/1234`.  This can be used to update a single product with the ID `1234`.

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

