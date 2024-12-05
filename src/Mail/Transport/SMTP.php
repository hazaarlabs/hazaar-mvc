<?php

namespace Hazaar\Mail\Transport;

use Hazaar\Mail\Mime\Message;
use Hazaar\Mail\Transport;
use Hazaar\Mail\TransportMessage;

class SMTP extends Transport
{
    private string $server;
    private int $port;
    private mixed $socket;
    private int $readTimeout;

    /**
     * Initialize the SMTP transport.
     *
     * The settings array must contain the following keys:
     *
     * - server: The SMTP server to connect to.
     * - port: The port to connect to.  Default is 25.
     * - timeout: The timeout in seconds to wait for a response from the server.  Default is 5.
     */
    public function init(array $settings): bool
    {
        $config = array_merge(
            [
                'port' => 25,
                'timeout' => 5,
            ],
            $settings
        );
        if (!isset($config['server'])) {
            throw new \Exception('Cannot send mail.  No SMTP mail server is configured!');
        }
        $this->server = $config['server'];
        $this->port = $config['port'];
        $this->readTimeout = $config['timeout'];

        return true;
    }

    public function send(TransportMessage $message): mixed
    {
        $extra_headers = [
            'To' => [],
            'From' => $this->encodeEmail($message->from),
            'Subject' => $message->subject,
        ];
        if ($message->content instanceof Message) {
            $extra_headers = array_merge($message->content->getHeaders(), $extra_headers);
        }
        $this->connect();
        if (false === $this->read(220, 1024, $result)) {
            throw new \Exception('Invalid response on connection: '.$result);
        }
        $this->write('EHLO '.gethostname());
        $response = false;
        if (false === $this->read(250, 65535, $result, $response)) {
            throw new \Exception('Bad response on mail from: '.$result);
        }
        $modules = explode("\r\n", $response);
        foreach ($modules as &$module) {
            if ('250-' === substr($module, 0, 4)) {
                $module = strtoupper(substr($module, 4));
            }
        }
        // Always use STARTTLS unless it has been explicitly disabled.
        if (in_array('STARTTLS', $modules) && false !== $this->options['starttls']) {
            $this->write('STARTTLS');
            if (false === $this->read(220, 1024, $error)) {
                throw new \Exception($error);
            }
            if (false === $this->options['tlsVerify']) {
                stream_context_set_option($this->socket, 'ssl', 'verify_peer', false);
                stream_context_set_option($this->socket, 'ssl', 'verify_peer_name', false);
            }
            if (!@stream_socket_enable_crypto($this->socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new \Exception(ake(\error_get_last(), 'message'));
            }
        } elseif (true === $this->options['starttls']) {
            throw new \Exception('STARTTLS is required but server does not support it.');
        }
        $auth_methods = array_reduce($modules, function ($carry, $item) {
            if ('AUTH' === substr($item, 0, 4)) {
                return array_merge($carry, explode(' ', substr($item, 5)));
            }

            return $carry;
        }, []);
        if ($username = $this->options['username']) {
            if (!($auth_method = strtoupper($this->options['auth'] ?? ''))) {
                if (in_array('CRAM-MD5', $auth_methods)) {
                    $auth_method = 'CRAM-MD5';
                } elseif (in_array('LOGIN', $auth_methods)) {
                    $auth_method = 'LOGIN';
                } elseif (in_array('PLAIN', $auth_methods)) {
                    $auth_method = 'PLAIN';
                } else {
                    throw new \Exception('Authentication not possible.  Only CRAM-MD5, LOGIN and PLAIN methods are supported.  Server needs: '.grammatical_implode($auth_methods, 'or'));
                }
            }

            switch ($auth_method) {
                case 'CRAM-MD5':
                    $this->write('AUTH CRAM-MD5');
                    if (false === $this->read(334, 128, $result)) {
                        throw new \Exception('Server does not want to CRAM-MD5 authenticate. Reason: '.$result);
                    }
                    $this->write(base64_encode($username.' '.hash_hmac('MD5', base64_decode($result), $this->options['password'])));

                    break;

                case 'LOGIN':
                    $this->write('AUTH LOGIN');
                    if (false === $this->read(334, 128, $result)) {
                        throw new \Exception('Server does not want to LOGIN authenticate. Reason: '.$result);
                    }
                    if (($prompt = base64_decode($result)) !== 'Username:') {
                        throw new \Exception('Server is broken.  Sent weird username prompt \''.$prompt.'\'');
                    }
                    $this->write(base64_encode($username));
                    // @phpstan-ignore-next-line
                    if (false === $this->read(334, 128, $result)) {
                        throw new \Exception('Server did not request password.  Reason: '.$result);
                    }
                    $this->write(base64_encode($this->options['password']));

                    break;

                case 'PLAIN':
                    $this->write('AUTH PLAIN');
                    if (false === $this->read(334, 128, $result)) {
                        throw new \Exception('Server does not want to do PLAIN authentication. Reason: '.$result);
                    }
                    $this->write(base64_encode("\0".$username."\0".$this->options['password']));

                    break;

                default:
                    throw new \Exception('Authentication not possible.  Only CRAM-MD5, LOGIN and PLAIN methods are supported.  Server needs: '.grammatical_implode($auth_methods, 'or'));
            }
            if (false === $this->read(235, 1024, $result, $response)) {
                throw new \Exception('SMTP Auth failed: '.$result);
            }
        }
        if ($dsn_active = (count($message->dsn) > 0 && in_array('DSN', $modules))) {
            $message->dsn = array_map('strtoupper', $message->dsn);
        }
        $this->write("MAIL FROM: {$message->from['email']}".($dsn_active ? ' RET=HDRS' : ''));
        if (false === $this->read(250, 1024, $result)) {
            throw new \Exception('Bad response on mail from: '.$result);
        }
        $rcpt_lists = [
            'To' => $message->to,
            'CC' => $message->cc,
            'BCC' => $message->bcc,
        ];
        foreach ($rcpt_lists as $header => $rcpt_list) {
            foreach ($rcpt_list as $x) {
                $extra_headers[$header][] = $this->encodeEmail($x);
                $rcpt = $x['email'];
                $this->write("RCPT TO: <{$rcpt}>".($dsn_active ? ' NOTIFY='.implode(',', $message->dsn) : ''));
                // @phpstan-ignore-next-line
                if (false === $this->read(250, 1024, $result)) {
                    throw new \Exception('Bad response on mail to: '.$result);
                }
            }
        }
        $this->write('DATA');
        if (false === $this->read(354, 1025, $result)) {
            throw new \Exception($result);
        }
        $out = '';
        foreach ($extra_headers as $key => $value) {
            $out .= "{$key}: ".(is_array($value) ? implode(', ', $value) : $value)."\r\n";
        }
        $out .= "\r\n{$message->content}\r\n.";
        $this->write($out);
        if (false === $this->read(250, 1024, $reason)) {
            throw new \Exception('Server rejected our data: '.$reason);
        }
        $this->write('QUIT');
        $this->close();

        return true;
    }

    private function connect(): bool
    {
        if (isset($this->socket) && is_resource($this->socket)) {
            fclose($this->socket);
        }
        $this->socket = @stream_socket_client($this->server.':'.$this->port, $errno, $errstr, $this->readTimeout);
        if (!$this->socket) {
            throw new \Exception('Unable to connect to '.$this->server.':'.$this->port.'. Reason: '.$errstr, $errno);
        }

        return true;
    }

    private function close(): bool
    {
        if (!is_resource($this->socket)) {
            return false;
        }
        fclose($this->socket);
        $this->socket = null;

        return true;
    }

    private function write(string $msg): false|int
    {
        if (!(is_resource($this->socket) && strlen($msg) > 0)) {
            return false;
        }

        return fwrite($this->socket, $msg."\r\n", strlen($msg) + 2);
    }

    private function read(int $code, int $len = 1024, ?string &$message = null, false|string &$response = false): bool
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
        // @phpstan-ignore-next-line
        if (!(count($read) > 0 && $read[0] == $this->socket)) {
            throw new \Exception('SMTP data receive timeout');
        }
        $response = fread($this->socket, $len);
        if ($this->getMessageCode($response, $message) !== $code) {
            return false;
        }

        return true;
    }

    private function getMessageCode(string $buf, ?string &$message = null): bool|int
    {
        if (!preg_match('/^(\d+)\s+(.+)$/m', $buf, $matches)) {
            return false;
        }
        $message = $matches[2];

        return (int) $matches[1];
    }

    /**
     * Encodes an email address for use in SMTP commands.
     *
     * @param array<string> $email
     */
    private function encodeEmail(array $email): string
    {
        return isset($email['name']) && $email['name'] ? $email['name']." <{$email['email']}>" : $email['email'];
    }
}
