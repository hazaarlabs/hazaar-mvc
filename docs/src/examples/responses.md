# Controller Responses

This page has a few basic examples of how to return responses from a controller action.  These examples are using the `Router` class to define routes, but the same principles apply to any controller action.

## Returning a string

The simplest way to return a response from a controller action is to return a string.  This will be sent to the client as a plain text response.

```php
Router::get('/', function(){
    return 'Hello World!';
});
```

## Returning a JSON response

If you want to return a JSON response, you can return an array or object from the controller action.  This will be automatically converted to a JSON response.

```php
Router::get('/json', function(){
    return ['message' => 'Hello World!'];
});
```

Alternatively, you can return a [`Hazaar\Controller\Response\JSON`](/api/class/Hazaar/Controller/Response/JSON.md) object.

```php
use Hazaar\Controller\Response\Json;
Router::get('/json', function(){
    return new Json(['message' => 'Hello World!']);
});
```

## Returning a file

If you want to return a file, you can return a [`Hazaar\Controller\Response\File`](/api/class/Hazaar/Controller/Response/File.md) object.

```php
use Hazaar\Controller\Response\File;
Router::get('/file', function(){
    return new File('path/to/file.txt');
});
```

## Returning an image file

If you want to return an image file, you can return a [`Hazaar\Controller\Response\Image`](/api/class/Hazaar/Controller/Response/Image.md) object.

```php
use Hazaar\Controller\Response\Image;
Router::get('/image', function(){
    return new Image('path/to/image.jpg');
});
```

# Built-in HTTP Responses

Hazaar has a number of built-in response classes that you can use to return different types of HTTP responses.  More of these will be added in the future, but for now there are a few basic ones.

The following built-in `Hazaar\Controller\Response\HTTP` classes are available:

* [OK](/api/class/Hazaar/Controller/Response/HTTP/Ok.md) - `200 OK`
* [NoContent](/api/class/Hazaar/Controller/Response/HTTP/NoContent.md) - `204 No Content`
* [Redirect](/api/class/Hazaar/Controller/Response/HTTP/Redirect.md) - `301 Moved Permanently`
* [BadRequest](/api/class/Hazaar/Controller/Response/HTTP/BadRequest.md) - `400 Bad Request`
* [Unauthorized](/api/class/Hazaar/Controller/Response/HTTP/Unauthorized.md) - `401 Unauthorized`
* [Forbidden](/api/class/Hazaar/Controller/Response/HTTP/Forbidden.md) - `403 Forbidden`
* [NotFound](/api/class/Hazaar/Controller/Response/HTTP/NotFound.md) - `404 Not Found`
* [RateLimitExceeded](/api/class/Hazaar/Controller/Response/HTTP/RateLimitExceeded.md) - `429 Too Many Requests`

## Redirecting to another URL

If you want to redirect the client to another URL, you can return a `Response` object with the redirect status code set.

```php
use Hazaar\Controller\Response\HTTP\Redirect;
Router::get('/redirect', function(){
    return new Redirect('https://hazaar.io');
});
```
