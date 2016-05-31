<?php

/**
 * @file        Auth/Adapter.php
 *
 * @author      Jamie Carl <jamie@hazaarlabs.com>
 *
 * @copyright   Copyright (c) 2012 Jamie Carl (http://www.hazaarlabs.com)
 */

/**
 * @brief User authentication namespace
 */
namespace Hazaar\Auth;

/**
 * @brief Abstract authentication adapter
 */
abstract class Adapter implements Adapter\_Interface, \ArrayAccess {

    protected $session;

    private $identity;

    private $credential;
    
    // Extra data fields to store from the user record
    private $extra = array();

    public static $credential_encryption = 'none';

    public static $credential_encryption_count = 1;

    public static $autologin_period = 3600;

    public static $autologin_cookie = 'siteAuth';

    function __construct() {

        $this->session = new \Hazaar\Session(array(
            'use_pragma' => FALSE
        ));
    
    }

    public function setIdentity($identity) {

        $this->identity = $identity;
    
    }

    public function setCredential($credential) {

        $this->credential = $credential;
    
    }

    public function getIdentity() {

        return $this->identity;
    
    }

    public function getCredential($credential = NULL) {

        if (!$credential)
            $credential = $this->credential;
            
            /*
         * Loop for added obfuscation
         */
        for($i = 1; $i <= Adapter::$credential_encryption_count; $i++) {
            
            switch (Adapter::$credential_encryption) {
                case 'md5' :
                    $credential = md5($credential);
                    
                    break;
                case 'sha' :
                    $credential = sha1($credential);
                    
                    break;
            }
        }
        
        return $credential;
    
    }

    /*
     * Supported encryption is md5, sha and none. The count value can be used to employ further obfuscation
     * by running the encryption method multiple times. Twice is usually more than enough.
     */
    static public function setCredentialEncryption($method, $count = 1) {

        Adapter::$credential_encryption = $method;
        
        Adapter::$credential_encryption_count = $count;
    
    }

    public function setDataFields(array $fields) {

        $this->extra = $fields;
    
    }

    public function &get($key) {

        return $this->session->get($key);
    
    }

    public function &__get($key) {

        return $this->get($key);
    
    }

    public function __set($key, $value) {

        return $this->set($key, $value);
    
    }

    public function __isset($key) {

        return $this->has($key);
    
    }

    public function set($key, $value) {

        $this->session->set($key, $value);
    
    }

    public function has($key) {

        return $this->session->has($key);
    
    }

    public function authenticate($identity = NULL, $credential = NULL, $autologin = FALSE) {

        /*
         * Save the authentication data
         */
        if ($identity)
            $this->setIdentity($identity);
        
        if ($credential)
            $this->setCredential($credential);
        
        $auth = $this->queryAuth($this->getIdentity(), $this->extra);
        
        /*
         * First, make sure the identity is correct.
         */
        if ($auth && $auth['identity'] == $this->getIdentity()) {
            
            /*
             * Check the credentials
             */
            if ($auth['credential'] == $this->getCredential()) {
                
                if (array_key_exists('data', $auth))
                    $this->session->setValues($auth['data']);
                
                $this->session->identity = md5($identity);
                
                if ($autologin && Adapter::$autologin_period > 0) {
                    
                    /*
                     * $credential should be encrypted, as stored in the datasource (ie: database), so we
                     * md5 that to totally obscure it. If it is not encrypted then it is not being
                     * stored encrypted and the developer should re-think their auth strategy but
                     * we offer some minor protection from that stupidity here.
                     */
                    $data = array(
                        'identity' => $identity,
                        'hash' => md5($auth['credential'])
                    );
                    
                    $cookie = $this->getAutologinCookieName();
                    
                    setcookie($cookie, http_build_query($data), time() + Adapter::$autologin_period, \Hazaar\Application::path());
                }
                
                return TRUE;
            }
        }
        
        return FALSE;
    
    }

    public function getUserData() {

        return $this->session->toArray();
    
    }

    public function authenticated() {

        /*
         * Because we're nasty, we even make sure the identity is 32 characters so it's likely an MD5 hash
         */
        if ($this->session->has('identity') && strlen($this->session->identity) == 32) {
            
            return TRUE;
        } elseif ($this->canAutoLogin()) {
            
            /*
             * If we've got a cookie set, use the identity to look up credentials
             */
            $cookie = $this->getAutologinCookieName();
            
            parse_str($_COOKIE[$cookie]);
            
            if ($auth = $this->queryAuth($identity, $this->extra)) {
                
                /*
                 * Check the cookie credentials against the ones we just got from the adapter
                 */
                if ($identity == $auth['identity'] && $hash == md5($auth['credential'])) {
                    
                    if (array_key_exists('data', $auth)) {
                        
                        $this->session->setValues($auth['data']);
                    }
                    
                    $this->session->identity = md5($identity);
                    
                    return TRUE;
                }
            }
        }
        
        return FALSE;
    
    }

    public function deauth() {

        $this->session->clear(TRUE);
        
        $cookie = $this->getAutologinCookieName();
        
        if (isset($_COOKIE[$cookie])) {
            
            unset($_COOKIE[$cookie]);
            
            setcookie($cookie, NULL, time() - 3600, \Hazaar\Application::path());
        }
        
        return TRUE;
    
    }

    private function canAutoLogin() {

        $cookie = $this->getAutologinCookieName();
        
        return Adapter::$autologin_period > 0 && isset($_COOKIE[$cookie]);
    
    }

    private function getAutologinCookieName() {

        $cookie = Adapter::$autologin_cookie;
        
        return $cookie;
    
    }

    /**
     * Array Access Methods
     *
     * These methods allows accessing user data as array attributes of the auth object. These methods do not allow this
     * data to be modified in any way.
     */
    public function offsetExists($key) {

        return $this->session->has($key);
    
    }

    public function &offsetGet($key) {

        if ($this->session->has($key))
            return $this->session->get($key);
        
        $result = NULL; // Required to return variables by reference
        
        return $result;
    
    }

    public function offsetSet($key, $value) {

        $this->session->set($key, $value);
    
    }

    public function offsetUnset($key) {

        $this->session->unset($key);
    
    }

}

