<?php

namespace Hazaar;

/**
 * The GeoData class for accessing geographic information on countries.
 *
 * This method allows access to useful country information such as codes, names, continents,
 * states, cities and various other data.
 *
 * Data is obtained via the publicly available GeoLite2 databases provided by MaxMind.
 *
 * See the [MaxMind GeoLite2 Pages](https://dev.maxmind.com/geoip/geoip2/geolite2/) for more information.
 *
 * <p class="notice">Currently, IP information is not stored and only country/state/city level data is
 * searchable.</p>
 *
 * <p class="notice warning">The first time the \Hazaar\GeoData class is used it needs to download some files
 * and construct an internal B-Tree database.  This requires HTTP access to the internet and depending
 * on the speed of the connection can take some time (usually around 10-15 seconds).  Once the B-Tree
 * database is constructed then data access speeds are extremely fast.</p>
 *
 * @since 2.3.44
 *
 * @author Jamie Carl <jamie@hazaarlabs.com>
 */
class GeoData {

    /**
     * The publicly available GeoData database data sources.
     * @var array
     */
    static private $sources = array(
        'city' => array(
            'url' => 'http://geolite.maxmind.com/download/geoip/database/GeoLite2-City-CSV.zip',
            'md5' => 'http://geolite.maxmind.com/download/geoip/database/GeoLite2-City-CSV.zip.md5',
            'csv' => 'GeoLite2-City-Locations-en.csv'
        ),
        'code' => array(
            'url' => 'https://countrycode.org/customer/countryCode/downloadCountryCodes'
        )
    );

    /**
     * The current GeoData database format version.
     *
     * Changing this triggers a re-initialisation of the internal database.
     *
     * @var int
     */
    static private $version = 2;

    /**
     * The internal B-Tree database adapter
     *
     * @var \Hazaar\Btree
     */
    private $db;

    function __construct($re_intialise = false){

        $file = new \Hazaar\File(\Hazaar\Application::getInstance()->runtimePath('geodata.db'));

        $this->db = new \Hazaar\Btree($file);

        if($re_intialise === true || $this->db->get('__version__') !== GeoData::$version)
            $this->__initialise();

        return true;

    }

    /**
     * Initialises the internal B-Tree database with all available data
     * @throws \Exception
     * @return boolean
     */
    private function __initialise(){

        $extra = array(
            'E164' => array('type' => 'int', 'key' => 'phone_code'),
            'Language Codes' => array('type' => 'array', 'delimiter' => ',', 'key' => 'languages'),
            'Capital' => 'capital',
            'Time Zone in Capital' => 'capital_timezone',
            'Area KM2' => array('type' => 'int', 'key' => 'area'),
            'Internet Hosts' => array('type' => 'int', 'key' => 'hosts'),
            'Internet Users' => array('type' => 'int', 'key' => 'users')
        );

        $data = array();

        $this->db->reset_btree_file();

        $tmpdir = new \Hazaar\File\Dir(Application::getInstance()->runtimePath('geodata', true));

        $city_zipfile = $tmpdir->get('geodata.zip');

        /*
         * Download the city database ZIP file and check it's MD5 signature
         */
        $city_zipfile->put_contents(file_get_contents(GeoData::$sources['city']['url']));

        if(!$city_zipfile->size() > 0)
            throw new \Exception('Unable to download city info source file!');

        $md5 = file_get_contents(GeoData::$sources['city']['md5']);

        if($city_zipfile->md5() !== $md5)
            throw new \Exception('City info source file MD5 signature does not match!');

        $files = $city_zipfile->unzip(GeoData::$sources['city']['csv'], $tmpdir);

        $city_zipfile->unlink(); //Cleanup now

        /*
         * Download extra country code data
         */
        $codes = array();

        $codes_file = explode("\n", file_get_contents(GeoData::$sources['code']['url']));

        $headers = str_getcsv(array_shift($codes_file));

        foreach($codes_file as $line){

            $line = str_getcsv($line);

            if(count($line) !== count($headers))
                continue;

            $item = array();

            foreach($line as $col => $value)
                $item[$headers[$col]] = $value;

            $codes[$item['ISO2']] = $item;

        }

        /*
         * Process the contents of the CSV and store in our Btree database
         */
        foreach($files as $file){

            $file->open();

            $columns = $file->getCSV();

            while($line = $file->getCSV()){

                if(count($columns) !== count($line))
                    continue;

                $entry = array_combine($columns, $line);

                if(!($country_code = $entry['country_iso_code']))
                    continue;

                if(!array_key_exists($country_code, $data)){

                    $data[$country_code] = array(
                        'id' => 'geoname_id',
                        'code' => $country_code,
                        'name' => $entry['country_name'],
                        'continent' => array(
                            'code' => $entry['continent_code'],
                            'name' => $entry['continent_name']
                        ),
                        'states' => array(),
                        'cities' => array()
                    );

                    if(array_key_exists($country_code, $codes)){

                        foreach($extra as $source_key => $target_key){

                            $value = $codes[$country_code][$source_key];

                            if(is_array($target_key)){

                                if(!($key = ake($target_key, 'key')))
                                    continue;

                                $type = ake($target_key, 'type', 'string');

                                if($type === 'array'){

                                    if(!($delim = ake($target_key, 'delimiter')))
                                        continue;

                                    $value = explode($delim, $value);

                                }else{

                                    settype($value, $type);

                                }

                                $data[$country_code][$key] = $value;

                            }else{

                                $data[$country_code][$target_key] = $value;

                            }

                        }

                    }

                }

                if(!($state_code = $entry['subdivision_1_iso_code']))
                    continue;

                if(!array_key_exists($state_code, $data[$country_code]['states'])){

                    $data[$country_code]['states'][$state_code] = array(
                        'code' => $state_code,
                        'name' => $entry['subdivision_1_name'],
                        'cities' => array()  //City index
                    );

                }


                if(!($city_name = $entry['city_name']))
                    continue;

                $city_id = uniqid();

                $data[$country_code]['cities'][$city_id] = array(
                    'name' => $city_name,
                    'timezone' => $entry['time_zone'],
                    'state' => $state_code
                );

                $data[$country_code]['states'][$state_code]['cities'][] = $city_id;

            }

            $file->close();

            $file->unlink();

        }

        foreach($data as $key => $country)
            $this->db->set($key, $country);

        $this->db->set('__version__', GeoData::$version);

        $tmpdir->delete(true);

        return true;

    }

    /**
     * Obtains a list of all countries indexed by code
     * @param mixed $db
     * @param mixed $field
     * @return array
     */
    private function __list(\Hazaar\Btree $db, $field = 'name'){

        $list = array();

        $codes = $db->range("\x00", "\xff");

        foreach($codes as $code => $info){

            if(substr($code, 0, 2) == '__')
                continue;

            $list[$code] = ake($info, $field);

        }

        asort($list);

        return $list;

    }

    /**
     * Retrieve a list of countries
     *
     * This method will return an associative array containing a list of all countries organised by their
     * two character ISO code.  The ISO code is the key and the country name is the value.
     *
     * @return array
     */
    public function countries(){

        return $this->__list($this->db, 'name');

    }

    /**
     * Retrieve information about a country by it's ISO code.
     *
     * This method will return an array that contains:
     * - id := The GeoNamesID
     * - code := Two character ISO country code
     * - name := Country name
     * - continent := Continent info containing the continent ISO code and name.
     * - phone_code := Two digit telephone dialing code (E164)
     * - languages := Array of languages used in this country
     * - capital := Name of the capital city
     * - capital_timezone := The timezone in the capital city
     * - area := Physical area in KM/2
     * - hosts := Estimated number of active internet hosts
     * - users := Estimated number of active internet users
     *
     * @param string $code The two character ISO country code to get information for.
     *
     * @return array An array of available country information.
     */
    public function country_info($code){

        $info = $this->db->get(strtoupper($code));

        unset($info['states']);

        unset($info['cities']);

        return $info;

    }

    /**
     * Retrieve a list of states for a country
     *
     * Using a two character ISO country code, this method will return a list of states for that country.
     *
     * @param string $country_code Two character ISO country code
     *
     * @return array An array of states with their state code as the key and full name as the value.
     */
    public function states($country_code){

        $list = array();

        if($country = $this->db->get(strtoupper($country_code))){

            foreach(ake($country, 'states') as $code => $state)
                $list[$code] = $state['name'];

            asort($list);

        }

        return $list;

    }

    /**
     * Retrieve a list of cities for the requested country and state
     *
     * Using a two character ISO country code and the state code this method will return an
     * array of cities in that state.
     *
     * @param string $country_code Two character ISO country code.
     * @param string $state_code State code.
     * @return array An array of city names.
     */
    public function cities($country_code, $state_code){

        $list = array();

        if($country = $this->db->get(strtoupper($country_code))){

            $cities = ake(ake(ake($country, 'states'), $state_code), 'cities', array());

            foreach($cities as $id){

                if(!($city = $country['cities'][$id]))
                    continue;

                $list[] = $city['name'];

            }

            sort($list);

        }

        return $list;

    }

    /**
     * Quick access method to retrieve a country name.
     *
     * @param string $country_code A two character ISO country code.
     * @return string
     */
    public function country_name($country_code){

        $info = $this->country_info($country_code);

        return ake($info, 'name');

    }

    /**
     * Quick access method to find a country code using the name of the country.
     *
     * @param string $name The full name of the country to get the ISO code for.
     * @return string
     */
    public function country_code($name){

        $info = $this->db->range("\x00", "\xff");

        foreach($info as $country){

            if(strcasecmp($country['name'], $name) == 0)
                return $country['code'];

        }

        return false;

    }

    /**
     * Quick access method to retrieve country continent info.
     *
     * @param string $country_code Two character ISO country code.
     * @return array
     */
    public function country_continent($country_code){

        $info = $this->country_info($country_code);

        return ake($info, 'continent');

    }

    /**
     * Quick access method to retrieve country language info.
     *
     * @param string $country_code Two character ISO country code.
     * @return array
     */
    public function country_languages($country_code){

        $info = $this->country_info($country_code);

        return ake($info, 'languages');

    }

    /**
     * Quick access method to retrieve country phone dialling code.
     *
     * @param string $country_code Two character ISO country code.
     * @return int
     */
    public function country_phone_code($country_code){

        $info = $this->country_info($country_code);

        return ake($info, 'phone_code');

    }

    /**
     * Quick access method to retrieve country capital name.
     *
     * @param string $country_code Two character ISO country code.
     * @return string
     */
    public function country_capital($country_code){

        $info = $this->country_info($country_code);

        return ake($info, 'capital');

    }

    /**
     * Quick access method to retrieve country capital timezone.
     *
     * @param string $country_code Two character ISO country code.
     * @return string
     */
    public function country_capital_timezone($country_code){

        $info = $this->country_info($country_code);

        return ake($info, 'capital_timezone');

    }

    /**
     * Quick access method to retrieve country area in square kilometers.
     *
     * @param string $country_code Two character ISO country code.
     * @return int
     */
    public function country_area($country_code){

        $info = $this->country_info($country_code);

        return ake($info, 'area');

    }

    /**
     * Quick access method to retrieve country estimated internet hosts.
     *
     * @param string $country_code Two character ISO country code.
     * @return int
     */
    public function country_hosts($country_code){

        $info = $this->country_info($country_code);

        return ake($info, 'hosts');

    }

    /**
     * Quick access method to retrieve country estimated internet users.
     *
     * @param string $country_code Two character ISO country code.
     * @return int
     */
    public function country_users($country_code){

        $info = $this->country_info($country_code);

        return ake($info, 'users');

    }

}
