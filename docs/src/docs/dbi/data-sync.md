# Hazaar DBI Data Synchronisation

::: danger
This page is tagged as a draft and is a work in progress.  It is not yet complete and may contain errors or inaccuracies.
:::

Hazaar DBI allows developers to define static data that can be imported into a database using a power data synchronisation process.  

This could be used to:

1. Initialise a new created database after software installation.
2. Ensure some data is maintained, such as an administration or system user account.
3. Manage business operation data and workflows.

The data sync process is quite powerful and offer multiple methods of synchronising data into a database, from custom SQL queries to an advanced row differencing engine that allows 

## Data Sync File Format

Datasync files are text files with JSON formatted objects.  The main container of a datasync file is a JSON array.  This allows synchronisations to be rolled out in stages as each element in the container array is processed in order. This is especially useful in relational databases where you need to link records.

An example layout could be:

```json
[                                               //Sync Container Array
    {                                           //Sync Stage Object
        "message": "Your custom message"        //Sync log message output
    },  
    {                                           //Sync Stage Object
        "exec": "DELETE FROM tablename"         //Custom SQL execution
    },    
    {
        "vars": {
            "foo": "bar"                        //Global variables are defined when there is no "table" property.  These variables will be saved across sync stages.
        }
    },
    {                                           //Sync Stage Object - Row Sync Object
        "table": "{{tablename}}",               //The name of the table this element will work with.
        "insertonly": false,                    //Will only insert new records.  Useful for system initialisation.
        "truncate": true,                       //Boolean indicating that the table should be truncated and rebuilt on execution of the sync.  This resets any sequences on the table.
        "purge": "SQL CRITERIA",                //Purges specific rows from the table before the sync.  This is similar to truncate except only rows that match the SQL WHERE criteria specific will be removed and the sequences are not reset.  Note: Do NOT include the WHERE, just the criteria. ie: `module = 'foobar'`.
        "keys": [ "optional" ],                 //Optional list of fields to use as the unique identifier keys (see Unique Identifier Keys).
        "vars": [ "optional" ],                 //Defines variables for use in macros
        "refs": [ "optional" ],                 //Defines data references that apply to every row that will be sync'd in this stage.
        "rows": [                               //An array of row object elements that will be sync'd into the database. 
            {
                "field1": "value1",             //String fields are supported.
                "field2": 2,                    //Numeric fields, including floats/doubles, are supported.
                "field3": true,                 //Boolean fields are supported.
                "field4": [ "a", "b", "c" ],    //Native [ARRAY] columns are also supported.
                "field5": {                     //JSON Objects are also supported if the target column has a JSON data type.  These fields also support
                    "key1": "element1",         //Array objects which are defined the same as the above [ARRAY] data type fields.  The data sync engine
                    "key2": "element2"          //will detect that target column type and convert the field value as needed.
                }
            }
        ],
        "source": {                             //An object that contains remote data sync properties
            "config": "string/object",          //Optional. The DBI config property.  This can be a string that names a DBI
                                                //configuration, or an object that contains DBI config properties.  Required if hostURL is not set.
            "hostURL": "string",                //Optional. A remote host running Hazaar DBI that will allow data sync access. Required if config is not set.
            "syncKey": "string",                //WARNING:  This is only for testing.  Sync files are not secure and
                                                //should not contain sensitive keys like this.  This should be set on
                                                //the DBI configuration object stored in an encrypted DBI config file.
            "select": {},                       //Optional. Sent to the DBI\Table::select() function to define a fieldset.
            "criteria": {},                     //Optional. Sent to the DBI\Table::find() function to set query criteris.
            "map": {}                           //Optional. Data map object sent to DBI\Datamapper::map() to map fields.
            "ignoreErrors": false             //If true then if the remote data source is not available the sync will log the error and continue.  Default: false
        }
    }
]
```

Datasync files are used to make sure that required data exists in the database.  This can be for an initial installation that will create records in a database automatically as part of system initialisation.  These can also be used to ensure that any data that is referenced in code actually exists in the database.  Normally this is done to define types of things that have an identifier that will be referenced in code but linked to other entities in the database.

## Sync Container Array

This array simply contains sync stage objects that will be executed in the order they are defined in the array.  The data sync process can be broken up into separate stages that perform a unique operation.  Normally a single sync stage will define rows that will be synchonised into a single table.

See below for how to define *Sync Stage* objects.

## Sync Stage Objects

A sync stage object is simply a JSON object that describes what actions will be performed during this stage of the synchronisation process.

There are currently 5 actions that can be performed:

1. `message` - This will output a log message.
2. `exec` - This will execute custom SQL.
3. `rows` - Will synchronise rows in a table. _(Requires `table`)_
4. `update` - Will update existing rows in a table._(Requires `table`)_
5. `delete` - Will delete existing rows from a table. _(Requires `table`)_

These actions can be performed in a single sync stage, or combined to be executed during the same stage, however combining `exec` with `rows`, `update` or `delete` is not considered best practice and `exec` should normally be executed in it's own stage.  If they are combined, the order of execution is as listed above.

Combining `message` with `exec` and other actions is encouraged as it will output user-friendly log messages when executing custom SQL.

## Log Message Output

The simplest action that can be performed in a sync stage is to simply output a log message with the `message` property.  This can be any text that complies with the JSON data standard.

### Example

```json
{
    "message": "This is a log message"
}
```

## Execute Custom SQL

It is possible to execute custom SQL during a sync stage.  The SQL will be executed "as is" and it's return value is not processed in any way.  

The SQL can be defined as either a single SQL string statement, or multiple SQL statements in an _Array_.  This will execute all SQL statements one after the other in the order they are defined in the sync stage.

If an error occurs during execution of any custom SQL, an exception will be thrown and the data sync process will be stopped.

### Example - Single

```json
{
    "exec": "DELETE FROM my_table"
}
```

### Example - Multiple

```json
{
    "exec": [
        "DELETE FROM my_table",
        "DELETE FROM you_table"
    ]
}
```

### Using Variables

It is possible to include variabled in the SQL using mustache tags.  

> **Note**
> For details on how to define variables, see the section below on _Variables_.

#### Example

```json
[
    {
        "vars": {
            "TEST_DATA": 1234
        },
        "exec": "UPDATE test_table SET value={{TEST_DATA}} WHERE value IS NULL;"
    }
]
```

This will replace `{{TEST_DATA}}` with `1234` to produce the SQL `UPDATE test_table SET value=1234 WHERE value IS NULL;`.

## Row Sync Objects

Row sync objects are used with the `rows` property is defined in a sync stage object.  The `rows` property is an _Array_ of key/value pairs where the key is the column name, and the value is the value that will be set in the column of the row.

The power of synchronisation comes from detecting if the row currently exists based on certain criteria, then comparing the existing row to see if there are any differences before updating only the columns that are defined in the sync file.

### Existing Row Detection

The idea behind the data sync engine is to ensure that a row, as it is defined in the data sync file, exists in the database.  How existing rows are identified by the sync engine depends on the fields defined in the row objects as well as how the sync stage object is defined.

There are three operating modes described below in the order in which they are prioritised.

* *Primary key* - The row object has had the primary key value defined.
* *Key List* - A list of field names is defined whose values will be used to find existing records.
* *Object* - The entire object is used to find existing records.

> **Warning**
> It is possible to mix and match only *Primary Key* and *Object* modes as neither of these modes require keys to be defined.  However this is not recommended.

#### Primary Key Mode

This is the fastest and most reliable sync method.  If the primary key has been defined as one of the fields in the row object, then it's value will be used to find and existing record.  If there is no existing record, a new one will be inserted with the defined field values.  If an existing row is found, this record will be compared against the fields defined in the row object and any differences will be updated on the existing record.

> **Notice**
> If a database column is not defined in the data sync row object, it's value will not be changed.  If you want to set ensure such columns are empty, simply define them in the row object with a `null` value.

> **Warning**
> The caveat with using primary key mode, is that you need to define primary key in a record.  For small systems this should not be a problem, but for large systems with many data sync files, many developers and many records, it can become difficult to keep track of primary keys.  In these situations you can use *Key List Mode* along with *Row Object Field Macros* to link records.

##### Example

In the below example, `id` is the primary key field and so should be defined in each row object.

```json
[
    {
        "table": "test",
        "rows": [
            {
                "id": 1,
                "name": "one",
                "label": "Row Number #1"
            },
            {
                "id": 2,
                "name": "two",
                "label": "Row Number #2"
            }
        ]
    }
]
```

In the above example, the sync engine will look for each record where the `id` column contains the defined value.  If one doesn't exist, a new record will be inserted.  If one does exist, it will ensure that the `name` and `label` columns contain their defined values.  

#### Object Mode

Object mode is the slowest, but most simple mode.  Essentially it will use all the defined column values to find an existing record and only if no record exists will it insert a new record.  

> **Warning **
> Because the existing record lookup is done using all the of the defined data, updates are not possible.

##### Example

```json
[
    {
        "table": "test",
        "rows": [
            {
                "name": "one",
                "label": "Row Number #1"
            },
            {
                "name": "two",
                "label": "Row Number #2"
            }
        ]
    }
]
```

In this example, the sync engine will make sure that the defined rows exist in the database.  If they already exist, then nothing will be changed.

#### Key List Mode

Key list mode is basically a combination of the above two modes, hence why it is sometimes referred to a *hybrid mode*.  In this mode, instead of using the primary key to lookup records, the lookup keys are defined in the *Sync Stage Object*.

##### Example

```json
[
    {
        "table": "test",
        "keys": [ "name" ],
        "rows": [
            {
                "name": "one",
                "label": "Row Number #1"
            },
            {
                "name": "two",
                "label": "Row Number #2"
            }
        ]
    }
]
```

In the above example, the `name` column is used to find an existing record.  If one does not exist an insert is performed using the defined column values.  If a record does exist, it will ensure that the `label` field contains the defined value. 

> **Notice**
> It is a good idea to make sure a database index is defined for the columns used in the `keys` attribute.  This will greatly improve performance during data synchronisation.

## Row Object Macros

Row object macros make it possible to perform simple, optimised queries during the sync process that will lookup and return a value to be stored in the column.  These queries are designed to allow foreign key columns to lookup the reference values that should be stored in this record.

These macros are string field values that are prefixed with `::` and match a very specific pattern.  This means that we wont' interfer with columns values that legitimately contain a `::` prefix.

Macros are defined in the format `::source_table(source_column):field=value,field=value`.  Lookup criteria is comma separated and currently only `AND` criteria is supported.

> **Notice**
> Macros that are defined as the row data value and will be evaluated for that row item only.

##### Example

In the below example we have data for two tables. *test_type* and *test* which has a column named *type_id* that references the *test_type* table's *id* column.  In *test_type* the *id* column is a serial primary key.

You can see here that we have a simple macro defined in each field value for the *type_id* column that looks up *id* column of the *internal* record in the *test_type* table.

```json
[
    {
        "table": "test_type",
        "keys": [ "name" ],
        "rows": [
            {
                "name": "internal",
                "label": "Internal Test Type"
            }
        ]
    },
    {
        "table": "test",
        "keys": [ "name" ],
        "rows": [
            {
                "name": "one",
                "label": "Row Number #1",
                "type_id":  "::test_type(id):name=internal"
            },
            {
                "name": "two",
                "label": "Row Number #2",
                "type_id":  "::test_type(id):name=internal"
            }
        ]
    }
]
```

### Macro Optimisation

These queries are optimised to ensure they operate as quickly as possible.  If possible, an SQL query not be performed if the referenced value is defined anywhere in the loaded data sync files.  

This means that in the above example, the data sync engine already knows about a record in the *test_type* table with a name of *internal* and will use it's cached primary key as the reference value.  

If a query IS performed on the database, the results are cached for the life of the data sync process and will used immediately in subsequent lookup macros.  This means that in the above example, if a query was actually performed for the first row, that cached result would be used in the second row because the queries are the same.

## Table References

It is possible to define a single value that will be populated in EVERY row that is to be synchronised into a table.  This makes it simple to set common values that are shared between all rows in a row sync object.

Table references are defined as JSON objects where the property name is the name of the target column and the property value is the value to be used in each row.

Using the table references it is possible to obtain the value using a macro.  This value will then be used in every row of the row sync object.  These types of references are quite efficient as they are resolved at the beginning of table processing and do not change throughout the execution of the data sync object.

> **Notice**
> These table references can be used in the `keys` property as well so that the resulting values can be used as reference keys.

### Example - Basic

An example could be to group contact item types such as email addresses, physical addresses or phone numbers.

```json
[
    {
        "table": "contact_types",
        "keys": [ "group_name", "type_name" ],
        "refs": {
            "group_name": "email"
        },
        "rows": [
            {
                "type_name": "Personal Email"
            },
            {
                "type_name": "Business Email"
            }
        ]
    },
    {
        "table": "contact_types",
        "keys": [ "group_name", "type_name" ],
        "refs": {
            "group_name": "phone"
        },
        "rows": [
            {
                "type_name": "Home Phone"
            },
            {
                "type_name": "Work Phone"
            },
            {
                "type_name": "Mobile Phone"
            }
        ]
    },
    {
        "table": "contact_types",
        "keys": [ "group_name", "type_name" ],
        "refs": {
            "group_name": "email"
        },
        "rows": [
            {
                "type_name": "Street Address"
            },
            {
                "type_name": "Postal Address"
            }
        ]
    }
]
```

### Example - Using Macros

In this example we set the reference value _type_id_ to the _id_ value from the _items_ table where the _group_name=email_ and _name=home_.

```json
[
    {
        "table": "items",
        "keys": [ "type_id", "type_name" ],
        "refs": {
            "type_id": "::item_types(id):group_name=email,name=home"
        },
        "rows": [
            {
                "item_name": "Test Item #1"
            },
            {
                "item_name": "Test Item #2"
            }
        ]
    }
]
```

## Stage Variables

Variables can be defined on a sync stage to allow easier manipulation of data in that stage.  You might want to do this if you have column data that you want to define once, and use in multiple rows.  Then later if you need to change that data, you only need to change it in once place.

Once a variable is defined, it can be used in data using **mustache tags**. For exmaple: `{{my_variable}}`.

#### Example

```json
[
    {
        "table": "my_table",
        "vars": {
            "TEST_DATA": 1234
        },
        "rows": [
            {
                "name": "Test Row #1",
                "value": "{{TEST_DATA}}"
            },
            {
                "name": "Test Row #2",
                "value": "{{TEST_DATA}}"
            },
            {
                "name": "Test Row #3",
                "value": "{{TEST_DATA}}"
            }
        ]
    }
]

```

### Macros Variables

Variables can be defined and used in macros as a way of using multiple macros to find existing rows.  Say for example, you have a row that references another table where there are also multiple references.  Normally if you were writing SQL you would use a join, but macros are only able to reference a single table.  Instead, we can execute one macro to obtain the first value, then use that in a second macro to simulate the same affect as a table join.

Once a variable is defined, it can be used in a macro using **mustache tags**. For exmaple: `{{my_variable}}`.

### Example

In this example we create a variable that references the *main_type* table and gets the _id_ for a row that has the name _default_ and version _2_.  This variable is then used to find a sub_type of the main type with the name _init_.

> **Notice**
> This example is the reason this feature was created.  The use-case was there were multiple main types which had a list of sub-types that could have the same names, making it impossible to use macros to look up these row ids.

```json
[
    {
        "table": "my_table",
        "vars": {
            "main_type_id": "main_type(id):name=default,version=2"
        },
        "refs": {
            "sub_type_id": "sub_type(id):main_type_id={{main_type_id}},name=init"
        },
        "rows": [
            {
                "name": "Row #1"
            },
            {
                "name": "Row #2"
            },
            {
                "name": "Row #3",
                "other_field": "other_type(id):main_type_id={{main_type_id}},name=test"
            }
        ]
    }
]
```

## Remote Data Sync

It is possible to use a remote data source to sync data from.  This can be either an external database server directly, or another web host that runs Hazaar/DBI and has been configured with a secure data sync key.

### Direct Database Sync

This can be used if the data sync source is directly accessible by the application.  If the database is not directly accessible, see the next second on "DBI Database Sync".

To use direct database sync you can either define the DBI configuration in the sync file directly, or use a named configuration.  

> **Warning** 
> It is recommended to define connection properties directly in a data sync file.  Instead, use a named configuration which which is stored in a secure DBI configuration file.

#### Example

```json
[
    {
        "table": "local_table",           //Local target table
        "source": {
            "config": "remote_database",  //DBI named configuration
            "table": "remote_table",      //Source table on remote
            "criteria": {                 //Query criteria
                "active": true
            },
            "ignoreErrors": true          //Continue if there's an error
        }
    }
]
```

### DBI Database Sync

It is possible to route the data sync via another Hazaar/DBI application.  This is because it is not always possible or safe to make a database server directly accessible.

> **Notice**
> A syncKey **MUST** be configured in the source application DBI configuration for this to be enabled.

> **Danger**
> While it is possible to set the syncKey property in the data sync file directly, this is definitely **NOT RECOMMENDED** and has only been added to ease the development process.

#### Example

```json
[
    {
        "table": "local_table",                    //Local target table
        "source": {
            "hostURL": "http://remote.server.com", //Remote host URL.  This MUST be a Hazaar/DBI application base path.
            "config": "remote_database",           //A named configuration directive that must exist on both sides and contain a syncKey.
            "table": "remote_table",               //Source table on remote
            "criteria": {                          //Query criteria
                "active": true
            }
        }
    }
]
```
