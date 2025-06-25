# Getting Started Without Docker

## Installation

It is possible to develop applications without using Docker. However, this requires you to install and configure a web server, PHP, and all the required extensions and libraries yourself. This is not a trivial task and is beyond the scope of this documentation.
 
### Pre-requisites

* PHP 8.0 or greater
* Composer
* Terminal for accessing the [Hazaar Tool](/reference/hazaar-tool) via its Command Line Interface (CLI).
* Text Editor or IDE. We recommend [Visual Studio Code](https://code.visualstudio.com/) with the devContainers plugin. For help with setting up Visual Studio Code for Hazaar development, see [Tooling](/guide/tooling).
* A web server. See [Deploying to a Web Server](/guide/deploy/overview) for more information. Setting up a development web server is beyond the scope of this documentation but is similar to setting up a production web server.

## Let's Go!

To get started with Hazaar, create a new application using Composer. This will create a new directory containing your application and all the required dependencies.

### The Example Application

```shell
composer create-project hazaar/hazaar myapp
```

This will create a new directory called `myapp` in your current working directory. This directory will contain the application, library, and public directories of your project, along with some example code to help get you started quickly.

> **Tip:** The first thing you should do is familiarize yourself with the structure of your new Hazaar application. Understanding where to find and place your code, configuration, and assets will make development much easier. See the [Application Structure](/docs/start/structure.md) guide for a detailed overview.

## What's Next?

Now that you have a basic application, you can start developing your project. Here are some helpful next steps:

- [Application Structure](/docs/start/structure.md) – Learn about the layout of a Hazaar application.
- [Configuration](/docs/basics/configuration.md) – Configure your application settings.
- [Routing](/docs/basics/routing.md) – Understand how routing works in Hazaar.
- [Controllers](/docs/basics/controllers.md) – Create controllers to handle requests.
- [Views](/docs/basics/views/overview.md) – Build views to present data to users.
- [Models](/docs/basics/models.md) – Work with models and data sources.
- [Database](/docs/dbi/overview.md) – Integrate databases into your application.
- [Your First Application](/example/your-first-app) – Follow a step-by-step example to build your first Hazaar app.
