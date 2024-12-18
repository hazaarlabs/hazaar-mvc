# Application Models

::: danger
This page is tagged as a draft and is a work in progress.  It is not yet complete and may contain errors or inaccuracies.
:::

Hazaar provides a handy application model class to help sanitise data and provide a consistent interface to your data.

## Defining a Model

To create a model you need to define a class that extends the `Hazaar\Model` class.  This class provides a number of methods to help you interact with your data.

Properties are defined as protected variables in the class and are accessed using the `__get` and `__set` magic methods.  This allows the model to provide hooks for data validation and sanitisation.

Public and private properties are allowed but will not be validated or sanitised.  Public properties can be accessed directly but private properties can only be accessed by the implementing class.

```php
<?php
class MyModel extends Hazaar\Model {
     
    /**
     * This public property can be accessed directly but will not be validated or sanitised.
     */
    public $id; 

    /**
     * These protected properties will be validated and sanitised by the model class.
     */
    protected $name; 
    protected $description;

    /**
     * This private property is only accessible by the class itself.
     */
    private $counter = 0; 

    public function construct(){
        $this->defineEventHook('read', 'name', function($value){
            return $value . '!';
        });
    }

}
```

## Using a Model

To use a model you need to create an instance of it and then you can set and get values from it.

```php
<?php
$model = new MyModel();
```

## Methods

### `get($name)`

Get a property value from the model.  Can also be accessed as a property.

```php
$model->name;
```

### `set($name, $value)`

Set a property value on the model.  Can also be set as a property.

```php
$model->name = 'John';
```

### `toArray()`

Convert the model to an array.

```php
$array = $model->toArray();
```

### `defineEventHook($event, $property, $callback)`

Define an event hook for a property.  The event can be `read`, `write` or `validate`.

```php
$model->defineEventHook('read', 'name', function($value){
    return $value . '!';
});
```

### `defineRule($property, $rule, ...$args)`

Define a validation rule for a property.

```php
$model->defineRule('name', 'required');
```

See below for a list of available rules.

## Validation Rules

The model class provides a number of validation rules that can be applied to properties.  These rules are defined using the `defineRule` method.

Some rules will throw an exception if the rule is not met.  These exceptions are instances of `Hazaar\Exception\ValidationFailed`.  Other rules will modify the value of the property (for example, the `pad` rule).

### `Model::defineRule('required', {key})`

The property must have a value.  Throws an exception if the property is not set.

### `Model::defineRule('min', {key}, {minvalue})`

An integer property must be greater than or equal to the value.  Ensures the integer is not less than the value.

### `Model::defineRule('max', {key}, {maxvalue})`

An integer property must be less than or equal to the value. Ensures the integer is not greater than the value.

### `Model::defineRule('range', {key}, {minvalue}, {maxvalue})`

An integer property must be within the range of the two values.  Ensures the integer is not less than the minimum value or greater than the maximum value.

### `Model::defineRule('minlength', {key}, {minlength})`

A string property must be longer than or equal to the value. Throws an exception if the string is shorter than the value.

### `Model::defineRule('maxlength', {key}, {maxlength})`

A string property must be shorter than or equal to the value. Throws an exception if the string is longer than the value.

### `Model::defineRule('pad', {key}, {length}, {padchar}, {padtype})`

A string property will be padded to the length of the value. The pad character and type can be specified.  The pad type can be `STR_PAD_LEFT`, `STR_PAD_RIGHT` or `STR_PAD_BOTH`.

### `Model::defineRule('filter', {key}, {filter})`

A string property will be filtered using `filter_var()`.  See this list of available filters: [http://php.net/manual/en/filter.filters.php](http://php.net/manual/en/filter.filters.php)

### `Model::defineRule('contains', {key}, {needle})`

An array property must contain the value.  Throws an exception if the value is not in the array.

### `Model::defineRule('custom', {key}, {callback})`

A custom validation rule.  The callback function should return `true` if the value is valid or `false` if it is not.