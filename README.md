# Introduction

Getting up and running with HazaarMVC is really easy and is done in only a few basic
steps, depending on the operating system you are working with.  I suggest Ubuntu Linux
as Hazaar has been developed on Ubuntu so it will work with it.  I have made some effort
to ensure that Hazaar is cross-platform compatible, particularly with Windows support, but
as I do not develop under Windows daily, some bugs may arise.  If so, please create a
support issue so they can be fixed.

Hazaar MVC is installed with [Composer](http://getcomposer.org) and available via
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

Because Hazaar MVC is a library, you need to create a project that depends on it for composer
to download it.  The easiest way to do this is to install the example skeleton application.  This will
also give you a starting point for development.

You can do this with one command using composer:

```
$ composer create-project hazaarlabs/example path/to/install
```

That's it.  You should now have the example application and Hazaar MVC downloaded and ready
to go. Now all you need to do is set up your web server and you're good to go.  

You can now have a read of our [Online Documentation](http://hazaarmvc.com/docs) for tips
on setting up a web server to run your new application.

## Test your new project

Because I'm a nice guy, i've made it super simple to test your new project without installing
any further programs.  You can start up the application using the PHP built-in web server
by executing the composer `serve` command

```
$ composer serve
```

At this point the application will be accessible from your web browser by navigating to
http://localhost:8080.


# Getting Started with Development

A great place to get up to speed quickly on Hazaar MVC is by reading the
[Hazaar MVC Getting Started Guide](http://hazaarmvc.com/docs/getting-started).

The QuickStart covers some of the most commonly used components of Hazaar MVC.

# Contributing

Please contact [support@hazaarlabs.com](mailto:support@hazaarlabs.com) if you would like to
get involved!

# Licence

The files in this archive are released under the Apache 2.0 License. You can find a
 copy of this license in [LICENCE.md](https://git.hazaarlabs.com/hazaar/hazaar-mvc/blob/master/LICENCE.md).