<?php

namespace Hazaar;

/**
 * Countries short summary.
 *
 * Countries description.
 *
 * @version 1.0
 * @author jamiec
 */
class GeoData {

    private $dbs;

    private $source = 'http://geolite.maxmind.com/download/geoip/database/GeoLite2-City-CSV.zip';

    private $source_md5 = 'http://geolite.maxmind.com/download/geoip/database/GeoLite2-City-CSV.zip.md5';

    private $source_csv = 'GeoLite2-City-Locations-en.csv';

    function __construct(){

        $file = new \Hazaar\File(\Hazaar\Application::getInstance()->runtimePath('geodata.db'));

        $this->db = new \Hazaar\Btree($file);

        if($this->db->get('__loaded__') === null)
            $this->__initialise();

        return true;

    }

    private function __initialise(){

        //return false;
        $data = array();

        $tmpdir = new \Hazaar\File\Dir(Application::getInstance()->runtimePath('geodata', true));

        /*
         * Download the database ZIP file and check it's MD5 signature
         */
        $zipfile = $tmpdir->get('geodata.zip');

        $zipfile->put_contents(file_get_contents($this->source));

        $md5 = file_get_contents($this->source_md5);

        if($zipfile->md5() !== $md5)
            return false;

        $files = $zipfile->unzip($this->source_csv, $tmpdir);

        $zipfile->unlink(); //Cleanup now

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

        $this->db->set('__loaded__', true);

        $tmpdir->delete(true);

    }

    public function getCountryName($code){

        return ake($this->dbs['country']->get(strtoupper($code)), 'name');

    }

    public function getCode($name){

        foreach($this->info as $info){

            if(strcasecmp($info['country'], $name) == 0)
                return $info['iso'];

        }

        return false;

    }

    private function __list($db, $field){

        $list = array();

        $codes = $this->db->keys();

        foreach($codes as $code){

            if(substr($code, 0, 2) == '__')
                continue;

            $list[$code] = ake($this->db->get($code), 'name');

        }

        asort($list);

        return $list;

    }

    public function countries(){

        return $this->__list('country', 'country_name');

    }

    public function countryInfo($code){

        return $this->db->get(strtoupper($code));

    }

    public function states($country_code){

        $list = array();

        if($country = $this->db->get(strtoupper($country_code))){

            foreach(ake($country, 'states') as $code => $state)
                $list[$code] = $state['name'];

            asort($list);

        }

        return $list;

    }

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

}
