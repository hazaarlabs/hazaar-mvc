# Routing Aliases

For an overview on how routing works in a HazaarMVC application, see the section on Routing.

Aliases are a way of making the URL point to a controller other than the controller with the same name (which is the default behavior). That is, you could get the URL /index to point to a controller called *MyController* instead.

Aliases DO NOT redirect! The URL will not be tampered with and it is only the internal route the code takes to find, load and execute a controller method that is altered.

There are two types of aliases supported depending on the level of complexity required to fulfill the request.

## Static Aliases

Static aliases are the simplest form of aliases. They are simply configuration directives that generates an associative array internally that is used to find a requested alias.

Take the following example. In the application.ini file you may define a single alias such as:

```
{
    "development": {
        "app": {
            "alias": {
                "textalias": "index/test/foo/bar"
            }
        }
    }
}
```

This will cause the URL

```
http://www.yourhost.com/testalias
```

to be interpreted as

```
http://www.yourhost.com/index/test/foo/bar
```

Static aliases are useful for hard-coding URL aliases for reasons such as:

* Abbreviation - You would like to make a long complex URL into something shorthand and easier to remember/access.
* Duplication - You would like a controller to be accessible by multiple names. eg: logon, login, authorise, etc.

## Dynamic Aliases

Dynamic aliases are a little more complex and require some coding in your application to get them to work (which is the whole point). Using dynamic aliases allows you to write your own code to interpret a request URL and generate a result by any means you wish. Dynamic aliases are evaluated AFTER the application has been bootstraped which allows you to use any database access methods that you are using in your application.

The complexity involved in using dynamic aliases is entirely up to you as the application developer.

### Getting Started

The code that is executed to generate routing information is stored in your application root directory in a file called *route.php*. This file is executed inside a Hazaar\Application\Router context which provides two methods.

* get() - Returns the current route.
* set() - Sets the current route.

Routes can be set in the form of controller/action/arg1/arg2/argx where all fields separated by '/' are optional, including the controller. If the controller is omitted then the router assumes no dynamic routing is taking place and it will carry on as usual.

#### Example route.php to load routes from a MongoDB database

```php
$db = new Hazaar\Db\MongoDB(array('database' => 'system'));
$route = $db->route->findOne(array('_id' => $this->get()));
if($route){
    $this->set($route['target']);
}
```

This will use the current route to look up a document in the system database and return the value in the target element to use as the route.