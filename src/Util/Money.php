<?php

declare(strict_types=1);

/**
 * @file        Hazaar/Money.php
 *
 * @author      Jamie Carl <jamie@hazaar.io>
 * @copyright   Copyright (c) 2012 Jamie Carl (http://www.hazaar.io)
 */

namespace Hazaar\Util;

use Hazaar\Application\FilePath;
use Hazaar\Cache\Adapter;
use Hazaar\File;
use Hazaar\File\BTree;
use Hazaar\Loader;

/**
 * Money class
 *
 * This class is used to extend a normal integer value by adding currency related features such as the
 * currency type (AUD, USD, JPY, etc) and realtime currency conversion using Yahoo Quotes.
 *
 * ### Example
 *
 * ```php
 * $aud = new Money(500, 'AUD');
 * $usd = new Money(200, 'USD');
 * $total = $aud->add($usd);
 * ```
 *
 * The default money format is '%.2n' which will format the value to whole dollar with 2 decimal places. ie:
 * $123.45. You can specify the format when retrieving the amount (see self::format()) or you can set the
 * default format at any time.
 *
 * You can also set the default currency code to use when none is specified.
 *
 * It is recommended that these be set in your bootstrap file so that they are consistent across the whole
 * application.
 *
 * ### Example bootstrap.php
 *
 * ```php
 * Hazaar\self::$defaultCurrency = 'AUD';
 * ```
 */
class Money
{
    /**
     * Default currency code.
     */
    public static ?string $defaultCurrency = null;

    /**
     * @private
     */
    private float $value;

    /**
     * @var array<string, mixed>
     */
    private array $localCurrency;
    private static ?BTree $db = null;

    /**
     * @var array<string, mixed>
     */
    private static array $exchangeRates = [];
    private static ?Adapter $cache = null;

    /**
     * @detail      The money class constructors takes two parameters.  The value of the currency and the type of
     *              currency the value is representative of.
     *
     *              Currency info is loaded from a built-in support file named countryInfo.txt.  This file contains
     *              country information including country codes, currency names, etc.  The first time a currency is
     *              used this information is loaded into memory only once and is shared between all currency objects.
     *
     *              A cache object is also set up for use by the exchange conversion methods.  It will attempt to use
     *              the APC cache backend but if that is not available it will fall back to the file backend.
     *
     * @param float  $value    The currency value amount
     * @param string $currency The name of the currency or country of origin.  Ie: 'USD' and 'US' will both resolve
     *                         to US dollars.
     */
    public function __construct(float $value = 0, ?string $currency = null)
    {
        if (null === self::$defaultCurrency) {
            self::$defaultCurrency = trim(localeconv()['int_curr_symbol'] ?? '');
        }
        if (null === $currency) {
            $currency = self::$defaultCurrency;
        }
        $this->set($value, $currency);
        $this->localCurrency = $this->getCurrencyInfo($currency);
    }

    /**
     * @detail      This magic function is called when PHP tries to automatically convert the currency to a string.
     *              Simply calls the self::toString() method.
     *
     * @return string eg: $100USD
     */
    public function __toString(): string
    {
        return $this->toString();
    }

    /**
     * Retrieves information about a currency.
     *
     * @param null|string $currency The currency code. If null, returns information about all currencies.
     *
     * @return mixed returns an array of currency information if $currency is null, otherwise returns the information for the specified currency
     */
    public function getCurrencyInfo(?string $currency = null): mixed
    {
        if (null == self::$db) {
            $file = new File(Loader::getFilePath(FilePath::SUPPORT, 'currency.db'));
            self::$db = new BTree($file, true);
        }
        if (null === $currency) {
            $currencies = self::$db->toArray();
            unset($currencies['__version__']);

            return $currencies;
        }
        if (3 !== strlen($currency)) {
            $currency = $this->getCurrencyCode($currency);
        }

        return self::$db->get($currency);
    }

    /**
     * @detail      Get either the default currency code, or get a currency code for a country.  Use this instead of
     *              accessing self::defaultCurrency directly because if self::$defaultCurrency is not set, this will
     *              try and determine the default currency and set it automatically which will occur when a new Money
     *              object is created.
     *
     * @return string a valid currency code such as AUD, USD, JPY, etc
     */
    public function getCurrencyCode(?string $code = null): string
    {
        // If there is no currency set, get the default currency
        if (!$code) {
            $code = self::$defaultCurrency;
        }
        // If there is no default currency, look it up and set it now
        if (!$code) {
            // Get the current locale country code and use that to look up the currency code
            if (preg_match('/^\w\w[_-](\w\w)/', setlocale(LC_MONETARY, '0'), $matches)) {
                $code = $matches[1];
            } else {
                /**
                 * The absolute fallback default is AUD.
                 */
                $code = 'AUD';
            }
        }
        if (2 === strlen($code)) {
            $code = $this->getCode($code);
        }
        if (!self::$defaultCurrency) {
            self::$defaultCurrency = $code;
        }

        return strtoupper($code);
    }

    /**
     * @detail      Get the currency code for the current currency object or look up the currency code for a country.
     *              This value is normalised during object instantiation.  This means that if you specify a country upon
     *              instantiation, this will still return the correct currency code.
     *
     *              Optionally, if a country parameter is specified, this method can be used to look up the currency code
     *              for that country.
     *
     *              ### Example
     *
     *              ```php
     *              echo $currency->getCode('au');  //This will echo the string 'AUD'.
     *              ```
     *
     * @param string $country optional country code to look up a currency code for
     *
     * @return string the currency code requested
     */
    public function getCode(?string $country = null): string
    {
        if ($country) {
            if (strlen($country) < 3) {
                $countries = self::$db->range("\x00", "\xff");
                foreach ($countries as $c) {
                    if (0 !== strcasecmp($c['iso'], $country)) {
                        continue;
                    }
                    $country = $c['currencycode'];

                    break;
                }
            }
            $info = self::$db->get($country);

            return $info['currencycode'] ?? '';
        }

        return $this->localCurrency['currencycode'] ?? '';
    }

    /**
     * @detail      Get the symbol for the current currency.  The currency symbol is usually prefixed to the currency
     *              amount.  This method doesn't actually return the currency symbol as such, but will return the HTML
     *              entity name of the currency symbol, for example 'dollar', 'pound', 'yen', etc.
     *
     * @return string the HTML entity name of the currency symbol
     */
    public function getCurrencySymbol(): string
    {
        return $this->localCurrency['symbol'] ?? '$';
    }

    /**
     * @detail      Get the current exchange rate for the currency value against a foreign currency.  This method uses
     *              the Yahoo Quotes service to get the current exchange rate.  For this method to work your host needs
     *              to have web access (ie: port 80).  This should 'just work' for all but a small number of cases.
     *
     *              Because this method contacts another web service the response can be a little slow.  Because of this
     *              results are cached so that subsequent requests for the same conversion will be faster.
     *
     * @param string $foreignCurrency the foreign currency to get an exchange rate for
     *
     * @return float the current currency exchange rate
     */
    public function getExchangeRate(string $foreignCurrency): float
    {
        if (2 == strlen($foreignCurrency)) {
            $foreignCurrency = $this->getCode($foreignCurrency);
        }
        if (0 == strcasecmp($this->localCurrency['currencycode'], $foreignCurrency)) {
            return 1;
        }
        $base = strtoupper(trim($this->localCurrency['currencycode'] ?? ''));
        $foreignCurrency = strtoupper(trim($foreignCurrency));
        if (!(self::$exchangeRates[$base] ?? false)) {
            if (null === self::$cache) {
                self::$cache = new Adapter(['apc', 'file']);
            }
            $key = 'exchange:rate:'.$base;
            if (!(self::$exchangeRates[$base] = self::$cache->get($key))) {
                $url = 'https://api.hazaar.io/api/money/latest?base='.$base;
                $result = json_decode(file_get_contents($url), true);
                self::$exchangeRates[$base] = $result;
                self::$cache->set($key, self::$exchangeRates[$base]);
            }
        }

        return self::$exchangeRates[$base]['rates'][$foreignCurrency] ?? 0;
    }

    /**
     * @detail      Convert the currency object to another currency and return a new Money object.
     *
     * @param string $foreignCurrency The currency to convert to.  Can be country or currency code.
     *
     * @return Money a new currency object with the value of the foreign currency amount
     */
    public function convertTo(string $foreignCurrency): Money
    {
        return new Money($this->value * $this->getExchangeRate($foreignCurrency), $foreignCurrency);
    }

    /**
     * @detail      The format method will format the currency value amount to an international standard format of
     *              {symbol}{amount}{code}.  For example, US dollars will be expressed as $100USD.  Australian
     *              dollar as $105AUD and so on.
     *
     * @param string $format An optional format passed to the money_format function.  If not specified the global
     *                       default format will be used.
     *
     * @return string The currency value as a formatted string
     */
    public function format(?string $format = null): string
    {
        $nm = new \NumberFormatter(setlocale(LC_MONETARY, ''), \NumberFormatter::CURRENCY);

        return $nm->formatCurrency($this->value, 'AUD');
    }

    /**
     * @detail      Convert currency to a string.  Outputs the same as the self::format() method using the default
     *              format.
     *
     * @return string eg: $100USD
     */
    public function toString(): string
    {
        return $this->format();
    }

    /**
     * @detail      Get the currency as a float value formatted using the money_format PHP function with optional
     *              precision to specify the number of decimal places.
     *
     * @param int $precision The number of decimal places to round to.  Defaults to 2.
     *
     * @return float The currency value as a float
     */
    public function toFloat(int $precision = 2): float
    {
        return round($this->value, $precision);
    }

    /**
     * @detail      Get the currency value represented as an integer in cents.  1 dollar = 100 cents.
     *
     * @return int the currency value in whole cents
     */
    public function toCents(): int
    {
        return (int) round($this->value * 100);
    }

    /**
     * @detail      Add one or more amounts or Money objects to the current currency.  Parameters here can be either
     *              a numeric value or another Money object.  If the parameter is a Money object then the value
     *              will be automatically converted using the current exchange rate before it is added.
     *
     *              ### Parameters
     *
     *              Any number of numeric or Money objects parameters.
     *
     * @return Money the sum of all values as a new Money object
     */
    public function add(): Money
    {
        $total = $this->value;
        foreach (func_get_args() as $arg) {
            if (is_numeric($arg)) {
                $total += $arg;
            } elseif ($arg instanceof Money) {
                $total += $arg->convertTo($this->localCurrency['currencycode'])->toFloat();
            }
        }

        return new Money($total, $this->localCurrency['currencycode']);
    }

    /**
     * @detail      Subtract one or more values or Money objects from the current object.  Parameters can be either
     *              a numeric value or another Money object.  If the parameter is a Money object then the value
     *              will be automatically converted using the current exchange rate before it is subtracted.
     *
     * @return Money the result of subtraction as a new Money object
     */
    public function subtract(): Money
    {
        $total = $this->value;
        foreach (func_get_args() as $arg) {
            if (is_numeric($arg)) {
                $total -= $arg;
            } elseif ($arg instanceof Money) {
                $total -= $arg->convertTo($this->localCurrency['currencycode'])->toFloat();
            }
        }

        return new Money($total, $this->localCurrency['currencycode']);
    }

    /**
     * Sets the value of the Money object.
     *
     * @param float|string $value     the value to set
     * @param null|string  &$currency The currency to set. Pass by reference.
     *
     * @return float the updated value of the Money object
     */
    public function set(float|string $value, ?string &$currency = null): float
    {
        if (is_string($value)) {
            $value = trim($value);
            if (preg_match('/^(\D*)([\d\.,]+)(\D*)/', $value, $matches)) {
                $this->value = floatval(str_replace(',', '', $matches[2]));
                $currency = $matches[3];
            } else {
                $this->value = floatval(substr($value, 1));
            }
        } else {
            $this->value = floatval($value);
        }

        return $this->value;
    }

    /**
     * Get the name of the currency.
     *
     * @return string the name of the currency
     */
    public function getCurrencyName(): string
    {
        return $this->localCurrency['name'] ?? '';
    }
}
