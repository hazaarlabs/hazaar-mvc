# Introduction

::: danger
This page is tagged as a draft and is a work in progress.  It is not yet complete and may contain errors or inaccuracies.
:::

Getting up and running with HazaarMVC is really easy and is done in only a few basic steps, depending on the operating system you are working with. I suggest Ubuntu Linux as Hazaar has been developed on Ubuntu so it will work with it. I have made some effort to ensure that Hazaar is cross-platform compatible, particularly with Windows support, but as I do not develop under Windows daily, some bugs may arise. If so, please create a support issue so they can be fixed.

Hazaar MVC is installed with [Composer](http://getcomposer.org) and available via [Packagist](http://packagist.org).

## Composer

Composer is THE dependency management tool for PHP, created mainly to facilitate installation and updates for project dependencies. It will check which other packages a specific project depends on and install them for you, using the appropriate versions according to the project requirements.

### Linux Installation

This is just a generic installation process on an Debian/Ubuntu based system. Seeing as you are a super smart developer you can translate these commands to other systems like Redhat/CentOS, etc.

#### Step 1 — Installing the Dependencies

Before we download and install Composer, we need to make sure our server has all dependencies installed.

First, update the package manager cache by running:

```shell
$ sudo apt-get update
```

Now, let's install the dependencies. We'll need curl in order to download Composer and php5-cli for installing and running it. git is used by Composer for downloading project dependencies. Everything can be installed with the following command:

```shell
$ sudo apt-get install curl php5-cli git
```

You can now proceed to the next step.

#### Step 2 — Downloading and Installing Composer

Composer installation is really simple and can be done with a single command:

```shell
$ curl -sS https://getcomposer.org/installer "> https://getcomposer.org/installer | sudo php -- --install-dir=/usr/local/bin --filename=composer
```

This will download and install Composer as a system-wide command named composer, under `/usr/local/bin`. The output should look like this:

```shell
#!/usr/bin/env php

All settings correct for using Composer
Downloading...

Composer successfully installed to: /usr/local/bin/composer
Use it: php /usr/local/bin/composer
```

To test your installation, run:

```shell
$ composer
```

And you should get output similar to this:

```shell
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

If you prefer to have separate Composer executables for each project you might host on this server, you can simply install it locally, on a per-project basis. This method is also useful when your system user doesn't have permission to install software system-wide. In this case, installation can be done with `curl -sS https://getcomposer.org/installer "> https://getcomposer.org/installer | php - this will generate a composer.phar` file in your current directory, which can be executed with php composer.phar [command].

#### Step 3 - Install the example application

Because Hazaar MVC is a library, you need to create project that depends on it for composer to download it. The easiest way to do this is to install the example application. This will also give you a starting point for development.

You can do this with one command using composer:

```shell
$ composer create-project hazaarlabs/example
```

That's it. You should now have the example application and Hazaar MVC downloaded and ready to go. Now all you need to do is set up your web server and you're good to go.

### Windows Installation

Coming soon...

# Getting Started

A great place to get up to speed quickly on Hazaar MVC is by reading the [Hazaar MVC Quickstar Guide](http://hazaar.io/quickstart).

The QuickStart covers some of the most commonly used components of Hazaar MVC.

# Contributing

Please contact [support@hazaar.io](mailto:support@hazaar.io) if you would like to get involved!

# Licence

The files in this archive are released under the Apache 2.0 License. You can find a copy of this license in [LICENCE.md](https://git.hazaar.io/hazaar/hazaar-mvc/blob/master/LICENCE.md).