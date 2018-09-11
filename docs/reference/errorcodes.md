# Error Codes

This section is far from complete. One of the features of HazaarMVC that is a little lacking is it's error handling support. Errors are handled quite gracefully, but a simple Exception object is thrown with a descriptive message, rather than a specific exception class. Error handling in HazaarMVC is about to undergo some major changes and this section may no longer be relevant. For now it is his for historic purposes.

## Configuration Errors

### 10001 - A required module in not installed

Install the module. For example if you need to install gd, on a debian-based system you can just run:

```
apt-get install php5-gd
```

Alternatively you can use pecl. To install the MongoDB module run:

```
pecl install mongo
```

## Bootstrap Errors

Coming at some point...

## Runtime Errors

### 10400 - Error loop detected

An error or exception has been thrown, then while trying to output the error another error was thrown. Instead of getting an endless loop, you will see this error. This should never happen so if it does, contact support to report a bug.

### 10404 - File not found

### 10405 - Method not found

A method being called on a view could not be found. Check that the method exists and/or that the view helper has been loaded.

## Miscellaneous Errors
