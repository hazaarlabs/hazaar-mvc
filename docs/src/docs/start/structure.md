# Project Layout

::: danger
This page is tagged as a draft and is a work in progress.  It is not yet complete and may contain errors or inaccuracies.
:::

Before you start developing your first application, you need to understand the layout of the Hazaar application directory. You will have a project root, which will usually be the name of your project (if you used the The Config Tool to create it. Inside that directory you will have application, library and public directories which define the core structure of a HazaarMVC application.

HazaarMVC has a project layout like most other MVC frameworks. The project root must look like this to work correctly:

```
.
├─ application
│  ├─ configs
│  │  ├─ application.json
│  │  ├─ database.json
│  │  └─ ...
│  ├─ controllers
│  │  ├─ Error.php
│  │  ├─ Index.php
│  │  └─ ...
│  ├─ models
│  │  ├─ Data.php
│  │  └─ ...
│  └─ views
│     ├─ application.phtml
│     ├─ index.phtml
│     ├─ error.phtml
│     ├─ custom.tpl
│     └─ ...
├─ public
│  └─ index.php
└─ composer.json
```

## `application` directory

This is the application directory that contains all the models, views and controllers for your application. This is where you will do all of your work. The application directory contains the following sub-directories:

### `configs` directory

This is where you will put all of your application configuration files. The only file that is required is the `application.json` file. This file is used to configure the application and is explained in the [Configuration](/guide/basics/configuration) section.

### `controllers` directory

This is where you will put all of your application controllers. Controllers are the glue that binds your models and views together. Controllers are explained in the [Controllers](/guide/basics/controllers) section.

### `models` directory

This is where you will put all of your application models. Models are the data layer of your application. Models are explained in the [Models](/guide/basics/models) section.

### `views` directory

This is where you will put all of your application views. Views are the presentation layer of your application. Views are explained in the [Views](/guide/basics/views) section.

## `public` directory

This is where you point your web server configuration to for the document root. It will contain two files. index.php and .htaccess. Don't mess with either unless you really know what you are doing and need to set up some sort of custom execution path.