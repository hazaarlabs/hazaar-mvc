# Getting Started Without Docker

::: danger
This page is tagged as a draft and is a work in progress.  It is not yet complete and may contain errors or inaccuracies.
:::

## Installation

It is of course possible to develop applications without using docker.  This is not recommended however as it will require you to install and configure a web server, PHP, and all the required extensions and libraries yourself.  This is not a trivial task and is beyond the scope of this documentation.
 
### Pre-requisites

* PHP 7.0 or greater
* Composer
* Terminal for accessing the [Hazaar Tool](/reference/hazaar-tool) via it's Command Line Interface (CLI).
* Text Editor or IDE.  We recommend [Visual Studio Code](https://code.visualstudio.com/) with the devContainers plugin.  For help with setting up Visual Studio Code for Hazaar MVC development, see [Tooling](/guide/tooling).
* A web server.  See [Deploying to a Web Server](/guide/deploy/overview) for more information.  Setting up a development web server is beyond the scope of this documentation but it is similar to setting up a production web server.

## Let's Go!

To get started with Hazaar MVC, you need to create an application using composer.  This is a simple process that will create a new directory containing your application and all the required dependencies.

### The Example Application

```shell
$ composer create-project hazaarlabs/example
```

This will create a new directory called `example` in your current working directory.  This directory will contain the application, library, and public directories of your project and some example code to help get you started quickly.

### The Skeleton Application _(Optional)_

```shell
$ composer create-project hazaarlabs/skeleton myapp
```

This will create a new directory called `myapp` in your current working directory.  This directory will contain the application, library, and public directories of your project with no example code.  This is a good starting point for a new project.

## File Structure

The file structure of a Hazaar MVC application is fairly straighforward.  There are only a few directories that you need to be aware of and looks something like this:

```
.
├─ application
│  ├─ configs
│  │  └─ application.json
│  ├─ controllers
│  │  └─ IndexController.php
│  ├─ models
│  │  └─ Data.php
│  └─ views
│     ├─ index.phtml
│     └─ layouts
├─ public
│  └─ index.php
└─ package.json
```

### The `public` Directory

The public directory is the root of your web application.  This is the directory that you will point your web server to.  It contains the `index.php` file which is the entry point for your application.

### The `application` Directory

The application directory is where your application code lives.  This is where you will write your controllers, models, views, and other application specific code.

### The `configs` Directory

The configs directory is where you will put your application configuration files.  These are JSON files that contain configuration information for your application.  The `application.json` file is the main configuration file for your application.

### The `controllers` Directory

The controllers directory is where you will put your application controllers.  These are PHP files that contain the logic for your application.  Controllers are responsible for handling requests and returning responses.

### The `models` Directory

The models directory is where you will put your application models.  These are PHP files that contain the data and business logic for your application.  Models are responsible for interacting with databases and other data sources.

### The `views` Directory

The views directory is where you will put your application views.  These are PHTML files that contain the presentation logic for your application.  Views are responsible for displaying the data to the user.

## What's Next?

Now that you have a basic application, you can start developing your application.  See the [Your First Applications](/example/your-first-app) example for more information on how to get started with Hazaar MVC.