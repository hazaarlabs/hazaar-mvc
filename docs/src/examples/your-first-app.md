# Your First Application

This is a simple example of how to create a basic Hazaar application.

## Create a new project

First, create a new project using Composer:

```bash
composer create-project hazaar/hazaar myapp
```

This will create a new directory called `myapp` with a basic Hazaar application.

## Create a controller

Next, create a new controller in the `controllers` directory.  For example, create a new file called `HelloController.php` with the following content:

```php
<?php

namespace Application\Controllers;

use Hazaar\Controller\Rest;

class HelloController extends Rest {

    public function get() {
        return 'Hello World!';
    }

}
```

## Create a route

Next, create a new route in the `routes.php` file.  For example, add the following line to the end of the file:

```php
Router::add('/hello', 'HelloController');
```

## Run the application

Finally, run the application using the built-in PHP web server:

```bash
cd myapp
php -S localhost:8000
```

You can now access the application in your web browser at [http://localhost:8000/hello](http://localhost:8000/hello).

## Next steps

This is a very basic example of a Hazaar application.  You can now start adding more controllers, routes, and views to build a more complex application.  For more information, see the [Hazaar documentation](https://hazaar.io/docs).
