# System Requirements

HazaarMVC is designed and tested on Linux but some lengths have been taken to ensure Hazaar will operate on any operating system.

Hazaar has been tested and known to work on:

* Ubuntu 12.10+
* Debian 6.0+
* Windows 7/10

If you have success running Hazaar on other operating systems, please let me know so I can update this list.

## Web Server Software

We recommend Apache 2.4+ or Nginx.  Others may work but we have not tested any as yet.

If your application is going to be using a database (this is optional) then you will want to make sure there is a database driver available for the database server you intend to use.

## PHP 5.4+

PHP must be version 5.4 or greater.  Development began on PHP 5.3 but there are features of PHP 5.4 that Hazaar depends on for some of it’s functionality, such as Closures.

## Databases

HazaarMVC has support for the following relational database servers:

* MySQL Version 4+
* PostgreSQL Version 8.1+
* Microsoft SQL Server
* SQLite

HazaarMVC also has support for the following NoSQL database servers:

* MongoDB Version 2.2.4+

!!! NOTE
    The built-in relational database support requires PDO, so you should make sure you have the correct PDOdriver extensions.