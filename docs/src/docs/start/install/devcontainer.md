# Using VSCode DevContainers

The best way to get started with Hazaar is to use the [Visual Studio Code](https://code.visualstudio.com/) [devContainers](https://code.visualstudio.com/docs/devcontainers/containers) extension.  This will automatically setup a development environment for you with all the required dependencies installed and ready to go.

The Hazaar example application is already setup to use devContainers so you can get started quickly.  It includes
a `Dockerfile` and a `.devcontainer` directory that contains the configuration for the development container.  It
is based on the [FrankenPHP](https://frankenphp.dev) image which includes a web server that can be used for both
development and production environments.

::: tip
For more information on FrankenPHP and how Hazaar supports it, see the [FrankenPHP](/docs/deploy/frankenphp.md) documentation. 
:::

## Install Dependencies

You will need to have the following installed on your system:

* [Docker Desktop](https://www.docker.com/products/docker-desktop/)
* [Git](https://git-scm.com/)
* [Visual Studio Code](https://code.visualstudio.com/)
* [devContainers plugin for VSCode](https://code.visualstudio.com/docs/devcontainers/containers).

### The Example Application

Grab the example application using Git directly:

```shell
$ git clone https://git.hazaar.io/hazaar/hazaar myapp
```
Which will output something like:

```shell
Cloning into 'myapp'...
warning: redirecting to https://git.hazaar.io/hazaar/hazaar.git/
remote: Enumerating objects: 552, done.
remote: Counting objects: 100% (293/293), done.
remote: Compressing objects: 100% (244/244), done.
remote: Total 552 (delta 150), reused 23 (delta 23), pack-reused 259 (from 1)
Receiving objects: 100% (552/552), 6.89 MiB | 8.49 MiB/s, done.
Resolving deltas: 100% (254/254), done.
```

This will create a new directory called `myapp` in your current working directory.  This directory will contain the application
and public directories of your project and some example code to help get you started quickly.

Open the project folder in Visual Studio Code and the [devcontainers extension](https://code.visualstudio.com/docs/devcontainers/containers) 
will prompt you to open the project in a container.  Click the **Reopen in Container** button to open the project in a container.

This will automatically build and start the container, install all the required dependencies and run a web server for you to access
the application.  This process can take a minute or two depending on your computer and internet connection.

![Reopen in container](/assets/devcontainers1.png)

::: warning
Once the container is running you will see that the web server is running, however the application is not yet functional.
:::

You will need to install the composer dependencies.  VSCode will prompt you to do this automatically.  Click the **Install** button to install the composer dependencies.

![Install composer dependencies](/assets/devcontainers2.png)

Optionally you can open a terminal in the container and run the composer install command manually:

```shell
$ composer install
```

![Open in browser](/assets/devcontainers3.png)

Once the composer dependencies are installed your application is ready to go.  Open your browser and navigate to `http://localhost:8000` to see the example application running.

![Example application](/assets/devcontainers4.png)

## What's Next?

* [Configuration](/docs/basics/configuration.md) - Learn how to configure your application.
* [Routing](/docs/basics/routing.md) - Learn how routing works for your application.
* [Controllers](/docs/basics/controllers.md) - Learn how to create controllers for your application.
* [Views](/docs/basics/views/overview.md) - Learn how to create views for your application.
* [Models](/docs/basics/models.md) - Learn how to create models for your application.
* [Database](/docs/dbi/overview.md) - Learn how to use databases in your application.