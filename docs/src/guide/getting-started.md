# Getting Started

## Installation

### Pre-requisites

* Docker Desktop
* Visual Studio Code with the devContainers plugin.  For help with setting up Visual Studio Code for Hazaar MVC development, see [Tooling](/guide/tooling).
* Git
* Composer (optional)

To get started with Hazaar MVC, you need to create an application using composer.  This is a simple process that will create a new directory containing your application and all the required dependencies.

### The Example Application

```shell
$ composer create-project hazaarlabs/example
```

Alternatively you can get the example application using Git directly:

```shell
$ git clone https://git.hazaar.io/hazaar/example.git
``` 

This will create a new directory called `example` in your current working directory.  This directory will contain the application and public directories of your project and some example code to help get you started quickly.  If installed with composer, the library will also be installed in the `vendor` directory.

Once you have the example application, you can open it in Visual Studio Code.  If you have the devContainers plugin installed, you will be prompted to open the project in a container.  This will automatically start the container and install all the required dependencies.

## What's Next?

* [Configuration](/guide/basics/configuration) - Learn how to configure your application.
* [Routing](/guide/basics/routing) - Learn how routing works for your application.
* [Models](/guide/basics/models) - Learn how to create models for your application.
* [Views](/guide/basics/views) - Learn how to create views for your application.
* [Controllers](/guide/basics/controllers) - Learn how to create controllers for your application.