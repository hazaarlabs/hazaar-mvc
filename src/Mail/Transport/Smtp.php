<?php

namespace Hazaar\Mail\Transport;

class Smtp extends \Hazaar\Mail\Transport {

    private $server;

    private $port;

    private $socket;

    private $read_timeout;

    public function init($settings){

        $config = new \Hazaar\Map(
            [
                'port' => 25,
                'timeout' => 5
            ]
        , $settings);
        
        if(!$config->has('server'))
            throw new \Exception('Cannot send mail.  No SMTP mail server is configured!');

        $this->server = $config->server;

        $this->port = $config->get('port');

        $this->read_timeout = $config->get('timeout');

    }

    private function connect(){

        if(is_resource($this->socket))
            fclose($this->socket);

        $this->socket = @stream_socket_client($this->server . ':' . $this->port, $errno, $errstr, $this->read_timeout);

        if(!$this->socket)
            throw new \Exception('Unable to connect to ' . $this->server . ':' . $this->port . '. Reason: ' . $errstr, $errno);

        return true;

    }

    private function close(){

        if(!$this->socket instanceof \Hazaar\Socket)
            return false;

        fclose($this->socket);

        $this->socket = null;

        return true;

    }

    private function write($msg){

        if(!(is_resource($this->socket) && is_string($msg) && strlen($msg) > 0))
            return false;

        return fwrite($this->socket, $msg . "\r\n", strlen($msg) + 2);

    }

    private function read($code, $len = 1024, &$message = null, &$response = null){

        if(!is_resource($this->socket))
            return false;
    
        $read = [
                $this->socket
        ];
        
        $write = null;
        
        $exempt = null;
        
        stream_select($read, $write, $exempt, $this->read_timeout, 0);
        
        if(!(count($read) > 0 && $read[0] == $this->socket))
            throw new \Exception('SMTP data receive timeout');

        $response = fread($this->socket, $len);

        if($this->getMessageCode($response, $message) !== $code)
            return false;

        return true;

    }

    public function send($to, $subject = null, $message = null, $extra_headers = [], $dsn_types = []){
        
        $from = ake($extra_headers, 'From');

        if(preg_match('/\<([^\>]+)\>/', $from, $matches))
            $from = $matches[1];

        $this->connect($this->server, $this->port);

        if(!$this->read(220, 1024, $result))
            throw new \Exception('Invalid response on connection: ' . $result);

        $this->write('EHLO ' . gethostname());

        if(!$this->read(250, 65535, $result, $response))
            throw new \Exception('Bad response on mail from: ' . $result);
        
        $modules = explode("\r\n", $response);

        foreach($modules as &$module){

            if(substr($module, 0, 4) === '250-')
                $module = strtoupper(substr($module, 4));

        }

        //Always use STARTTLS unless it has been explicitly disabled.
        if(in_array('STARTTLS', $modules) && $this->options->get('starttls') !== false){

            $this->write('STARTTLS');

            if(!$this->read(220, 1024, $error))
                throw new \Exception($error);

            if(!stream_socket_enable_crypto($this->socket, true, STREAM_CRYPTO_METHOD_SSLv23_CLIENT))
                throw new \Exception(ake(\error_get_last(), 'message'));

        }elseif($this->options->get('starttls') === true){

            throw new \Exception('STARTTLS is required but server does not support it.');

        }

        $auth_methods = array_reduce($modules, function($carry, $item){
            if(substr($item, 0, 4) === 'AUTH') return array_merge($carry, explode(' ', substr($item, 5)));
            return $carry;
        }, []);

        if($dsn_active = is_array($dsn_types) && count($dsn_types) > 0 && in_array('DSN', $modules))
            $dsn_types = array_map('strtoupper', $dsn_types);

        if(($username = $this->options->get('username'))){

            if(in_array('CRAM-MD5', $auth_methods)){

                $this->write('AUTH CRAM-MD5');

                if(!$this->read(334, 128, $result))
                    throw new \Exception('Server does not want to CRAM-MD5 authenticate. Reason: ' . $result);

                $this->write(base64_encode($username . ' ' . hash_hmac('MD5', base64_decode($result), $this->options->get('password'))));

            }elseif(in_array('LOGIN', $auth_methods)){

                $this->write('AUTH LOGIN');

                if(!$this->read(334, 128, $result))
                    throw new \Exception('Server does not want to LOGIN authenticate. Reason: ' . $result);

                if(($prompt = base64_decode($result)) !== 'Username:')
                    throw new \Exception('Server is broken.  Sent weird username prompt \'' . $prompt . '\'');

                $this->write(base64_encode($username));

                if(!$this->read(334, 128, $result))
                    throw new \Exception('Server did not request password.  Reason: ' . $result);

                $this->write(base64_encode($this->options->get('password')));
                
            }elseif(in_array('PLAIN', $auth_methods)){

                $this->write('AUTH PLAIN');

                if(!$this->read(334, 128, $result))
                    throw new \Exception('Server does not want to do PLAIN authentication. Reason: ' . $result);

                $this->write(base64_encode("\0" . $username . "\0" . $this->options->get('password')));

            }else{

                throw new \Exception('Authentication not possible.  Only CRAM-MD5, LOGIN and PLAIN methods are supported.  Server needs: ' . grammatical_implode($auth_methods, 'or'));
            
            }

            if(!$this->read(235, 1024, $result, $response))
                throw new \Exception('SMTP Auth failed: ' . $result);

        }

        $this->write("MAIL FROM: $from" . ($dsn_active ? ' RET=HDRS' : ''));

        if(!$this->read(250, 1024, $result))
            throw new \Exception('Bad response on mail from: ' . $result);

        $rcpt = $to;

        if($cc = ake($extra_headers, 'CC'))
            $rcpt = array_merge($rcpt, array_map('trim', explode(',', $cc)));

        if($bcc = ake($extra_headers, 'BCC'))
            $rcpt = array_merge($rcpt, array_map('trim', explode(',', $bcc)));

        foreach($rcpt as $x){

            if(preg_match('/^(.*)<(.*)>$/', $x, $matches)){

                if(!(array_key_exists('To', $extra_headers) && is_array($extra_headers['To'])))
                    $extra_headers['To'] = (array_key_exists('To', $extra_headers) ? $extra_headers['To'] : []);

                $extra_headers['To'][] = $x;

                $x = $matches[2];

            }

            $this->write("RCPT TO: <$x>" . ($dsn_active ? ' NOTIFY=' . implode(',', $dsn_types) : ''));

            if(!$this->read(250, 1024, $result))
                throw new \Exception('Bad response on mail to: ' . $result);

        }

        $this->write('DATA');

        if(!$this->read(354))
            throw new \Exception('Server rejected our data!');

        $extra_headers['Subject'] = $subject;

        $out = '';

        foreach($extra_headers as $key => $value)
            $out .= "$key: " . (is_array($value) ? implode(', ', $value) : $value) . "\r\n";

        $out .= "\r\n$message\r\n.";

        $this->write($out);

        if(!$this->read(250, 1024, $reason))
            throw new \Exception('Server rejected our data: ' . $reason);

        $this->write('QUIT');

        $this->close();

        return true;

    }

    private function getMessageCode($buf, &$message = null){

        if(!preg_match('/^(\d+)\s+(.+)$/m', $buf, $matches))
            return false;

        if(!is_numeric($matches[1]))
            return false;
            
        if(isset($matches[2]))
            $message = $matches[2];

        return intval($matches[1]);

    }


}
