# Getting Started with Hazaar

Getting up and running with Hazaar is quick and straightforward. This guide will walk you through installing Composer (if you don't have it already), setting up a new Hazaar project, and running your first application.

Hazaar is developed for Linux and works on any Linux distribution supported by PHP, including WSL on Windows. If you encounter any issues, please create a support issue using the Hazaar [issue tracker](https://git.hazaar.io/hazaar/framework/issues).

---

## 1. Prerequisites

- **PHP 8.0 or higher** must be installed on your system.
- **Composer** (dependency manager for PHP)

---

## 2. Install Composer

Hazaar is distributed via [Composer](https://getcomposer.org) and is available on [Packagist](https://packagist.org/packages/hazaar/hazaar).

If you already have Composer installed, skip to [Install the Example Application](#3-install-the-example-application).

Composer is a popular dependency management tool for PHP. It checks which packages your project depends on and installs them for you.

To install Composer globally on a Unix-like system, run:

```sh
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
```

> **Tip:** If you get a "command not found" error after installation, make sure `/usr/local/bin` is in your `PATH`.

To verify Composer is installed, run:

```sh
composer
```

You should see output similar to:

```
   ______
  / ____/___  ____ ___  ____  ____  ________  _____
 / /   / __ \/ __ `__ \/ __ \/ __ \/ ___/ _ \/ ___/
/ /___/ /_/ / / / / / / /_/ / /_/ (__  )  __/ /
\____/\____/_/ /_/ /_/ .___/\____/____/\___/_/
                    /_/
Composer version 2.7.1 2024-02-09 15:26:28
... (more output) ...
```

For more details or troubleshooting, see the [Composer documentation](https://getcomposer.org/doc/).

---

## 3. Install the Example Application

Hazaar is a library, so you need to create a project that depends on it. The easiest way is to use the example application, which provides a working starter template for your own development.

To create a new project called `myapp`:

```sh
composer create-project hazaar/hazaar myapp
```

This will download the example application and all Hazaar dependencies into a new `myapp` directory.

---

## 4. Run the Application

Change into your new project directory and start the built-in development server:

```sh
cd myapp
composer serve
```

You can now access your application in your web browser at [http://localhost:8000](http://localhost:8000).

---

## 5. What's Next?

- [Configuration](/docs/basics/configuration.md) – Learn how to configure your application.
- [Routing](/docs/basics/routing.md) – Understand how routing works.
- [Controllers](/docs/basics/controllers.md) – Create controllers for your application.
- [Views](/docs/basics/views/overview.md) – Build views for your application.
- [Models](/docs/basics/models.md) – Work with models.
- [Database](/docs/dbi/overview.md) – Use databases in your application.

---

If you have questions or run into issues, check the [FAQ](/docs/faq/) or reach out via the [contact page](/contact).