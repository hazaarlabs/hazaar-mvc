# The Request Object

The [`Hazaar\Application\Request`](/api/class/Hazaar/Application/Request) object provides information about the current request, including the request method, URL, and parameters. The request object is accessible on the controller using `$this->request`.

The request object provides a lot of useful information about the request.  For more information see the [Request API](/api/class/Hazaar/Application/Request).

## Request Methods

The request object provides the following methods to determine the request method:

- [isGet()](/api/class/Hazaar/Application/Request/HTTP#isget) - Returns true if the request uses the `GET` method.
- [isPut()](/api/class/Hazaar/Application/Request/HTTP#isput) - Returns true if the request uses the `PUT` method.
- [isPost()](/api/class/Hazaar/Application/Request/HTTP#ispost) - Returns true if the request uses the `POST` method.
- [isDelete()](/api/class/Hazaar/Application/Request/HTTP#isdelete) - Returns true if the request uses the `DELETE` method.
- [getMethod()](/api/class/Hazaar/Application/Request#getmethod) - Returns the name of the request method for this request.  

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

## Remote IP Address

The request object provides the following methods to determine the remote IP address:

- [`getRemoteAddress()`](/api/class/Hazaar/Application/Request#getremoteaddress) - Returns the remote IP address.
- [`getRemoteHost()`](/api/class/Hazaar/Application/Request#getremotehost) - Returns the remote host name.

```php
public function action(): Response
{
    $ip = $this->request->getRemoteAddress();
    $host = $this->request->getRemoteHost();
    
    return new Json(['ip' => $ip, 'host' => $host]);
}
```

The `getRemoteAddress` method returns the remote IP address of the client making the request, while the `getRemoteHost` method returns the host name of the client.

If a `X-Forwarded-For` header is present in the request, the `getRemoteAddress` method will return the first IP address in the list.  The `getRemoteHost` method will return the host name of the first IP address in the list.
