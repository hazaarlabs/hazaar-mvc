<p align="center"><a href="https://hazaar.io" target="_blank"><img src="https://git.hazaar.io/hazaar/hazaar-mvc/-/raw/master/libs/hazaar-icon-lg.png?inline=false" width="200" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://git.hazaar.io/hazaar/hazaar-mvc/badges/master/pipeline.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/hazaarlabs/hazaar-mvc" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/hazaarlabs/hazaar-mvc" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/hazaarlabs/hazaar-mvc" alt="License"></a>
</p>

# About Hazaar

Hazaar is a robust light-weight MVC framework for PHP8+ that focuses on speed, efficiency and simplicity.  Some of the
features availble in Hazaar include:

* [Fast and configurable router](https://hazaar.io/docs/basics/routing.html)
* [Fluent database ORM](https://hazaar.io/docs/dbi/overview.html)
* [Multiple cache and session backend options](https://hazaar.io/docs/advanced/caching/overview.html)
* [Database schema management with snapshots](https://hazaar.io/docs/dbi/schema-manager.html)
* Unified file storage system
* [Background job processing](https://hazaar.io/docs/components/warlock/delayed-exec.html)
* [Built-in WebSocket server](https://hazaar.io/docs/components/warlock/overview.html)
* [Robust message broker](https://hazaar.io/docs/components/warlock/realtime-signalling.html)

# Getting Started

Getting up and running with HazaarMVC is really easy and is done in only a few basic
steps, depending on the operating system you are working with.  I suggest Ubuntu Linux
as Hazaar has been developed on Ubuntu so it will work with it.  I have made some effort
to ensure that Hazaar is cross-platform compatible, particularly with Windows support, and
I am now developing under Windows daily so Hazaar has become quite stable under Windows.

If you hit any problems, please feel free to create a support issue so they can be fixed
at https://git.hazaar.io/hazaar/hazaar-mvc/issues.

Hazaar is installed with [Composer](http://getcomposer.org) and available via
[Packagist](http://packagist.org). 

## Installing with Composer

Composer is a popular dependency management tool for PHP, created mainly to facilitate
installation and updates for project dependencies. It will check which other packages
a specific project depends on and install them for you, using the appropriate versions
according to the project requirements.

Composer runs on any system that supports PHP.  Please see the composer documentation at
http://www.getcomposer.org for details on how to install composer on your system.

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
Composer version 1.0-dev (9859859f1082d94e546aa75746867df127aa0d9e) 2015-08-17 14:57:00

Usage:
 command [options] [arguments]

Options:
 --help (-h)           Display this help message
 --quiet (-q)          Do not output any message
 --verbose (-v|vv|vvv) Increase the verbosity of messages: 1 for normal output, 2 for more verbose output and 3 for debug
 --version (-V)        Display this application version
 --ansi                Force ANSI output
 --no-ansi             Disable ANSI output
 --no-interaction (-n) Do not ask any interactive question
 --profile             Display timing and memory usage information
 --working-dir (-d)    If specified, use the given directory as working directory.

. . .
```

This means Composer was succesfully installed on your system.

## Install the Example Application

Because Hazaar is a library, you need to create a project that depends on it for composer
to download it.  The easiest way to do this is to install the example skeleton application.  This will
also give you a starting point for development.

You can do this with one command using composer:

```
$ composer create-project hazaarlabs/example path/to/install
```

That's it.  You should now have the example application and Hazaar downloaded and ready
to go. Now all you need to do is set up your web server and you're good to go.  

You can now have a read of our [Online Documentation](https://scroly.io/hazaarmvc) for tips
on setting up a web server to run your new application.

## Test your new project

Because I'm a nice guy, i've made it super simple to test your new project without installing
any further programs.  You can start up the application using the PHP built-in web server
by executing the composer `serve` command from your new project directory:

```
$ composer serve
```

At this point the application will be accessible from your web browser by navigating to
http://localhost:8080.


# Getting Started with Development

A great place to get up to speed quickly on Hazaar is by reading the
[Hazaar Getting Started Guide](https://scroly.io/hazaarmvc/latest/getting_started/requirements.md).

The QuickStart covers some of the most commonly used components of Hazaar.

# Contributing

Please contact [support@hazaar.io](mailto:support@hazaar.io) if you would like to
get involved!

# Licence

The files in this archive are released under the Apache 2.0 License. You can find a
 copy of this license in [LICENCE.md](https://git.hazaar.io/hazaar/hazaar-mvc/blob/master/LICENCE.md).