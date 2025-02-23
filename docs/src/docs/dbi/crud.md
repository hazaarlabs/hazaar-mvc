# CRUD

## Querying the Database

The database abstraction layer provides a simple and easy to use interface for querying the database by providing database agnostic methods for executing queries.

A simple example of querying the database would be:

```php
$db = new Hazaar\DBI\Adapter();
$result = $db->table('my_table')->find();
```

The `find()` method is used to execute a `SELECT` query on the database.  The `table()` method is used to specify the table to query.  The `find()` method returns a
`Hazaar\DBI\Result` object that can be used to iterate over the results as well as return information about the query.  

See the [Hazaar\DBI\Result](/api/Hazaar/DBI/Result) class documentation for more information.

```php
$result = $db->table('my_table')->find(['id' => 1]);
```

The `fetch()` method is used to fetch the next row from the result set.  The `fetch()` method returns an associative array that contains the values of the columns in the row.

```php
$result = $db->table('my_table')->find();
while($row = $result->fetch()){
    // Do something with the row
}
```

### Finding a Single Row

The `find()` method can be used to find a single row in the database by passing a single primary key value as an argument.

```php
$row = $db->table('my_table')->findOne();
```