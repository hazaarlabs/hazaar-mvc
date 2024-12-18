# Delayed and Background Jobs

::: danger
This page is tagged as a draft and is a work in progress.  It is not yet complete and may contain errors or inaccuracies.
:::

One of the more advanced features of HazaarMVC is it's background job schedular. This mechanism is completely transparent and gives developers the ability to execute jobs in the background easily, run them after a delay, or schedule them to run at a specific date and time, all within the context of the current application. The schedular is very light, but powerful, and so does not consume many resources. Great pains have also been taken to make sure that only a single instance of the schedular is required per host.

## Overview

The schedular itself runs in a single thread that will never block and there is only one instance per install of HazaarMVC. This should mean only one instance per host as application should be sharing their HazaarMVC library. Jobs are executed in their own child processes so that they won't hang up or kill the main thread. Limits have also been placed on parallel executions jobs so that resources will not be consumed out of control, should a rogue job be doing something it shouldn't. Full logging is enabled with statistics gathering making it easy to identify any problems.

## Methods

There are currently two methods for scheduling function execution.  In both methods, `$function` is something that the PHP function `is_callable()` considers to be a callable object.  Support for static method function references are also such as `Application\Model\MyModel::doTheThing`.

### delay($seconds, $function, $tag = null, $overwrite = false)

This will run the function after the $seconds number of seconds has passed.

Returns the job ID on success, false otherwise.

### schedule($when, $function, $tag = null, $overwrite = false)

This will schedule the function to execute at a specific date/time specified by $when. The schedule method usesstrtotime() internally to resolve text times in $when into epoch values. This means that any string times thatstrtotime() supports can be used, including 'tomorrow 1pm', 'next week', etc.

You can also use epoch integer values.

::: warning 
Be aware however that there may be configuration differences between the schedular and your application which may cause timezones to be different. It is suggested that if you are setting an explicit time of execution that you should specify the timezone in the time string as a matter of course. Using the PHP function date('c', time()) will do this.
:::

Returns the job ID on success, false otherwise.

## Example Usage

Consider the following example code:

```php
$control = new Hazaar\Warlock\Control();
    
$code = function(){
    echo "APPLICATION_PATH = " . APPLICATION_PATH . "\n";        
    echo "APPLICATION_ENV  = " . APPLICATION_ENV . "\n";
};
    
if($control->runDelay(5, $code)){
    $this->redirect($this->url());
}

throw new \Exception('Unable to execute delayed function');
```

The above is a very simple example of how to use the runDelay() method to execute a function after a certain period of time has elapsed. We will now step through each line and outline what is happening.

### 1 - Instantiate the schedular control object

```php
$control = new Hazaar\Warlock\Control();
```

This will set up the schedular control object. This object handles all communication between the application and the schedular. It will also start up a new schedular instance if one is not already running.

It is possible to run the schedular from the command line. See the section running the schedular from the command-line for info.

### 2 - Create an 'anonymous function' (aka Closure)

In PHP, anonymous functions are implemented using the Closure class. The syntax to create an anonymous function is just the same as most other languages that allow anonymous functions.

```php
$code = function(){
    echo "APPLICATION_PATH = " . APPLICATION_PATH . "\n";
    echo "APPLICATION_ENV  = " . APPLICATION_ENV . "\n";
};
```

The function that we have created simply echos out the current APPLICATION_PATH and APPLICATION_ENV environment variables. This will prove that the function is being executed in the correct application context. This output will appear in the schedular log file.

Because the function is executed in the application context, the $this object will refer to the current application object. Making things like this possible:

```php
$appname = $this->config->app['name'];
```

### 3 - Schedule the code for execution

```php
$control->delay(5, $code);
```

In the above code we send the function to the runDelay() method and tell it to run the function in 5 seconds.

If we wanted to specify the time at which to run the function we could use the schedule() method.

```php
$control->schedule('5pm', $code);
```

This will schedule the function to execute at 5pm.

## Tags

To help keep track of jobs we have added a feature called tags. Tags are just string values given to jobs to identify them as unique. If you try and add a job with a tag that already exists, then two things could happen:

* Adding the job will fail. The error returned will state that the job could not be added because a job with the specified tag already exists.
* If the $overwrite parameter is set true, then the job will be overwritten with the new function and schedule information.

The benefits of tagging are that you can ensure that a particular function will never attempt to be executed if it already exists.

## Running Warlock from the command-line

It's not suggested that a developer ever do this, but during testing it is sometimes handy to have control over when Warlock runs.

To run Warlock from the command line do the following:

* *cd* into your Hazaar MVC application path
* Run the following command:
```shell
# sudo -u www-data php vendor/hazaarlabs/hazaar-warlock/src/Server.php
```
Logging data will then be output to your terminal.

## Calling static class methods

A new feature in Warlock 2.2 is the ability to call static class methods instead of having to use a closure.  Using a static class
method works almost exactly the same as using a closure except that the code can be defined anywhere in your project.

For example:

```php
namespace Application\Model;

class MyTestClass {

    static public function doTheThing(){
    
        $this->log(W_INFO, 'The thing is done!');

    }

}
```

If you use an IDE that attempts to resolve variable references you may get an error/warning about the use of `$this` in a static method.  Rest assured
this is normal and the code is actually executed in the context of a Warlock process and `$this` references that process object.  The process object
allows the use of `trigger`, `substribe`, `log` and any other methods normally available.

To execute this method in the background you can pass the callable in one of the following ways:

```php
$this->runDelay(30, array('Application\\Model\\MyTestClass', 'doTheThing'));
$this->runDelay(30, 'Application\\Model\\MyTestClass::doTheThing');
```

## Globally Scheduled Jobs

With Warlock 2.2 it is now possible to schedule jobs to execute without a service, and without being triggered by application code.  This allows
you to set up static method and have it executed on a specified schedule.

Using the above example class `myTestClass`, if we wanted this method to run every hour on business days between 9am and 5pm we can simply
add the following to the main Warlock config file `warlock.json`.

```json
{
    "{{APPLICATION_ENV}}":{
        "schedule": [
            {
                "when": "0 9-17 * * 1-5",
                "exec": "Application\\Model\\myTestClass::handleTestEvent"
            }
        ]
    }
}
```

When the scheduled time rolls around, a new *Runner* process will be started up and the `Application\\Model\\myTestClass::handleTestEvent` method
will be executed.  
