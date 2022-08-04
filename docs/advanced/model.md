# Strict Models

Strict models are models that extend the Hazaar\Model\Strict class and allow some built-in control over your data structure. Developers are able to define the data structure in the class and HazaarMVC will maintain strict data typing, validate and call appropriate callbacks on reads or update.

## Getting Started

Using strict models is very easy. Just create your model as usual, but extend the Hazaar\Model\Strict abstract class. In the class definition you simply define a public/protected method called init() which is called by the constructor and is used to return your field definition. The field definition is an array where the key is the name of the field and the value is either the name of the data type (simple definition) an array of definition parameters (complex definition) or even a combination of the two (hybrid definitions). All definition parameters are optional.

## Simple Field Definitions

Simple definitions are just a flat array of key/value pairs with the key as the name of the field, and the value as the data type you would like the field to be.

```php
class MyStrictModel extends \Hazaar\Model\Strict {
    
    public function init(){ 
        $fields = [
            'mystring' => 'string',
            'myint' => 'integer'
        ];
        return $fields;
    }
}
```

## Complex Field Definitions

Complex definitions are more detailed and allow for the full functionality of strict models to be used. These definitions are defined in a multi-dimensional array with the key being the name of the field and the value being an array of definition parameters.

### Possible definition parameters

* `type` - Defines the data type of the field. Possible types are string, integer, array, boolean, double, object, resource andNULL. Under the hood we use the PHP gettype() function to check/change the variable type so any types that it supports can be used. If not specified, no strict typing will be enforced.
* `default` - The default value for the variable. If not specified, the default will be null.
* `validate` - The field validation data to use when updating the field. See the section on validation for possible values.
* `update` - Defines the callbacks to be executed before (pre) and/or after (post) the field is updated. See the section on callbacks for more info.
* `read` - Callback to be called before a value is read. This allows the data to be mangled on the way out or some other action performed.

```php
class MyStrictModel extends \Hazaar\Model\Strict {
    public function init(){
        $fields = [
            'mystr' => [
                'type' => 'string',
                'default' => 'myteststring',
                'validate' => ['with' => '/test/']
            ],
            'mynum' => [
                'type' => 'integer',
                'default' => 300,
                'validate' => ['min' => 100, 'max' => 500]
            ],
            'mybool' => [
                'type' => 'boolean',
                'default' => false
            ]
        ];
        return $fields;
    }
     
}
```

In this example we have defined three fields. mystr, mynum and mybool.

* `mystr` is a string that defaults to the value 'myteststring' and has a regular expression validator to ensure it always contains the word "test".
* `mynum` is an integer that defaults to 300 and has two validators to ensure it is always a minimum of 100 and a maximum of 500.
* `mybool` is a boolean field that defaults to false.

## Validators

Validators are used to restrict the possible values allowed in a field. By default the model will throw an exception if an invalid value is added. It is possible however, to turn on silent mode which will not throw an exception and simply just not update the value.

Possible validators are:

* `min` - Validates integers which must be of the defined minimum value.
* `max` - Validates integers which must be of the defined maximum value.
* `with` - Validated strings with the defined regular expression.
* `equals` - Validates any value with a strict type value comparison (===).
* `minlen` - Validates a string to ensure it is of a minimum length of characters.
* `maxlen` - Validates a string to ensure it is no more than a maximum length of characters.

The complex definition example above shows how to use these.

## Using Callbacks

Callbacks are set up in the field definition defined in the init() call and can be defined in a number of ways. For short functions you can use a PHP Closure which is the PHP way of doing an anonymous function. For longer, more in-depth functions, you can create a function or public/protected method in your model and refer to it using a callback reference. Both will function exactly the same and it's just up to a code style thing. Anonymous functions will quickly complicate your field definition andinit() method so you may want to move larger functions into a method, for example.

Callbacks are executed inside the Hazaar\Model\Strict object context and therefore your callback function will need to be accessible by that object. Seeing as you are extending the Hazaar\Model\Strict class you will need to define a method as public or protected if you are going to use an object method.

There are 3 callbacks that can be used:

* `update['pre']` - This callback will be called BEFORE the value is updated. It allows the model to modify the value before it is updated. User MUST return a value which is the value that will be stored.
* `update['post']` - This callback is called AFTER the value has been updated. Nothing needs to be returned as the value is already updated.
* `read` - This callback is called BEFORE a value is read from the model.

It's not recommended that these callbacks be used for updating databases unless you really know that is what you want. Callbacks are executed on each update/read and so you will end up performing many queries. This may be what you want, but it is not recommended as it can have a significant import on performance.

You could use the callbacks to track changes to values, then you could use the shutdown() method to write any pending updates to the database, or you could implement your own update function.

```php
public function init(){
    $fields = [
        'mystr' => [
            'update' => [
                'pre' => function($value, $key){
                    //do your stuff here.  
                    //remember to return the new value.
                    return $value
                },
                'post'] => function($value, $key){
                    //do your stuff here.  
                    //no need to return anything as the update is already done
                }
            ),
            'read' => [$this, 'mangleData']
        ]
    ];
    return $fields;
}
protected function mangleData($value, $key){
    //do your stuff here.  remember to return the value.  
    //this allows you to modify the value as it's being read.
    return $value
}
```

As you can see, a field definition can quickly become complicated if you have many callbacks. To get around this, in the example, we defined the read callback as a callback reference to the mangleData method of our model class.

Callbacks that return a value must always return a value. Failure to return a value will result in a null value being used. That is, if you have a pre-update callback and it returns nothing, or has not return call, then the value will be set to null.

## Optional Methods

### construct()

Because a lot of the time you will have to prepare/populate the model before it can be used, you can define a construct() method. You should not define your own ___construct()_ method like you would normally for a new class. Instead, you can use the construct() method (no dashes prefix) which is called after the model has been prepared. Any parameters passed to the object at the time of instantiation are passed to this method. To prevent the user from accidentally overriding the default constructor it has been declared with the final keyword so any attempts to do so will result in an exception.

```php
class MyStrictModel extends \Hazaar\Model\Strict {

    private $db;
    public function init(){ 
        $fields = [
            '_id' => '\MongoId',
            'name' => 'string',
            'age' => 'int',
            'dob' => '\Hazaar\Date'
        ];
        return $fields;
    }
    public function construct($username){
        $this->db = new \Hazaar\Db\MongoDB();
        $this->populate($this->db->users->findOne(['name' => $username]);
    }
}
$model = new MyStrictModel('myusername');
```

In this example, the model is defined and a string value of 'test' is passed to the constructor. The model is initialised and then the construct() method is called with the string value as it's parameter. In this case, the string is tested for a value of 'test', which is it's value and so validates true and the program will exit.

### shutdown()

The shutdown method is called just before the destructor and is used to allow developers to perform some cleanup tasks. This is an ideal place to do some database updates.

```php
public function shutdown(){
    $this->db->users->update(['name' => $this->name], $this->to[));
}
```