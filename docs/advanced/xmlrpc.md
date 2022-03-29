# XML-RPC

The XML-RPC classes are there to make it incredibly easy to make RPC calls, or to serve them from your application. There are two parts to this functionality. The client and the server.

## Client

Using a client is very simple. All you need to do it this:

```php
$xml = new Hazaar\Xml\Rpc\Client('http://www.example.com/xmlrpc');
$response = $xml->doTest();
```

The $response variable will then hold the response from the method that was just called on the remote server. In the case of the below server code our $response variable will hold:

```php
array {
    'result' => 'OK',
    'string' => 'Hello, World!'
}
```

## Server

Servers are almost as easy to get up and running, but you just need to know the tricks. Servers are set up just as any other controller. Meaning you extend the Hazaar\Controller\XMLRPC class to create your XML-RPC server controller. Any public methods of that controller class will then be automatically registered as XML-RPC methods that can be called on your server.

```php
class XMLRPCController extends Hazaar\Controller\XMLRPC {
    public function doTest(){
        return [
            'result' => 'OK',
            'string' => 'Hello, World!'
        ];
    }
    public function getMethodList(){
        return $this->registered_methods;
    }
}
```

This will register two methods, `doTest` and `getMethodList`. It's that easy. If the client example above was used to call the `doTest` method we have just defined, the array with result and string will be returned.

The Hazaar\Controller\XMLRPC class is an alias class to the Hazaar\Xml\Rpc\Server class that has been added to maintain a logical connection between the server and the controller. Using either class as the parent of the controller will work however.