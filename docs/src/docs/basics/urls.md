# Generating URLs

::: danger
This page is tagged as a draft and is a work in progress.  It is not yet complete and may contain errors or inaccuracies.
:::

There are two methods for generating URLs within Hazaar. One is relative to the controller and the other is relative to the application. Both are methods of the corresponding object.

So from within your controller, to generate an application relative URL you would call:

```php
$this->application->url();
```

Or, for a URL relative to the controller:

```php
$this->url();
```

Parameters are dynamic and depend on what you are trying to generate. Usually two parameters are used with this method to generate a URL. The first being the controller and the section the controller action. If either is null then the method will figure out which to use based on where the method is being called from (either the controller or the application).

So as an example, given the application is on localhost and in the root path:

```php
// http://localhost/controller
"> http://localhost/controller
$this->url(); 
// http://localhost
"> http://localhost
$this->application->url();                              
// http://localhost/controller
"> http://localhost/controller
$this->application->url('controller');    
// http://localhost/controller              
$this->application->url('controller', null);
// http://localhost/index/action            
$this->application->url(null, 'action');                
// http://localhost/controller/action
$this->application->url('controller', 'action');       
// http://localhost/controller/action 
$this->application->url(['controller', 'action']);
// http://localhost/index/action
$this->application->url([null,action];
// http://localhost
"> http://localhost
$this->application->url(null, null);       
// http://localhost             
$this->application->url([null, null]);
```

## With Parameters

You can also specify GET parameters in a variety of ways.

```php
// http://localhost/?myParam=myValue
$application->url('?myParam=myValue');
// http://localhost/controller?myParam=myValue
$application->url('controller?myParam=myValue');                             
// http://localhost/controller/action?myParam=myValue
"> http://localhost/controller/action?myParam=myValue
$application->url('controller', 'action?myParam=myValue');                   
// http://localhost/controller/action?myParam=myValue
"> http://localhost/controller/action?myParam=myValue
$application->url('controller', 'action', ['myParam' => 'myValue'])
```