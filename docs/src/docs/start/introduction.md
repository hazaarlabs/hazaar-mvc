# Getting Started

::: danger
This page is tagged as a draft and is a work in progress.  It is not yet complete and may contain errors or inaccuracies.
:::

::: danger
We are committed to the principle of _release early, release often_. As a result, this documentation is a work in progress. The original **Hazaar** documentation served primarily as an internal reference, containing a collection of assorted information. Consequently, some sections may seem incomplete or unclear without prior knowledge.

Additionally, some links may be broken as we continue to update and reorganize the content. 

Rest assured, we are actively working on organizing and rewriting the documentation to better serve your needs. Thank you for your patience as we improve the documentation for the latest version of **Hazaar**.
:::

## Installation

### Pre-requisites

* [Docker Desktop](https://www.docker.com/products/docker-desktop/)
* [Git](https://git-scm.com/)
* [Composer](https://getcomposer.org/) (optional)
* [Visual Studio Code](https://code.visualstudio.com/)
* [devContainers plugin for VSCode](https://code.visualstudio.com/docs/devcontainers/containers).  

::: tip
For help with setting up Visual Studio Code for Hazaar development, see [Tooling](/guide/tooling).
:::

To get started with Hazaar, you need to create an application using composer.  This is a simple process that will create a new directory containing your application and all the required dependencies.

### The Example Application

```shell
$ composer create-project hazaar/hazaar myapp
```

Alternatively you can get the example application using Git directly:

```shell
$ git clone https://git.hazaar.io/hazaar/hazaar myapp
``` 

This will create a new directory called `myapp` in your current working directory.  This directory will contain the application and public directories of your project and some example code to help get you started quickly.  If installed with composer, the library will also be installed in the `vendor` directory.

Once you have the example application, you can open it in Visual Studio Code.  If you have the [devcontainers extension](https://code.visualstudio.com/docs/devcontainers/containers) installed, you will be prompted to open the project in a container.  This will automatically start the container and install all the required dependencies.

## What's Next?

* [Configuration](/docs/basics/configuration.md) - Learn how to configure your application.
* [Routing](/docs/basics/routing.md) - Learn how routing works for your application.
* [Controllers](/docs/basics/controllers.md) - Learn how to create controllers for your application.
* [Views](/docs/basics/views/overview.md) - Learn how to create views for your application.
* [Models](/docs/basics/models.md) - Learn how to create models for your application.
* [Database](/docs/dbi/overview.md) - Learn how to use databases in your application.