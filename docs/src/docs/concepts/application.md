# The Application

::: danger
This page is tagged as a draft and is a work in progress.  It is not yet complete and may contain errors or inaccuracies.
:::

## Bootstrap

In the default application root directory you will find a file called bootstrap.php. This file is executed each time the application is executed by a request to the server. It's a great place to put global configuration options and other things that will always be required by your application, such as configuring the database layer.

The script is executed after the application has been initialised, but before any controllers are initialised or actions are executed. The script is executed in the context of the Application object, which means it's members are easily accessible. TheApplication::config member is accessible and holds the current application configuration. To access theApplication::config member use $this->config.

For example, to set a default date format from one configured in the application config:

```php
Hazaar_View::$dateformat = $this->config->app['dateformat'];
```

You could also use it to configure your database once so that you don't have to do it each time a database object is required:

```php
Hazaar_Db_Adapter_Pgsql::configure([
      'host' => $this->config->db['host'],
      'port' => $this->config->db['port'],
      'database' => $this->config->db['database']
      'username' => $this->config->db['username']
      'password' => $this->config->db['password']
]);
```

Or, a shortcut in this case could be:

```php
Hazaar_Db_Adapter_Pgsql::configure($this->config->db);
```