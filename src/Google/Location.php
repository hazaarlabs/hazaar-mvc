<?php

namespace Hazaar\Google;

class Location {

    private $google_geocode_url = "https://maps.googleapis.com/maps/api/geocode/json";

    private $location;

    function __construct() {

        if(func_num_args() > 0) {

            call_user_func_array(array(
                $this,
                'geocode'
            ), func_get_args());

        }

    }

    public function geocode() {

        /*
         * A single argument is treated as an address value
         */
        if(func_num_args() == 1) {

            return $this->request(array('address' => preg_replace('/\s/', '+', func_get_arg(0))));

            /*
             * Two arguments are treated as latitude and longitude
             */
        } elseif(func_num_args() == 2) {

            return $this->request(array('latlng' => func_get_arg(0) . ',' . func_get_arg(1)));

        }

        /*
         * Anything else will throw an exception
         */
        throw new \Exception('Unsupported number of arguments to method call ' . __METHOD__ . '(' . implode(', ', array_fill(0, func_num_args(), '$arg')) . ');');

    }

    private function request($args) {

        $url_args = array('sensor=false');

        foreach($args as $key => $value)
            $url_args[] = "$key=$value";

        $url = $this->google_geocode_url . '?' . implode('&', $url_args);

        $result = json_decode(file_get_contents($url), true);

        if($result['status'] == 'OK') {

            $this->location = $result['results'];

        }

        return $this;

    }

    public function count() {

        return count($this->locations);

    }

    public function current() {

        if(! is_array($this->location))
            return null;

        return current($this->location);

    }

    public function next() {

        if(! is_array($this->location))
            return null;

        return next($this->location);

    }

    public function lat() {

        if($result = $this->current($this->location)) {

            return $result['geometry']['location']['lat'];

        }

        return null;

    }

    public function lng() {

        if($result = current($this->location)) {

            return $result['geometry']['location']['lng'];

        }

        return null;

    }

    public function address() {

        if($result = current($this->location)) {

            return $result['formatted_address'];

        }

        return null;

    }

}

