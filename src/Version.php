<?php

namespace Hazaar;

/**
 * @brief       The version class
 *
 * @detail      Handy for comparing version numbers such as 1.0 vs 1.3 or even 2.3.6 vs 1.6.3.7.32
 *
 * @module      core
 */
class Version {

    static public $default_delimiter = '.';

    private $__precision = null;

    private $__version;

    private $__version_parts;

    /**
     * Format a version number using the specified precision
     * 
     * @param $version mixed The version number to format.  Can be a string or a number.
     * 
     * @param $precision integer The precision of the version.  Normally 2 or 3, where 3 is Major/Minor/Revision.
     * 
     * @param $delimiter string Optionally override the version delimiter.  Normall a single character, ie: a full stop (.).  
     *          This will be the global default of '.' if not specified.
     */
    static public function format($version, $precision, $delimiter = null) {

        if(!$delimiter)
            $delimiter = self::$default_delimiter;

        $version = is_array($version) ? $version : explode($delimiter, $version);

        return implode('.', array_pad($version, $precision, '0'));

    }

    /**
     * Version constructor.
     *
     * @param $version string The version number as a string.
     *
     * @throws \Exception
     */
    public function __construct($version, $precision = null, $delimiter = null) {

        if(is_int($precision))
            $this->__precision = $precision;

        if(!$delimiter)
            $delimiter = self::$default_delimiter;

        $this->set($version, $delimiter);
        
    }

    public function set($version, $delimiter = null) {

        if($version === NULL)
            throw new \Hazaar\Exception('Version can not be null');

        if(!preg_match('/[0-9]+(\\' . $delimiter . '[0-9]+)*/', $version))
            throw new \Hazaar\Exception('Invalid version format');

        if(!is_int($this->__precision))
            $this->__precision = substr_count($version, $delimiter) + 1;
        
        $this->__version_parts = preg_split('/\\' . $delimiter . '/', $version);

        $this->__version = self::format($this->__version_parts, $this->__precision);

    }

    /**
     * Get the current version number
     *
     * @return string
     */
    public final function get() {

        return $this->__version;

    }

    /**
     * Magic method to output the version as a string
     *
     * @return string
     */
    public function __tostring() {

        return $this->get();

    }

    public function getParts(){

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
     * @param mixed $that The version to compare against.  Can be either a version string or another version object.
     *
     * @return int Either -1, 0 or 1 to indicate if the version is less than, equal to or greater than $that.
     */
    public function compareTo($that, $delimiter = null) {

        if($that == NULL)
            return 1;

        if(! $that instanceof Version)
            $that = new Version($that, $this->__precision, $delimiter);

        $thatParts = $that->getParts();

        $length = max(count($this->__version_parts), count($thatParts));

        for($i = 0; $i < $length; $i++) {

            $thisPart = $i < count($this->__version_parts) ? intval($this->__version_parts[$i]) : 0;

            $thatPart = $i < count($thatParts) ? intval($thatParts[$i]) : 0;

            if($thisPart < $thatPart)
                return -1;

            if($thisPart > $thatPart)
                return 1;

        }

        return 0;

    }

    /**
     * Compares two versions to see if they are equal.
     *
     * @param $that The version to compare to.
     *
     * @return bool TRUE or FALSE indicating if the versions are equal.
     */
    public function equals($that) {

        if($this == $that)
            return TRUE;

        if($that == NULL)
            return FALSE;

        if(get_class($this) != get_class($that))
            return FALSE;

        return ($this->compareTo($that) == 0);

    }

    public function setIfHigher($version, $delimiter = null) {

        if ($this->compareTo($version, $delimiter) === -1)
            $this->set($version, $delimiter);

    }

    public function setIfLower($version, $delimiter = null) {

        if ($this->compareTo($version, $delimiter) === 1)
            $this->set($version, $delimiter);

    }

}
