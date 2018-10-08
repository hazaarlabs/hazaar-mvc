# Configuration

## Application Directives

These directives are used throughout the application and affect it's functionality only.

### app.name

The short name of the application.

```
app.name = "Test Application"
```

### app.version

The current version of the application. This is returned when calling Hazaar\\Application::getVersion().

```
app.version = 1.0.1
```

### app.view

The default view to use for the Hazaar\\Controller\\Action controller. This controller uses this global view which can set the overall style and layout of the application. A call to $this->getContent() from inside this view will then populate the page with the output from the controller.

```
app.view = "application"
```

### app.defaultController

This is the controller that is loaded if one is not specified in the URL. Usually 'Index'.

```
app.defaultController = "Index"
```

### app.errorController

This is the controller that is used if an error occurs. Usually 'error'. See Error for more information.

```
app.errorController = "Error"
```

### app.compress

If this is true, the application will compress any output that it produces. This includes stylesheets and javascript files.

```
pp.compress = false
```

### app.timer

Enables the built-in application execution timer which is available from inside a controller at

```
$this->application->timer
```

```
app.timer = true
```

### app.maxload

If this directive exists it will activate load average protection. When the application executes it will check the 1 minute load average and if it is greater than this number Hazaar will return a 503 Too Busy HTTP response.

```
app.maxload = 3.00
```

### app.*

It's also possible to have custom options, apart from those above. These values can be retrieved from anywhere in the application by referencing the \Hazaar\Application\Config object which is accessible at $this->application->config from inside a controller. Alternatively, if you need to access to the config object outside of a controller you can get an instance of the application with Hazaar\Application::getInstance() and then refer to the config object there.

```
app.theme = "classic"
```

This would then be accessible from a controller or view by calling:

```php
$theme = $this->application->config->app['theme'];
```

## Paths Directives

Paths are for setting where various components exist in the application directory. Normally these will never change but have been added for complete flexibility.

### paths.model

The path, relative to the root/application path where models are kept. Usually 'models'.

```
paths.model = "models"
```

### paths.view

The path, relative to the root/application path where models are kept. Usually 'views'.

```
paths.view = "views"
```

### paths.controller

The path, relative to the root/application path where models are kept. Usually 'controllers'.

```
paths.controller = "controllers"
```

## PHP Settings Directivesh3. php.*

These config options can be used to configure INI settings in PHP itself.

```
php.display_startup_errors = 0
php.display_errors = 0
```

## Session Directives

These directives affect the functionality of the PHP session object. These aren't absolutely needed and have adequate default values for most situations.

```
session.name
```

The session name to use for PHP. This is what is set in the cookie on the client side. Defaults to the PHP session name of PHPSESSID.

```
session.name = "MYSESSION"
```

### session.namespace

The default session namespace. This namespace is used if one is not specified in the session object. Defaults to 'default'.

```
session.namespace = "default"
```

### session.timeout

Specifies the number of seconds of idle time that should pass before a session will expire. Defaults to 180.

```
session.timeout = 180
```

## Includes

You can include another configuration if needed.

```
include = production
```

This is useful for if you have a development server or other stripped down server. You can set all your options up in the [production] configuration, include that config, and then only add the settings that you want to override. For example, you may have debugging off by default, so you could include the production config and then just set debug = true.

## View Directives

```
view.helper.load
```

This directive can be used to load one or more view helpers automatically, meaning that the View::addHelper() method call does not need to be called from your within you controllers. This is handy for view helpers that need to be active all the time, such as with the Bootstrap or Google analytics view helpers.

```
view.helper.load[] = Bootstrap
view.helper.load[] = Google
```