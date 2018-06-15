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

    private $encoded   = true;

    private $typeCodes = array(
        //SYSTEM MESSAGES
        0x00 => 'NOOP',         //Null Opperation
        0x01 => 'SYNC',         //Sync client
        0x02 => 'OK',           //OK response
        0x03 => 'ERROR',        //Error response
        0x04 => 'STATUS',       //Status request/response
        0x05 => 'SHUTDOWN',     //Shutdown request
        0x06 => 'PING',         //Typical PING
        0x07 => 'PONG',         //Typical PONG

        //CODE EXECUTION MESSAGES
        0x10 => 'DELAY',        //Execute code after a period
        0x11 => 'SCHEDULE',     //Execute code at a set time
        0x12 => 'EXEC',         //Execute some code in the Warlock Runner.
        0x13 => 'CANCEL',       //Cancel a pending code execution

        //SIGNALLING MESSAGES
        0x20 => 'SUBSCRIBE',    //Subscribe to an event
        0x21 => 'UNSUBSCRIBE',  //Unsubscribe from an event
        0x22 => 'TRIGGER',      //Trigger an event
        0x23 => 'EVENT',        //An event

        //SERVICE MESSAGES
        0x30 => 'ENABLE',       //Start a service
        0x31 => 'DISABLE',      //Stop a service
        0x32 => 'SERVICE',      //Service status
        0x33 => 'SPAWN',        //Spawn a dynamic service
        0x34 => 'KILL',         //Kill a dynamic service instance

        //LOGGING/OUTPUT MESSAGES
        0x90 => 'LOG',          //Generic log message
        0x91 => 'DEBUG'
    );

    private $id;

    private $last_error;

    function __construct($id, $encoded = true) {

        $this->id = $id;

        $this->encoded = $encoded;

    }

    public function getLastError() {

        return $this->last_error;

    }

    /**
     * Checks that a protocol message type is valid and returns it's numeric value
     *
     * @param mixed $type If $type is a string, it is checked and if valid then it's numeric value is returned.  If $type is
     *                      an integer it will be returned back if valid.  If either is not valid then false is returned.
     * @return mixed The integer value of the message type. False if the type is not valid.
     */
    public function check($type){

        if(is_int($type)){

            if(array_key_exists($type, $this->typeCodes))
                return $type;

            return false;

        }

        return array_search(strtoupper($type), $this->typeCodes, true);

    }

    private function error($msg) {

        $this->last_error = $msg;

        return false;

    }

    public function getType($name) {

        return array_search(strtoupper($name), $this->typeCodes);

    }

    public function getTypeName($type) {

        if(!is_int($type))
            return $this->error('Bad packet type!');

        if(! array_key_exists($type, $this->typeCodes))
            return $this->error('Unknown packet type!');

        return $this->typeCodes[$type];

    }

    public function encode($type, $payload = array()) {

        if(($type = $this->check($type)) === false)
            return false;

        $packet = (object) array(
            'TYP' => $type,
            'SID' => $this->id,
            'TME' => time()
        );

        if($payload)
            $packet->PLD = $payload;

        $packet = json_encode($packet);

        return ($this->encoded ? base64_encode($packet) : $packet);

    }

    public function decode($packet, &$payload = null, &$time = null) {

        $payload = null;

        if(!($packet = json_decode(($this->encoded ? base64_decode($packet) : $packet))))
            return $this->error('Packet decode failed');

        if(!$packet instanceof \stdClass)
            return $this->error('Invalid packet format');

        if(!property_exists($packet, 'TYP'))
            return $this->error('No packet type');

        //This is a security thing to ensure that the client is connecting to the correct instance of Warlock
        if(! property_exists($packet, 'SID') || $packet->SID != $this->id)
            return $this->error('Packet decode rejected due to bad SID.');

        if(property_exists($packet, 'PLD'))
            $payload = $packet->PLD;

        if(property_exists($packet, 'TME'))
            $time = $packet->TME;

        return $this->getTypeName($packet->TYP);

    }

} 