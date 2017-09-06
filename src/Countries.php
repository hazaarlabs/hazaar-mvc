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
class Countries {

    private $info;

    function __construct(){

        $this->info = $this->load();

    }

    private function load(){

        if($info = Loader::getFilePath(FILE_PATH_SUPPORT, 'currencyInfo.csv')) {

            $h = fopen($info, 'r');

            if($h) {

                $lines = array();

                $headers = FALSE;

                while($line = fgetcsv($h, 0, ',')) {

                    if($headers == FALSE) {

                        $headers = $line;

                        continue;

                    }

                    if(substr($line[0], 0, 1) == '#')
                        continue;

                    $infoline = array();

                    foreach($headers as $index => $key)
                        $infoline[$key] = $line[$index];

                    $lines[strtolower($line[0])] = $infoline;

                }

                fclose($h);

                return $lines;

            }

        }

        return false;

    }

    public function getName($code){

        return ake(ake($this->info, strtolower($code)), 'country');

    }

    public function getCode($name){

        foreach($this->info as $info){

            if(strcasecmp($info['country'], $name) == 0)
                return $info['iso'];

        }

        return false;

    }

    public function listAll(){

        $list = array();

        foreach($this->info as $iso => $info)
            $list[$iso] = $info['country'];

        return $list;

    }

}