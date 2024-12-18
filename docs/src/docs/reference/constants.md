# Hazaar MVC Constants

::: danger
This page is tagged as a draft and is a work in progress.  It is not yet complete and may contain errors or inaccuracies.
:::

Hazaar MVC provides a number of global constants that can be used in applications.

## General

### ROOT_PATH

Constant containing the absolute filesystem path that contains the entire Hazaar MVC project.

### PUBLIC_PATH

Constant containing the absolute filesystem path to the application public directory.  The public directory is the entrypoint directory that contains the `index.php` entrypoint file that your web server executes.
 
### SUPPORT_PATH

Constant containing the absolute filesystem path to the HazaarMVC support library.  This is the path

### LIBRARY_PATH

Constant containing the absolute filesystem path to the HazaarMVC library.

### LINE_BREAK

Constant containing the acceptable line break character sequence for the current OS.  Usually `\r\n` for Windows and `\n` for Linux.

## Application Info

### APPLICATION_USER

Constant containing the name of the user running the script

## Application Paths

### APPLICATION_ENV

### APPLICATION_PATH

Constant containing the path in which the current application resides.

### APPLICATION_BASE

Constant containing the application base path relative to the document root.

### CONFIG_PATH

Constant containing the absolute filesystem path to the default configuration directory.
