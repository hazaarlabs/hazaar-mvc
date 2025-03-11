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

This will create a new directory called `myapp` in your current working directory.  This directory will contain the application
and public directories of your project and some example code to help get you started quickly.

Open the project folder in Visual Studio Code and the [devcontainers extension](https://code.visualstudio.com/docs/devcontainers/containers) 
will prompt you to open the project in a container.  This will automatically start the container, install all the
required dependencies and run a web server for you to access the application at `http://localhost:8000`.

## What's Next?

* [Configuration](/docs/basics/configuration.md) - Learn how to configure your application.
* [Routing](/docs/basics/routing.md) - Learn how routing works for your application.
* [Controllers](/docs/basics/controllers.md) - Learn how to create controllers for your application.
* [Views](/docs/basics/views/overview.md) - Learn how to create views for your application.
* [Models](/docs/basics/models.md) - Learn how to create models for your application.
* [Database](/docs/dbi/overview.md) - Learn how to use databases in your application.