<?php
/**
 * Created by PhpStorm.
 * Author: Jamie Carl
 * Date: 25/09/14
 * Time: 9:21 AM
 * 
 * @package     Core
 */

namespace Hazaar\Application;

/**
 * @brief       Hazaar Application Protocol Class
 *
 * @detail      The Application Protocol is a simple protocol developed to allow communication between
*               parts of the Hazaar framework over the wire or other IO interfaces.  It allows common information
*               to be encoded/decoded between endpoints.
 *
 * @since       2.0.0
 *
 * @package     Core
 */
class Protocol {

    private $encoded   = TRUE;

    private $typeCodes = array(
        0x00 => 'NOOP',         //Null Opperation
        0x01 => 'SYNC',         //Sync client
        0x02 => 'OK',           //OK response
        0x03 => 'ERROR',        //Error response
        0x04 => 'STATUS',       //Status request/response
        0x05 => 'SHUTDOWN',     //Shutdown request
        0x06 => 'DELAY',        //Execute code after a period
        0x07 => 'SCHEDULE',     //Execute code at a set time
        0x08 => 'CANCEL',       //Cancel a pending code execution
        0x09 => 'ENABLE',       //Start a service
        0x0A => 'DISABLE',      //Stop a service
        0x0B => 'SERVICE',      //Service status
        0x0C => 'SUBSCRIBE',    //Subscribe to an event
        0x0D => 'UNSUBSCRIBE',  //Unsubscribe from an event
        0x0E => 'TRIGGER',      //Trigger an event
        0x0F => 'EVENT',        //An event
        0x10 => 'EXEC',         //Execute some code in the Warlock Runner.
        0x99 => 'DEBUG'
    );

    private $id;

    private $last_error;

    function __construct($id, $encoded = TRUE) {

        $this->id = $id;

        $this->encoded = $encoded;

    }

    public function getLastError() {

        return $this->last_error;

    }

    private function error($msg) {

        $this->last_error = $msg;

        return FALSE;

    }

    public function getType($name) {

        return array_search(strtoupper($name), $this->typeCodes);

    }

    public function getTypeName($type) {

        if(! array_key_exists($type, $this->typeCodes))
            return FALSE;

        return $this->typeCodes[$type];

    }

    public function encode($type, $payload = array()) {

        if(is_string($type))
            $type = array_search(strtoupper($type), $this->typeCodes);

        $packet = array(
            'TYP' => $type,
            'SID' => $this->id,
            'TME' => time()
        );

        if($payload)
            $packet['PLD'] = $payload;

        $packet = json_encode($packet);

        return ($this->encoded ? base64_encode($packet) : $packet);

    }

    public function decode($packet, &$payload = NULL, &$offset = NULL) {

        $packet = json_decode(($this->encoded ? base64_decode($packet) : $packet), TRUE);

        if(! $packet)
            return $this->error('Packet decode failed');

        if(! is_array($packet))
            return $this->error('Invalid packet format');

        if(! array_key_exists('TYP', $packet))
            return $this->error('No packet type');

        //This is a security thing to ensure that the client is connecting to the correct instance of Warlock
        if(! array_key_exists('SID', $packet) || $packet['SID'] != $this->id)
            return $this->error('Packet decode rejected due to bad SID.');

        if(array_key_exists('PLD', $packet))
            $payload = $packet['PLD'];

        if(array_key_exists('TME', $packet))
            $offset = time() - $packet['TME'];

        return $packet['TYP'];

    }

} 