<?php

declare(strict_types=1);

namespace Hazaar\Warlock\Server;

use Hazaar\Warlock\Protocol;
use Hazaar\Warlock\Protocol\WebSockets;

class Client extends WebSockets implements \Hazaar\Warlock\Interface\Client
{
    public Logger $log;
    // WebSocket specific stuff
    public ?string $address = null;
    public int $port;

    public string $id;
    public string $type = 'client';

    /**
     * @var null|false|resource
     */
    public mixed $stream = null;
    public bool $closing = false;
    // Buffer for fragmented frames
    public ?string $frameBuffer = null;
    // Buffer for payloads split over multiple frames
    public ?string $payloadBuffer = null;
    // Warlock specific stuff
    public string $applicationName;
    public string $name;
    public ?string $username = null;
    public int $since;
    public int $lastContact = 0;

    /**
     * @var array<string,int>
     */
    public array $ping = [
        'attempts' => 0,
        'last' => 0,
        'retry' => 5,
        'retries' => 3,
    ];

    /**
     * Any detected time offset. This doesn't need to be exact so we don't bother worrying about latency.
     */
    public int $offset = 0;

    /**
     * This is an array of eventID and stream pairs.
     *
     * @var array<string>
     */
    public array $subscriptions = [];

    /**
     * If the client has any child task.
     *
     * @var array<Task>
     */
    public $tasks = [];
    protected int $start;

    /**
     * @param resource     $stream
     * @param array<mixed> $options
     */
    public function __construct(mixed $stream = null, ?array $options = null)
    {
        parent::__construct(['warlock']);
        $this->log = new Logger();
        $this->stream = $stream;
        $this->name = 'SOCKET#'.(int) $stream;
        $this->id = uniqid();
        $this->applicationName = $options['applicationName'] ?? 'warlock';
        $this->since = time();
        if (is_resource($this->stream)) {
            if ($peer = stream_socket_get_name($this->stream, true)) {
                $peerParts = explode(':', $peer);
                $this->address = $peerParts[0];
                $this->port = (int) $peerParts[1];
                $this->log->write(W_DEBUG, "CLIENT<-CREATE: HOST={$this->address} PORT={$this->port}", $this->name);
            }
            $this->lastContact = time();
        }
        $this->ping['wait'] = ake($options, 'pingWait', 15);
        $this->ping['pings'] = ake($options, 'pingCount', 5);
    }

    public function __destruct()
    {
        if ($this->address) {
            $this->log->write(W_DEBUG, "CLIENT->DESTROY: HOST={$this->address} PORT={$this->port}", $this->name);
        } else {
            $this->log->write(W_DEBUG, "CLIENT->DESTROY: CLIENT={$this->id}", $this->name);
        }
    }

    /**
     * Initiates a WebSocket client handshake.
     *
     * @return bool
     */
    public function initiateHandshake(string $request)
    {
        $body = '';
        if (!($headers = $this->parseHeaders($request, $body))) {
            $this->log->write(W_WARN, 'Unable to parse request while initiating WebSocket handshake!', $this->name);

            return false;
        }
        if (array_key_exists('post', $headers)) {
            return $this->initiateRESTHandshake($headers, $body);
        }
        if (array_key_exists('connection', $headers) && preg_match('/upgrade/', strtolower($headers['connection']))) {
            return $this->initiateWebSocketsHandshake($headers);
        }

        return false;
    }

    public function recv(string &$buf): void
    {
        // Record this time as the last time we received data from the client
        $this->lastContact = time();
        /*
         * Sometimes we can get multiple frames in a single buffer so we cycle through
         * them until they are all processed.  This will even allow partial frames to be
         * added to the client frame buffer.
         */
        while ($frame = $this->processFrame($buf)) {
            $this->log->write(W_DECODE, 'CLIENT<-PACKET: '.$frame, $this->name);
            $payload = null;
            $time = null;
            $type = Master::$protocol->decode($frame, $payload, $time);
            if ($type) {
                $this->offset = (time() - $time);

                try {
                    if (!$this->processCommand($type, $payload)) {
                        throw new \Exception('Negative response returned while processing command!');
                    }
                } catch (\Exception $e) {
                    $this->log->write(W_ERR, 'An error occurred processing the command: '.$type, $this->name);
                    $this->log->write(W_DEBUG, "{$e->getMessage()} at {$e->getFile()}({$e->getLine()})", $this->name);
                    $this->send('error', [
                        'reason' => $e->getMessage(),
                        'command' => $type,
                    ]);
                }
            } else {
                $reason = Master::$protocol->getLastError();
                $this->log->write(W_ERR, "Protocol error: {$reason}", $this->name);
                $this->send('error', [
                    'reason' => $reason,
                ]);
            }
        }
    }

    public function send(string $command, mixed $payload = null): bool
    {
        $packet = Master::$protocol->encode($command, $payload); // Override the timestamp.
        $this->log->write(W_DECODE, "CLIENT->PACKET: {$packet}", $this->name);
        $frame = $this->frame($packet, 'text', false);

        return $this->write($frame);
    }

    /**
     * Process a stream client disconnect.
     */
    public function disconnect(): bool
    {
        $this->subscriptions = [];
        if (is_resource($this->stream)) {
            Master::$instance->clientRemove($this->stream);
            stream_socket_shutdown($this->stream, STREAM_SHUT_RDWR);
            $this->log->write(W_DEBUG, "CLIENT->CLOSE: HOST={$this->address} PORT={$this->port} CLIENT={$this->id}", $this->name);

            return fclose($this->stream);
        }

        return false;
    }

    public function commandUnsubscribe(string $eventID): bool
    {
        $this->log->write(W_DEBUG, "CLIENT<-UNSUBSCRIBE: HOST={$this->address} PORT={$this->port} CLIENT={$this->id}", $this->name);
        if (($index = array_search($eventID, $this->subscriptions)) !== false) {
            unset($this->subscriptions[$index]);
        }

        return Master::$instance->unsubscribe($this, $eventID);
    }

    public function commandTrigger(string $eventID, mixed $data, bool $echoClient = true): bool
    {
        $this->log->write(W_DEBUG, "CLIENT<-TRIGGER: HOST={$this->address} PORT={$this->port} CLIENT={$this->id}", $this->name);

        return Master::$instance->trigger($eventID, $data, false === $echoClient ? $this->id : null);
    }

    public function sendEvent(string $eventID, string $triggerID, mixed $data): bool
    {
        if ('peer' !== $this->type && !in_array($eventID, $this->subscriptions)) {
            $this->log->write(W_WARN, "Client {$this->id} is not subscribed to event {$eventID}", $this->name);

            return false;
        }
        $packet = [
            'id' => $eventID,
            'trigger' => $triggerID,
            'time' => microtime(true),
            'data' => $data,
        ];

        return $this->send('EVENT', $packet);
    }

    public function ping(): bool
    {
        if ((time() - $this->ping['wait']) < $this->ping['last']) {
            return false;
        }
        ++$this->ping['attempts'];
        if ($this->ping['attempts'] > $this->ping['pings']) {
            $this->log->write(W_WARN, 'Disconnecting client due to lack of PONG!', $this->name);
            $this->disconnect();

            return false;
        }
        $this->ping['last'] = time();
        $this->log->write(W_DEBUG, 'WEBSOCKET->PING: ATTEMPTS='.$this->ping['attempts'].' LAST='.date('c', $this->ping['last']), $this->name);

        return $this->write($this->frame('', 'ping', false));
    }

    public function pong(): void
    {
        $this->ping['attempts'] = 0;
        $this->ping['last'] = 0;
    }

    /**
     * Overridden method from WebSocket class to check the requested WebSocket URL is valid.
     */
    protected function checkRequestURL(string $url): array|bool
    {
        $parts = parse_url($url);
        // Check that a path was actually sent
        if (!array_key_exists('path', $parts)) {
            return false;
        }
        // Check that the path is correct based on the applicationName constant
        if ($parts['path'] != '/'.$this->applicationName.'/warlock') {
            return false;
        }
        $query = [];
        if (array_key_exists('query', $parts)) {
            parse_str($parts['query'], $query);
        }

        return $query;
    }

    /**
     * Generate an HTTP response message.
     *
     * @param int           $code    HTTP response code
     * @param string        $body    The response body
     * @param array<string> $headers Additional headers
     */
    protected function httpResponse(int $code, ?string $body = null, array $headers = []): string
    {
        $lf = "\r\n";
        $response = "HTTP/1.1 {$code} ".http_response_text($code).$lf;
        $defaultHeaders = [
            'Date' => date('r'),
            'Server' => 'Warlock/2.0 ('.php_uname('s').')',
            'X-Powered-By' => phpversion(),
        ];
        if ($body) {
            $defaultHeaders['Content-Length'] = strlen($body);
        }
        $headers = array_merge($defaultHeaders, $headers);
        foreach ($headers as $key => $value) {
            $response .= $key.': '.$value.$lf;
        }

        return $response.$lf.$body;
    }

    protected function write(string $frame): bool
    {
        if (!is_resource($this->stream)) {
            return false;
        }
        $len = strlen($frame);
        $this->log->write(W_DECODE2, 'CLIENT->FRAME: '.implode(' ', $this->hexString($frame)), $this->name);
        $this->log->write(W_DEBUG, "CLIENT->STREAM: BYTES={$len} HOST={$this->address} PORT={$this->port}", $this->name);
        $bytes_sent = @fwrite($this->stream, $frame, $len);
        if (false === $bytes_sent) {
            $this->log->write(W_WARN, 'An error occured while sending to the client. Could be disconnected.', $this->name);
            $this->disconnect();

            return false;
        }
        if ($bytes_sent != $len) {
            $this->log->write(W_ERR, $bytes_sent.' bytes have been sent instead of the '.$len.' bytes expected', $this->name);
            $this->disconnect();

            return false;
        }

        return true;
    }

    /**
     * Processes a client data frame.
     */
    protected function processFrame(string &$frameBuffer): mixed
    {
        if ($this->frameBuffer) {
            $frameBuffer = $this->frameBuffer.$frameBuffer;
            $this->frameBuffer = null;

            return $this->processFrame($frameBuffer);
        }
        if (!$frameBuffer) {
            return false;
        }
        $this->log->write(W_DECODE2, 'CLIENT<-FRAME: '.implode(' ', $this->hexString($frameBuffer)), $this->name);
        $opcode = $this->getFrame($frameBuffer, $payload);
        /*
         * If we get an opcode that equals false then we got a bad frame.
         *
         * If we get an opcode actually equals true, then the FIN flag was not set so this is a fragmented
         * frame and there wil be one or more coninuation frames.  So, we return false if there are no more
         * frames to process, or true if there are already more frames in the buffer to process.
         *
         * If we get a opcode of -1 then we received only part of the frame and there is more data
         * required to complete the frame.
         */
        if (false === $opcode) {
            $this->log->write(W_ERR, 'Bad frame received from client. Disconnecting.', $this->name);
            $this->disconnect();

            return false;
        }
        if (true === $opcode) {
            $this->log->write(W_WARN, "Websockets fragment frame received from {$this->address}:{$this->port}", $this->name);
            $this->payloadBuffer .= $payload;

            return false;
        }
        if (-1 === $opcode) {
            $this->frameBuffer = $frameBuffer;

            return false;
        }
        $this->log->write(W_DECODE2, "CLIENT<-OPCODE: {$opcode}", $this->name);
        // Save any leftover frame data in the client framebuffer because we got more than a whole frame)
        if (strlen($frameBuffer) > 0) {
            $this->frameBuffer = $frameBuffer;
            $frameBuffer = '';
        }

        // Check the WebSocket OPCODE and see if we need to do any internal processing like PING/PONG, CLOSE, etc.
        switch ($opcode) {
            case 0: // If the opcode is 0, then this is our FIN continuation frame.
                // If we have data in the payload buffer (we absolutely should) then retrieve it here.
                if (!$this->payloadBuffer) {
                    $this->log->write(W_WARN, 'Got finaly continuation frame but there is no payload in the buffer!?');
                }
                $payload = $this->payloadBuffer.$payload;
                $this->payloadBuffer = '';

                break;

            case 1: // Text frame
            case 2: // Binary frame
                // These are our normal frame types which will already be processed into $payload.
                break;

            case 8: // Close frame
                if (false === $this->closing) {
                    $this->log->write(W_DEBUG, "WEBSOCKET<-CLOSE: HOST={$this->address} PORT={$this->port} CLIENT={$this->id}", $this->name);
                    $this->closing = true;
                    $frame = $this->frame('', 'close', false);
                    @fwrite($this->stream, $frame, strlen($frame));
                    if ('client' === $this->type && ($count = count($this->tasks)) > 0) {
                        $this->log->write(W_NOTICE, 'Disconnected WebSocket client has '
                            .$count.' running/pending child task', $this->name);
                        foreach ($this->tasks as $task) {
                            if (true !== $task->detach) {
                                $task->status = TASK_CANCELLED;
                            }
                        }
                    }
                    $this->log->write(W_NOTICE, "Websockets connection closed to {$this->address}:{$this->port}", $this->name);
                    $this->disconnect();
                }

                return false;

            case 9: // Ping
                $this->log->write(W_DEBUG, "WEBSOCKET<-PING: HOST={$this->address} PORT={$this->port} CLIENT={$this->id}", $this->name);
                $frame = $this->frame('', 'pong', false);
                @fwrite($this->stream, $frame, strlen($frame));

                return false;

            case 10: // Pong
                $this->log->write(W_DEBUG, "WEBSOCKET<-PONG: HOST={$this->address} PORT={$this->port} CLIENT={$this->id}", $this->name);
                $this->pong();

                return false;

            default: // Unknown!
                $this->log->write(W_ERR, "Bad opcode received on Websocket connection from {$this->address}:{$this->port}", $this->name);
                $this->disconnect();

                return false;
        }

        return $payload;
    }

    protected function processCommand(string $command, mixed $payload = null): bool
    {
        $this->log->write(W_DEBUG, "CLIENT<-COMMAND: {$command} HOST={$this->address} PORT={$this->port} CLIENT={$this->id}", $this->name);

        switch ($command) {
            case 'NOOP':
                $this->log->write(W_INFO, 'NOOP: '.print_r($payload, true), $this->name);

                return true;

            case 'OK':
                if ($payload) {
                    $this->log->write(W_INFO, $payload, $this->name);
                }

                return true;

            case 'ERROR':
                $this->log->write(W_ERR, $payload, $this->name);

                return true;

            case 'AUTH':
                return $this->commandAuthorise($payload);

            case 'SUBSCRIBE' :
                $filter = (property_exists($payload, 'filter') ? $payload->filter : null);

                return $this->commandSubscribe($payload->id, $filter);

            case 'UNSUBSCRIBE' :
                return $this->commandUnsubscribe($payload->id);

            case 'TRIGGER' :
                return $this->commandTrigger($payload->id, ake($payload, 'data'), ake($payload, 'echo', false));

            case 'PING' :
                return $this->send('pong', $payload);

            case 'PONG':
                if (is_int($payload)) {
                    $trip_ms = (microtime(true) - $payload) * 1000;
                    $this->log->write(W_INFO, 'PONG received in '.$trip_ms.'ms', $this->name);
                } else {
                    $this->log->write(W_WARN, 'PONG received with invalid payload!', $this->name);
                }

                break;

            case 'LOG':
                return $this->commandLog($payload);

            case 'DEBUG':
                $this->log->write(W_DEBUG, ake($payload, 'type', 'Client').': '.ake($payload, 'data', 'NO DATA'), $this->name);

                return true;

            case 'STATUS' :
                if ($payload) {
                    return $this->commandStatus($payload);
                }

                // no break
            default:
                return Master::$instance->processCommand($this, $command, $payload);
        }

        return false;
    }

    protected function commandAuthorise(\stdClass $payload, bool $acknowledge = true): bool
    {
        $this->log->write(W_DEBUG, "CLIENT<-AUTH: OFFSET={$this->offset} HOST={$this->address} PORT={$this->port} CLIENT={$this->id}", $this->name);
        if (!property_exists($payload, 'access_key')) {
            return false;
        }
        if (!Master::$instance->authorise($this, (string) $payload->access_key)) {
            $this->log->write(W_WARN, 'Warlock control rejected to client '.$this->id, $this->name);
            if (true === $acknowledge) {
                $this->send('ERROR');
            }

            return false;
        }
        if (true === $acknowledge) {
            $this->send('OK');
        }
        if ($this->type !== $payload->type) {
            $this->log->write(W_NOTICE, "Client type changed from '{$this->type}' to '{$payload->type}'.", $this->name);
            $this->type = $payload->type;
        }

        return true;
    }

    protected function commandStatus(?\stdClass $payload = null): bool
    {
        $this->log->write(W_WARN, 'Client sent status but client is not a service!', $this->address.':'.$this->port);

        throw new \Exception('Status only allowed for services!');
    }

    /**
     * @param array<string,mixed> $filter
     */
    protected function commandSubscribe(string $eventID, ?array $filter = null): bool
    {
        $this->log->write(W_DEBUG, "CLIENT<-SUBSCRIBE: HOST={$this->address} PORT={$this->port} CLIENT={$this->id}", $this->name);
        $this->subscriptions[] = $eventID;
        Master::$instance->subscribe($this, $eventID, $filter);

        return true;
    }

    protected function commandLog(\stdClass $payload): bool
    {
        if (!property_exists($payload, 'msg')) {
            throw new \Exception('Unable to write to log without a log message!');
        }
        $level = ake($payload, 'level', W_INFO);
        $name = ake($payload, 'name', $this->name);
        if (is_array($payload->msg)) {
            foreach ($payload->msg as $msg) {
                $this->commandLog((object) ['level' => $level, 'msg' => $msg, 'name' => $name]);
            }
        } else {
            $this->log->write($level, ake($payload, 'msg', '--'), $name);
        }

        return true;
    }

    /**
     * Initiates a WebSocket client handshake.
     *
     * @param array<string,string> $headers
     */
    private function initiateWebSocketsHandshake(array $headers): bool
    {
        $this->log->write(W_DEBUG, "WEBSOCKETS<-HANDSHAKE: HOST={$this->address} PORT={$this->port} CLIENT={$this->id}", $this->name);
        $responseHeaders = [];
        $results = [];
        $responseCode = $this->acceptHandshake($headers, $responseHeaders, null, $results);
        if (!(array_key_exists('get', $headers) && 101 === $responseCode)) {
            $responseHeaders['Connection'] = 'close';
            $responseHeaders['Content-Type'] = 'text/text';
            $body = $responseCode.' '.http_response_text($responseCode);
            $response = $this->httpResponse($responseCode, $body, $responseHeaders);
            $this->log->write(W_WARN, "Handshake failed with code {$body}", $this->name);
            @fwrite($this->stream, $response, strlen($response));

            return false;
        }
        if (array_key_exists('UID', $results['url'])) {
            $this->username = base64_decode($results['url']['UID']);
            if (null != $this->username) {
                $this->log->write(W_NOTICE, "USER: {$this->username}", $this->name);
            }
        }
        $this->log->write(W_DEBUG, "WEBSOCKETS->ACCEPT: HOST={$this->address} PORT={$this->port} CLIENT={$this->id}", $this->name);
        $response = $this->httpResponse($responseCode, null, $responseHeaders);
        $bytes = strlen($response);
        $result = @fwrite($this->stream, $response, $bytes);
        if (false === $result || $result !== $bytes) {
            return false;
        }
        $initPacket = Master::$protocol->encode('init', ['CID' => $this->id, 'EVT' => Protocol::$typeCodes]);
        if (Master::$protocol->encoded()) {
            $initPacket = base64_encode($initPacket);
        }
        $initFrame = $this->frame($initPacket, 'text', false);
        // If this is NOT a Warlock process request (ie: it's a browser) send the protocol init frame!
        if (!(array_key_exists('x-warlock-php', $headers) && 'true' === $headers['x-warlock-php'])) {
            $this->log->write(W_DEBUG, "CLIENT->INIT: HOST={$this->address} POST={$this->port} CLIENT={$this->id}", $this->name);
            $this->write($initFrame);
        }
        if (array_key_exists('authorization', $headers)) {
            list($type, $key) = preg_split('/\s+/', $headers['authorization']);
            if ('apikey' !== strtolower($type)) {
                return false;
            }
            $payload = (object) [
                'client_id' => $this->id,
                'type' => $type = ake($headers, 'x-warlock-client-type', 'admin'),
                'access_key' => base64_decode($key),
            ];
            if (!$this->commandAuthorise($payload, 'service' === $type)) {
                return false;
            }
        } elseif (array_key_exists('x-cluster-access-key', $headers)) {
            Master::$instance->cluster->addPeer($headers, $this);
        }
        $this->log->write(W_NOTICE, "WebSockets connection from {$this->address}:{$this->port}", $this->name);

        return true;
    }

    /**
     * Initiates a REST client handshake.
     *
     * @param array<string,string> $headers
     */
    private function initiateRESTHandshake(array $headers, string $body): bool
    {
        try {
            if (false === $this->checkRequestURL($headers['post'])) {
                throw new \Exception('Invalid REST request: '.$headers['post'], 400);
            }
            $this->log->write(W_DEBUG, 'POST: '.$headers['post'], $this->name);
            if (!array_key_exists('authorization', $headers)) {
                throw new \Exception('Unauthorised', 401);
            }
            list($type, $key) = preg_split('/\s+/', $headers['authorization']);
            if ('apikey' !== strtolower($type)) {
                throw new \Exception('Unacceptable authorization type', 401);
            }
            $authPayload = (object) [
                'client_id' => $this->id,
                'type' => 'client',
                'access_key' => base64_decode($key),
            ];
            if (!$this->commandAuthorise($authPayload, false)) {
                throw new \Exception('Unauthorised', 401);
            }
            $payload = null;
            $time = null;
            $type = Master::$protocol->decode($body, $payload, $time);
            $this->offset = (time() - $time);
            if (!$type) {
                throw new \Exception('Bad request', 400);
            }
            if ('TRIGGER' !== $type) {
                throw new \Exception('Unsupported command type: '.$type, 400);
            }
            if (!is_object($payload)) {
                throw new \Exception('Invalid payload for TRIGGER command');
            }
            if (!property_exists($payload, 'id')) {
                throw new \Exception('No event ID specified for TRIGGER command');
            }
            if ($this->commandTrigger($payload->id, ake($payload, 'data'), ake($payload, 'echo', false))) {
                $response = $this->httpResponse(200, 'OK');
            } else {
                throw new \Exception('Bad trigger data', 400);
            }
        } catch (\Exception $e) {
            $response = $this->httpResponse($e->getCode(), $e->getMessage());
        }
        $this->write($response);

        return false;
    }
}
