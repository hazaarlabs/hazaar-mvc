# Getting Started

::: danger
We are committed to the principle of _release early, release often_. As a result, this documentation is a work in progress. The original **Hazaar MVC** documentation served primarily as an internal reference, containing a collection of assorted information. Consequently, some sections may seem incomplete or unclear without prior knowledge.

Additionally, some links may be broken as we continue to update and reorganize the content. 

Rest assured, we are actively working on organizing and rewriting the documentation to better serve your needs. Thank you for your patience as we improve the documentation for the latest version of **Hazaar MVC**.
:::

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