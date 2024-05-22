<?php

declare(strict_types=1);

namespace Hazaar;

/**
 * @brief       The version class
 *
 * @detail      Handy for comparing version numbers such as 1.0 vs 1.3 or even 2.3.6 vs 1.6.3.7.32
 *
 * @module      core
 */
class Version
{
    public static string $default_delimiter = '.';
    private ?int $__precision = null;
    private string $__version = 'none';

    /**
     * @var array<string>
     */
    private array $__version_parts = [];

    /**
     * Version constructor.
     *
     * @param string $version string The version number as a string
     *
     * @throws \Exception
     */
    public function __construct(string $version, ?int $precision = null, ?string $delimiter = null)
    {
        $this->__precision = $precision;
        if (!$delimiter) {
            $delimiter = self::$default_delimiter;
        }
        $this->set($version, $delimiter);
    }

    /**
     * Magic method to output the version as a string.
     *
     * @return string
     */
    public function __toString()
    {
        return $this->get();
    }

    /**
     * Format a version number using the specified precision.
     *
     * @param array<string>|string $version   the version number to format
     * @param int                  $precision The precision of the version.  Normally 2 or 3, where 3 is Major/Minor/Revision.
     * @param string               $delimiter Optionally override the version delimiter.  Normall a single character, ie: a full stop (.).
     *                                        This will be the global default of '.' if not specified.
     */
    public static function format(array|string $version, int $precision, ?string $delimiter = null): string
    {
        if (!$delimiter) {
            $delimiter = self::$default_delimiter;
        }
        $version = is_array($version) ? $version : explode($delimiter, $version);

        return implode('.', array_pad($version, $precision, '0'));
    }

    public function set(string $version, ?string $delimiter = null): void
    {
        if (null === $delimiter) {
            $delimiter = self::$default_delimiter;
        }
        if (!preg_match('/[0-9]+(\\'.$delimiter.'[0-9]+)*/', $version)) {
            throw new Exception('Invalid version format');
        }
        if (!is_int($this->__precision)) {
            $this->__precision = substr_count($version, $delimiter) + 1;
        }
        $this->__version_parts = preg_split('/\\'.$delimiter.'/', $version);
        $this->__version = self::format($this->__version_parts, $this->__precision);
    }

    /**
     * Get the current version number.
     *
     * @return string
     */
    final public function get()
    {
        return $this->__version;
    }

    /**
     * Get the version parts as an array.
     *
     * @return array<string>
     */
    public function getParts(): array
    {
        return $this->__version_parts;
    }

    /**
     * Compare the version to another version.
     *
     * Returns an integer indicating the result:
     * * 0 Indicates the versions are equal.
     * * -1 Means the vesion is less than $that.
     * * 1 Means the version is greater than $that.
     *
     * @param string|Version $that The version to compare against.  Can be either a version string or another version object.
     *
     * @return int either -1, 0 or 1 to indicate if the version is less than, equal to or greater than $that
     */
    public function compareTo(string|Version $that, ?string $delimiter = null)
    {
        if (!$that instanceof Version) {
            $that = new Version($that, $this->__precision, $delimiter);
        }
        $thatParts = $that->getParts();
        $length = max(count($this->__version_parts), count($thatParts));
        for ($i = 0; $i < $length; ++$i) {
            $thisPart = $i < count($this->__version_parts) ? (int)$this->__version_parts[$i] : 0;
            $thatPart = $i < count($thatParts) ? (int)$thatParts[$i] : 0;
            if ($thisPart < $thatPart) {
                return -1;
            }
            if ($thisPart > $thatPart) {
                return 1;
            }
        }

        return 0;
    }

    /**
     * Compares two versions to see if they are equal.
     *
     * @param string|Version $that The version to compare to
     *
     * @return bool TRUE or FALSE indicating if the versions are equal
     */
    public function equals(string|Version $that): bool
    {
        if ($this == $that) {
            return true;
        }

        return 0 == $this->compareTo($that);
    }

    public function setIfHigher(string|Version $version, ?string $delimiter = null): void
    {
        if (-1 === $this->compareTo($version, $delimiter)) {
            $this->set($version, $delimiter);
        }
    }

    public function setIfLower(string|Version $version, ?string $delimiter = null): void
    {
        if (1 === $this->compareTo($version, $delimiter)) {
            $this->set($version, $delimiter);
        }
    }
}
