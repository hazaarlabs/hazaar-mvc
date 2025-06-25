# Validation

Hazaar provides a powerful, fluent validation system through the [`Assert`](/api/class/Hazaar/Validation/Assert.md) class. This validation system allows you to chain multiple validation rules together in a readable and maintainable way, making it easy to validate data before processing it in your application.

## Overview

Data validation is a critical part of any application, ensuring that the data you work with is correct, safe, and as expected. Hazaar's validation system is designed to be both expressive and flexible, allowing you to write clear, maintainable validation logic. By using a fluent interface, you can chain multiple rules together, making your validation code easy to read and reason about. This system helps prevent bugs, security issues, and unexpected behavior by catching invalid data early in your application's workflow.

The validation system supports two modes of operation:
- **Immediate Mode** (default): Throws exceptions as soon as a validation fails, making it ideal for simple, single-value checks where you want to fail fast.
- **Lazy Mode**: Collects all validation errors before throwing an exception, which is useful when validating complex objects or forms and you want to present all errors to the user at once.

## Basic Usage

To start validating a value, use the static `that()` method. You can chain as many validation rules as you need. If any rule fails, an exception is thrown (immediate mode) or collected (lazy mode).

```php
use Hazaar\Validation\Assert;

// Simple validation: checks if $email is a string and a valid email address
Assert::that($email)->string()->email();

// Multiple validations: checks if $age is an integer between 18 and 100
Assert::that($age)
    ->integer()
    ->between(18, 100);
```

## Validation Modes

Validation can be performed in two modes, depending on your needs:

### Immediate Mode

Immediate mode is the default. In this mode, as soon as a validation rule fails, an [`InvalidArgumentException`](https://www.php.net/manual/en/class.invalidargumentexception.php) is thrown. This is useful for validating single values or when you want to stop processing at the first error.

```php
// This will throw an exception immediately if the email is invalid
Assert::that($email)
    ->notEmpty()   // Ensures the value is not empty
    ->string()     // Ensures the value is a string
    ->email();     // Ensures the value is a valid email address
```

Use immediate mode when you want to fail fast and only care about the first error encountered.

### Lazy Mode

Lazy mode is useful when you want to validate multiple fields or an entire object and collect all errors before handling them. Instead of throwing an exception immediately, all validation errors are collected and thrown together as a [`ValidationException`](/api/class/Hazaar/Validation/ValidationException.md) when you call `verify()`.

```php
// This will collect all validation errors and throw them together
Assert::that($user)
    ->lazy()       // Switch to lazy mode
    ->notEmpty()   // Ensures the user object is not empty
    ->object()     // Ensures the value is an object
    ->verify();    // Throws ValidationException if any validations failed
```

Lazy mode is ideal for validating forms or data structures where you want to show the user all issues at once.

## Available Validations

Hazaar provides a wide range of built-in validation methods. You can chain these methods to build complex validation logic. Here are some of the most commonly used validations:

### Type Validations
Type validations ensure that a value is of a specific PHP type. These are often used as the first step in a validation chain.
```php
Assert::that($value)
    ->string()    // Validates that the value is a string
    ->integer()   // Validates that the value is an integer
    ->float()     // Validates that the value is a float
    ->boolean()   // Validates that the value is a boolean
    ->numeric()   // Validates that the value is numeric (string or number)
    ->scalar()    // Validates that the value is a scalar type
    ->array()     // Validates that the value is an array
    ->object();   // Validates that the value is an object
```

### String Validations
String validations check properties of string values, such as length or format.
```php
Assert::that($value)
    ->minLength(5)        // Ensures the string is at least 5 characters long
    ->maxLength(100)      // Ensures the string is no more than 100 characters
    ->matchesRegex('/pattern/'); // Ensures the string matches the given regular expression
```

### Numeric Validations
Numeric validations are used to check the value of numbers, such as minimum, maximum, or range.
```php
Assert::that($value)
    ->min(0)             // Ensures the value is at least 0
    ->max(100)           // Ensures the value is no more than 100
    ->between(1, 10);    // Ensures the value is between 1 and 10 (inclusive)
```

### Internet Validations
Internet validations are useful for checking common internet-related formats.
```php
Assert::that($value)
    ->email()    // Validates that the value is a valid email address
    ->url()      // Validates that the value is a valid URL
    ->ip()       // Validates that the value is a valid IP address (v4 or v6)
    ->ipv4()     // Validates that the value is a valid IPv4 address
    ->ipv6();    // Validates that the value is a valid IPv6 address
```

### Collection Validations
Collection validations check if a value exists within or outside a given set.
```php
Assert::that($value)
    ->in(['apple', 'banana', 'orange'])     // Ensures the value is one of the allowed options
    ->notIn(['restricted', 'banned']);      // Ensures the value is not one of the disallowed options
```

## Error Handling

When validation fails, Hazaar throws exceptions to help you handle errors gracefully. There are two main types of exceptions:

- [`InvalidArgumentException`](https://www.php.net/manual/en/class.invalidargumentexception.php) - Thrown in immediate mode when the first validation fails.
- [`ValidationException`](/api/class/Hazaar/Validation/ValidationException.md) - Thrown in lazy mode, containing all collected errors.

You can catch these exceptions to display user-friendly error messages or log issues. The `ValidationException` contains all error messages, usually separated by newlines, which you can process as needed.

Example of handling validation errors in lazy mode:

```php
try {
    Assert::that($user)
        ->lazy()
        ->notEmpty()
        ->object()
        ->verify();
} catch (ValidationException $e) {
    // Handle multiple validation errors
    $errors = explode("\n", $e->getMessage());
    foreach ($errors as $error) {
        // Log or display each error
    }
}
```

## Best Practices

1. **Use lazy validation for complex data:** When validating multiple fields or objects, lazy mode helps you collect and display all errors at once, improving user experience.
2. **Use immediate validation for simple checks:** For single values or quick checks, immediate mode is faster and simpler.
3. **Provide custom error messages:** Custom messages make it easier for users to understand what went wrong and how to fix it.
4. **Chain related validations:** Grouping related rules together improves readability and maintainability.
5. **Validate types first:** Always check the type of a value before applying more specific validations to avoid unexpected errors.

## Complete Example

The following example demonstrates how to validate a user registration form using both type and value validations, and how to handle multiple errors at once:

```php
use Hazaar\Validation\Assert;

// User registration validation
try {
    // Validate the user data object
    Assert::that($userData)
        ->lazy()
        ->object()
        ->notEmpty();

    // Validate the email field
    Assert::that($userData->email)
        ->lazy()
        ->string()
        ->email()
        ->maxLength(255);

    // Validate the age field
    Assert::that($userData->age)
        ->lazy()
        ->integer()
        ->between(18, 120);

    // Validate the role field
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

This approach ensures that all validation errors are collected and can be presented to the user in a clear, actionable way, making your application more robust and user-friendly.
