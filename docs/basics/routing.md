# Overview of Routing

HazaarMVC's concept of routing is fairly simple to understand. HazaarMVC uses the request URL to figure out which controller is being requested. Further to that, it will determine if there is an action also being requested as well as any arguments.

A standard request URL will look something like this:

```
http://www.yourhost.com/controller/action/arg1/arg2/argx
```

This will load the controller, and execute the action along with the supplied arguments.

```
http://www.yourhost.com/auth/login/myusername/mypassword
```

Calling the above URL will load the AuthController controller and execute it's login method, passing it two arguments ofmyusername and mypassword.
You would receive this call with the following controller in application/controllers/Auth.php:

```php
class AuthController extends \Hazaar\Controller\Action {
  
    public function init(){
        //Required.  Do some init stuff here.
    }
  
    //
    // This is the method that will be executed
    //
    public function login($username, $password){
        //Do something with your username and password.
    }
  
}
```

HazaarMVC has the ability to alter the application execution path without modifying any code or URLs. For more information see Routing Aliases.