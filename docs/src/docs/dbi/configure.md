# Hazaar DBI Configuration

Hazaar provides a simple and easy to use database abstraction layer that allows you to interact with a variety of database systems in a consistent way.  The database abstraction layer is built on top of the [PDO](http://php.net/manual/en/book.pdo.php) extension and provides a simple and easy to use interface for interacting with databases.

## Configuring a Database Connection

To use the database abstraction layer you first need to configure a database connection.  This is done by creating a `database.json` file in your application configuration directory.  The database configuration file is a JSON formatted file that contains the connection details for each database you wish to connect to.  The database configuration file is loaded automatically by the framework when it is required.

```json
{
    "development": {
        "type": "pgsql",
        "host": "localhost",
        "dbname": "my_database",
        "user": "my_user",
        "password": "my_password"
    },
    "test": {
        "type": "sqlite",
        "file": "test.db"
    }
}
```

The database configuration file can contain multiple database configurations.  The default configuration to use is determined by the current `APPLICATION_ENV` environment variable.  If the `APPLICATION_ENV` environment variable is not set, the default configuration is `development`.

```php
$db = new Hazaar\DBI\Adapter();
```

Alternatively, you can specify the configuration to use by passing the name of the configuration to the `Hazaar\DBI\Adapter` constructor.

```php
$db = new Hazaar\DBI\Adapter('test');
```

Lastly, you can also specify the configuration as an array.  This is useful for quick testing or when you don't want to use a configuration file but is not recommended for production use.

```php
$db = new Hazaar\DBI\Adapter(array(
    'type' => 'pgsql',
    'host' => 'localhost',
    'dbname' => 'test',
    'user' => 'test',
    'password' => 'test'
));
```



## PostgreSQL Replication

If your database host is running PostgreSQL 9.0+ replication then Hazaar has some extra magic for you. It's possible to use a read-only slave for most queries and then have Hazaar's database adapter automatically send all write operations to the master. Without the application knowing, or caring.

To achieve this, all you need to do is add the

```ini
db.master
```

parameter to the database.ini file and the Hazaar DB adapter will take care of the rest.

### How does it work?

Basically, if the `db.master` parameter is set, then the adapter knows to check if the `db.host` is a slave by executing thePGSQL specific query:

```sql
SELECT pg_is_in_recovery()
```

This query indicates that the host is in recovery mode, meaning it is a replication slave. If this is true, then the adapter will create a second connection using the main connection parameters but switches out the host parameter with the value in `db.master`.

After that, any write operations will use the second connection which will write to the master.