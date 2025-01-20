# Hazaar Constants

::: danger
This page is tagged as a draft and is a work in progress.  It is not yet complete and may contain errors or inaccuracies.
:::

Hazaar provides a number of global constants that can be used in applications.

## General

Constant containing the acceptable line break character sequence for the current OS.  Usually `\r\n` for Windows and `\n` for Linux.

## Application Info

### APPLICATION_ENV

Constant containing the current environment the application is running in.  This is usually set as an environment variable in the web server configuration.  Defaults to `development`.

### APPLICATION_USER

Constant containing the name of the user running the script
