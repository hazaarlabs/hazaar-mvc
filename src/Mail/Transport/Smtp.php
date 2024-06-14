<?php

namespace Hazaar\Mail\Transport;

use Hazaar\Mail\Mime\Message;
use Hazaar\Mail\Transport;
use Hazaar\Map;
use Hazaar\Socket;

class Smtp extends Transport
{
    private $server;
    private $port;
    private $socket;
    private $readTimeout;

    public function init($settings)
    {
        $config = new Map(
            [
                'port' => 25,
                'timeout' => 5,
            ],
            $settings
        );
        if (!$config->has('server')) {
            throw new \Exception('Cannot send mail.  No SMTP mail server is configured!');
        }
        $this->server = $config->server;
        $this->port = $config->get('port');
        $this->readTimeout = $config->get('timeout');
    }

    /**
     * Send an email via the transport.
     *
     * @param null|mixed $message
     */
    public function send(
        array $recipients,
        array $from,
        ?string $subject = null,
        $message = null,
        array $headers = [],
        array $attachments = []
    ): bool {
        if ($message instanceof Message && is_array($attachments) && count($attachments) > 0) {
            $message = $message->addParts($attachments);
        }
        $this->connect($this->server, $this->port);
        if (!$this->read(220, 1024, $result)) {
            throw new \Exception('Invalid response on connection: '.$result);
        }
        $this->write('EHLO '.gethostname());
        if (!$this->read(250, 65535, $result, $response)) {
            throw new \Exception('Bad response on mail from: '.$result);
        }

        $modules = explode("\r\n", $response);
        foreach ($modules as &$module) {
            if ('250-' === substr($module, 0, 4)) {
                $module = strtoupper(substr($module, 4));
            }
        }
        // Always use STARTTLS unless it has been explicitly disabled.
        if (in_array('STARTTLS', $modules) && false !== $this->options->get('starttls')) {
            $this->write('STARTTLS');
            if (!$this->read(220, 1024, $error)) {
                throw new \Exception($error);
            }
            if (!stream_socket_enable_crypto($this->socket, true, STREAM_CRYPTO_METHOD_SSLv23_CLIENT)) {
                throw new \Exception(ake(\error_get_last(), 'message'));
            }
        } elseif (true === $this->options->get('starttls')) {
            throw new \Exception('STARTTLS is required but server does not support it.');
        }
        $auth_methods = array_reduce($modules, function ($carry, $item) {
            if ('AUTH' === substr($item, 0, 4)) {
                return array_merge($carry, explode(' ', substr($item, 5)));
            }

            return $carry;
        }, []);
        if ($dsn_active = is_array($this->dsn) && count($this->dsn) > 0 && in_array('DSN', $modules)) {
            $this->dsn = array_map('strtoupper', $this->dsn);
        }
        if ($username = $this->options->get('username')) {
            if (in_array('CRAM-MD5', $auth_methods)) {
                $this->write('AUTH CRAM-MD5');
                if (!$this->read(334, 128, $result)) {
                    throw new \Exception('Server does not want to CRAM-MD5 authenticate. Reason: '.$result);
                }
                $this->write(base64_encode($username.' '.hash_hmac('MD5', base64_decode($result), $this->options->get('password'))));
            } elseif (in_array('LOGIN', $auth_methods)) {
                $this->write('AUTH LOGIN');
                if (!$this->read(334, 128, $result)) {
                    throw new \Exception('Server does not want to LOGIN authenticate. Reason: '.$result);
                }
                if (($prompt = base64_decode($result)) !== 'Username:') {
                    throw new \Exception('Server is broken.  Sent weird username prompt \''.$prompt.'\'');
                }
                $this->write(base64_encode($username));
                if (!$this->read(334, 128, $result)) {
                    throw new \Exception('Server did not request password.  Reason: '.$result);
                }
                $this->write(base64_encode($this->options->get('password')));
            } elseif (in_array('PLAIN', $auth_methods)) {
                $this->write('AUTH PLAIN');
                if (!$this->read(334, 128, $result)) {
                    throw new \Exception('Server does not want to do PLAIN authentication. Reason: '.$result);
                }
                $this->write(base64_encode("\0".$username."\0".$this->options->get('password')));
            } else {
                throw new \Exception('Authentication not possible.  Only CRAM-MD5, LOGIN and PLAIN methods are supported.  Server needs: '.grammatical_implode($auth_methods, 'or'));
            }
            if (!$this->read(235, 1024, $result, $response)) {
                throw new \Exception('SMTP Auth failed: '.$result);
            }
        }
        $this->write("MAIL FROM: {$from[0]}".($dsn_active ? ' RET=HDRS' : ''));
        if (!$this->read(250, 1024, $result)) {
            throw new \Exception('Bad response on mail from: '.$result);
        }
        $rcpt = $recipients['to'];
        if ($cc = ake($recipients, 'cc')) {
            $rcpt = array_merge($rcpt, $cc);
        }
        if ($bcc = ake($recipients, 'bcc')) {
            $rcpt = array_merge($rcpt, $bcc);
        }
        foreach($recipients as $type => $addresses) {
            foreach ($addresses as $x) {
                $this->write("RCPT TO: <{$x[0]}>".($dsn_active ? ' NOTIFY='.implode(',', $this->dsn) : ''));
                if (!$this->read(250, 1024, $result)) {
                    throw new \Exception('Bad response on mail to: '.$result);
                }
                $headers[ucfirst($type)][] = self::encodeEmailAddress($x[0], ake($x, 1));
            }
        }
        $headers['From'] = self::encodeEmailAddress($from[0], ake($from, 1));
        $headers['Subject'] = $subject;
        $this->write('DATA');
        if (!$this->read(354)) {
            throw new \Exception('Server rejected our data!');
        }
        $out = '';
        foreach ($headers as $key => $value) {
            $out .= "{$key}: ".(is_array($value) ? implode(', ', $value) : $value)."\r\n";
        }
        $out .= "\r\n{$message}\r\n.";
        $this->write($out);
        if (!$this->read(250, 1024, $reason)) {
            throw new \Exception('Server rejected our data: '.$reason);
        }
        $this->write('QUIT');
        $this->close();

        return true;
    }

    private function connect()
    {
        if (is_resource($this->socket)) {
            fclose($this->socket);
        }
        $this->socket = @stream_socket_client($this->server.':'.$this->port, $errno, $errstr, $this->readTimeout);
        if (!$this->socket) {
            throw new \Exception('Unable to connect to '.$this->server.':'.$this->port.'. Reason: '.$errstr, $errno);
        }

        return true;
    }

    private function close()
    {
        if (!$this->socket instanceof Socket) {
            return false;
        }
        fclose($this->socket);
        $this->socket = null;

        return true;
    }

    private function write($msg)
    {
        if (!(is_resource($this->socket) && is_string($msg) && strlen($msg) > 0)) {
            return false;
        }

        return fwrite($this->socket, $msg."\r\n", strlen($msg) + 2);
    }

    private function read($code, $len = 1024, &$message = null, &$response = null)
    {
        if (!is_resource($this->socket)) {
            return false;
        }

        $read = [
            $this->socket,
        ];

        $write = null;

        $exempt = null;

        stream_select($read, $write, $exempt, $this->readTimeout, 0);

        if (!(count($read) > 0 && $read[0] == $this->socket)) {
            throw new \Exception('SMTP data receive timeout');
        }
        $response = fread($this->socket, $len);
        if ($this->getMessageCode($response, $message) !== $code) {
            return false;
        }

        return true;
    }

    private function getMessageCode($buf, &$message = null)
    {
        if (!preg_match('/^(\d+)\s+(.+)$/m', $buf, $matches)) {
            return false;
        }
        if (!is_numeric($matches[1])) {
            return false;
        }

        if (isset($matches[2])) {
            $message = $matches[2];
        }

        return intval($matches[1]);
    }
}
