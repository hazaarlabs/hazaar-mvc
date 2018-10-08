# Money

Hazaar has a few tricks up it's sleeve when it comes to money and currencies. Here's a few things that Hazaar's Money class can help you do:

* Output money values in your current region's money format
* Convert money values from one currency to another
* Add two or more money objects together that have different currencies.

Sounds pretty cool, huh?

## How does it work?

Well, everything is done automatically, but there's a lot going on in the background. For example, to output the correct money format the money classes uses a local database of all countries and their currency information to generate the correct format. All you need to know is the currency name, or the 2 digit abbreviated country code and Hazaar's Money class will do the rest.

### How does it convert values between currencies?
In the background the currency class is talking to Yahoo's financial servers to get the latest currency conversion rates. It then caches them using a Hazaar\Cache object with the file backend (by default) so that currency conversion in formation can be shared between sessions. This information expires after 30 minutes so you can be assured of having fresh data without causing too much lag during the rate lookup.
Here's a few examples of how to use the Money class. All these examples use the Money view helper which simply returns a new Money object.

## Getting Set Up

For the Money class to work correctly, it needs to know your default currency. There are a few ways to tell it this. See Setting your locale for details on how to do this correctly for your situation.

You can also set the default currency explicitly in your bootstrap.php file of your application by adding the following line of code.

```php
Hazaar\Money::$default_currency = 'AUD';
```

Hazaar will attempt to determine the default currency based on the locale but if it can not, then the default currency will beAUD.

## Example Usage

### Displaying Money Values

This will simply display the value in current application locales currency.

```php
<?=$this->money(1000);?>
```

You can also specify the type of currency that you are working with by setting the currency argument.

```php
<?=$this->money(1000, 'AUD');?>
```

### Converting Currencies

This will output the current AUD value as USD.

```php
<?=$this->money(1000, 'AUD')->convertTo('USD');?>
```

### Adding Currencies

It's also possible to add two currency objects together, even if they are not of the same currency type.

```php
<?=$this->money(1000, 'AUD')->add($this->money(1000, 'USD'));?>
```

### Subtracting Currencies

The same goes for subtracting currencies.

```php
<?=$this->money(1000, 'AUD')->subtract($this->money(1000, 'USD'));?>
```

## More Information

See the API documentation for Hazaar\Money for more currency methods.