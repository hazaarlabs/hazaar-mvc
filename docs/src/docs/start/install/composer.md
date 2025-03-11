# Getting Started

Getting up and running with Hazaar is really easy and is done in only a few basic
steps.  Hazaar has been developed to run on Linux so it is compatible with any Linux
distro supported by PHP, including WSL on Windows.  

If you hit any problems, please feel free to create a support issue so they can be fixed
using the Hazaar [issue tracker](https://git.hazaar.io/hazaar/framework/issues).

Hazaar can be installed with [Composer](http://getcomposer.org) and is available via
[Packagist](http://packagist.org). 

## Installing Composer

If you have already installed Composer on your system, you can skip this step and move
on [to the next section.](#install-the-example-application).

Composer is a popular dependency management tool for PHP, created mainly to facilitate
installation and updates for project dependencies. It will check which other packages
a specific project depends on and install them for you, using the appropriate versions
according to the project requirements.

Composer runs on any system that supports PHP.  Please see the composer documentation at
http://www.getcomposer.org for details on how to install composer on your system.

On a Unix-like system, you can install Composer globally with the following command:

```
$ curl -sS https://getcomposer.org/installer | php
$ sudo mv composer.phar /usr/local/bin/composer
```

To test your installation you can run the composer command from your command line:

```
$ composer
```

And you should get output similar to this:

```
   ______
  / ____/___  ____ ___  ____  ____  ________  _____
 / /   / __ \/ __ `__ \/ __ \/ __ \/ ___/ _ \/ ___/
/ /___/ /_/ / / / / / / /_/ / /_/ (__  )  __/ /
\____/\____/_/ /_/ /_/ .___/\____/____/\___/_/
                    /_/
Composer version 2.7.1 2024-02-09 15:26:28

Usage:
  command [options] [arguments]

Options:
  -h, --help                     Display help for the given command. When no command is given display help for the list command
  -q, --quiet                    Do not output any message
  -V, --version                  Display this application version
      --ansi|--no-ansi           Force (or disable --no-ansi) ANSI output
  -n, --no-interaction           Do not ask any interactive question
      --profile                  Display timing and memory usage information
      --no-plugins               Whether to disable plugins.
      --no-scripts               Skips the execution of all scripts defined in composer.json file.
  -d, --working-dir=WORKING-DIR  If specified, use the given directory as working directory.
      --no-cache                 Prevent use of the cache
  -v|vv|vvv, --verbose           Increase the verbosity of messages: 1 for normal output, 2 for more verbose output and 3 for debug

. . .
```

This means Composer was succesfully installed on your system.

## Install the Example Application

Because Hazaar is a library, you need to create a project that depends on it for composer
to download it.  The easiest way to do this is to install the example application.  This will
also give you a starting point for development.

You can do this with one command using composer:

```
$ composer create-project hazaar/hazaar myapp
```

That's it.  You should now have the example application and Hazaar downloaded and ready
to go. You can start the application with PHP's built-in web server by running the following
command from the project directory:

```
$ cd myapp
$ composer serve
```

You can now access the application from your web browser by navigating to http://localhost:8000.

## What's Next?

* [Configuration](/docs/basics/configuration.md) - Learn how to configure your application.
* [Routing](/docs/basics/routing.md) - Learn how routing works for your application.
* [Controllers](/docs/basics/controllers.md) - Learn how to create controllers for your application.
* [Views](/docs/basics/views/overview.md) - Learn how to create views for your application.
* [Models](/docs/basics/models.md) - Learn how to create models for your application.
* [Database](/docs/dbi/overview.md) - Learn how to use databases in your application.