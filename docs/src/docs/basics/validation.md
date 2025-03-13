# Validation

Hazaar provides a powerful, fluent validation system through the [`Assert`](/api/class/Hazaar/Validation/Assert.md) class. This validation system allows you to chain multiple validation rules together in a readable and maintainable way, making it easy to validate data before processing it in your application.

## Overview

The validation system supports two modes of operation:
- **Immediate Mode** (default): Throws exceptions as soon as a validation fails
- **Lazy Mode**: Collects all validation errors before throwing an exception

## Basic Usage

To start validating a value, use the static `that()` method:

```php
use Hazaar\Validation\Assert;

// Simple validation
Assert::that($email)->string()->email();

// Multiple validations
Assert::that($age)
    ->integer()
    ->between(18, 100);
```

## Validation Modes

### Immediate Mode

In immediate mode (default), an `InvalidArgumentException` is thrown as soon as a validation fails:

```php
// This will throw an exception immediately if the email is invalid
Assert::that($email)
    ->notEmpty()
    ->string()
    ->email();
```

### Lazy Mode

Lazy mode collects all validation errors before throwing a [`ValidationException`](/api/class/Hazaar/Validation/ValidationException.md):

```php
// This will collect all validation errors
Assert::that($user)
    ->lazy()
    ->notEmpty()
    ->object()
    ->verify(); // Throws ValidationException if any validations failed
```

## Available Validations

### Type Validations
```php
Assert::that($value)
    ->string()    // Validates string type
    ->integer()   // Validates integer type
    ->float()     // Validates float type
    ->boolean()   // Validates boolean type
    ->numeric()   // Validates numeric values (strings or numbers)
    ->scalar()    // Validates scalar types
    ->array()     // Validates array type
    ->object();   // Validates object type
```

### String Validations
```php
Assert::that($value)
    ->minLength(5)        // Minimum string length
    ->maxLength(100)      // Maximum string length
    ->matchesRegex('/pattern/'); // Matches regular expression
```

### Numeric Validations
```php
Assert::that($value)
    ->min(0)             // Minimum value
    ->max(100)           // Maximum value
    ->between(1, 10);    // Value between range (inclusive)
```

### Internet Validations
```php
Assert::that($value)
    ->email()    // Validates email address
    ->url()      // Validates URL
    ->ip()       // Validates IP address (v4 or v6)
    ->ipv4()     // Validates IPv4 address
    ->ipv6();    // Validates IPv6 address
```

### Collection Validations
```php
Assert::that($value)
    ->in(['apple', 'banana', 'orange'])     // Value must be in array
    ->notIn(['restricted', 'banned']);       // Value must not be in array
```

## Error Handling

When validation fails, two types of exceptions can be thrown:

- [`InvalidArgumentException`](https://www.php.net/manual/en/class.invalidargumentexception.php) - Thrown in immediate mode
- [`ValidationException`](/api/class/Hazaar/Validation/ValidationException.md) - Thrown in lazy mode with all collected errors

Example of handling validation errors:

```php
try {
    Assert::that($user)
        ->lazy()
        ->notEmpty()
        ->object()
        ->verify();
} catch (ValidationException $e) {
    // Handle multiple validation errors
    echo $e->getMessage(); // Contains all errors separated by newlines
}
```

## Best Practices

1. Use lazy validation when validating multiple fields that could have multiple errors
2. Use immediate validation for simple, single-value validations
3. Provide custom error messages for better error reporting
4. Chain related validations together for better readability
5. Use type validations before specific validations

## Complete Example

Here's a comprehensive example showing various validation scenarios:

```php
use Hazaar\Validation\Assert;

// User registration validation
try {
    Assert::that($userData)
        ->lazy()
        ->object()
        ->notEmpty();

    Assert::that($userData->email)
        ->lazy()
        ->string()
        ->email()
        ->maxLength(255);

    Assert::that($userData->age)
        ->lazy()
        ->integer()
        ->between(18, 120);

    Assert::that($userData->role)
        ->lazy()
        ->string()
        ->in(['user', 'admin', 'moderator'])
        ->verify();
} catch (ValidationException $e) {
    // Handle all validation errors at once
    $errors = explode("\n", $e->getMessage());
    foreach ($errors as $error) {
        // Log or display each error
    }
}
```
