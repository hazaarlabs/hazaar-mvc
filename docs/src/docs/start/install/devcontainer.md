# Using VSCode DevContainers with Hazaar

The easiest way to get started with Hazaar is by using the [Visual Studio Code](https://code.visualstudio.com/) [Dev Containers](https://code.visualstudio.com/docs/devcontainers/containers) extension. This sets up a complete development environment for you, with all dependencies installed and ready to go.

The Hazaar example application is pre-configured for Dev Containers. It includes a `Dockerfile` and a `.devcontainer` directory for container configuration, based on the [FrankenPHP](https://frankenphp.dev) image. This provides a web server suitable for both development and production.

::: tip
For more on FrankenPHP and Hazaar integration, see the [FrankenPHP documentation](/docs/deploy/frankenphp.md).
:::

---

## 1. Prerequisites

Make sure you have the following installed:

- [Docker Desktop](https://www.docker.com/products/docker-desktop/)
- [Git](https://git-scm.com/)
- [Visual Studio Code](https://code.visualstudio.com/)
- [Dev Containers extension for VSCode](https://marketplace.visualstudio.com/items?itemName=ms-vscode-remote.remote-containers)

---

## 2. Clone the Example Application

Clone the Hazaar example application using Git:

```sh
git clone https://git.hazaar.io/hazaar/hazaar myapp
```

This creates a new `myapp` directory with the application code and example configuration.

---

## 3. Open in VSCode and Launch the Dev Container

1. Open the `myapp` folder in Visual Studio Code.
2. When prompted by the Dev Containers extension, click **Reopen in Container**.

This will:
- Build and start the development container
- Install all required dependencies
- Launch a web server for your application

> **Note:** The first build may take a few minutes depending on your system and internet speed.

![Reopen in container](/assets/devcontainers1.png)

---

## 4. Install Composer Dependencies

Once the container is running, you must install PHP dependencies with Composer. VSCode will usually prompt you to do this—click **Install** when prompted.

Alternatively, open a terminal in the container and run:

```sh
composer install
```

![Install composer dependencies](/assets/devcontainers2.png)

---

## 5. Run and View the Application

After installing dependencies, your application is ready! Open your browser and go to [http://localhost:8000](http://localhost:8000) to see the example app running.

![Open in browser](/assets/devcontainers3.png)

---

## 6. Example Application

The example application provides a working starting point, including:
- Application and public directories
- Example code and configuration
- Dev Container setup for rapid development

![Example application](/assets/devcontainers4.png)

---

## What's Next?

- [Configuration](/docs/basics/configuration.md) – Learn how to configure your application.
- [Routing](/docs/basics/routing.md) – Understand routing in Hazaar.
- [Controllers](/docs/basics/controllers.md) – Create controllers for your app.
- [Views](/docs/basics/views/overview.md) – Build views for your app.
- [Models](/docs/basics/models.md) – Work with models.
- [Database](/docs/dbi/overview.md) – Use databases in your app.

---

If you have questions or run into issues, check the [FAQ](/docs/faq/) or reach out via the [contact page](/contact).