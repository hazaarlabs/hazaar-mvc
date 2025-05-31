<?php

declare(strict_types=1);

namespace Hazaar\Warlock\Server;

use Hazaar\HTTP\Response;
use Hazaar\Warlock\Enum\ClientType;
use Hazaar\Warlock\Enum\LogLevel;
use Hazaar\Warlock\Enum\PacketType;
use Hazaar\Warlock\Logger;
use Hazaar\Warlock\Protocol;
use Hazaar\Warlock\Protocol\WebSockets;

class Client extends WebSockets implements \Hazaar\Warlock\Interface\Client
{
    public Logger $log;
    // WebSocket specific stuff
    public ?string $address = null;
    public ?int $port = null;

    public string $id;
    public ClientType $type = ClientType::BASIC;

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
        $this->since = time();
        if (is_resource($this->stream)) {
            if ($peer = stream_socket_get_name($this->stream, true)) {
                $peerParts = explode(':', $peer);
                $this->address = $peerParts[0];
                $this->port = (int) $peerParts[1];
                $this->log->write("CLIENT<-CREATE: HOST={$this->address} PORT={$this->port}", LogLevel::DEBUG);
            }
            $this->lastContact = time();
        }
        $this->ping['wait'] = $options['pingWait'] ?? 15;
        $this->ping['pings'] = $options['pingCount'] ?? 5;
    }

    public function __destruct()
    {
        if ($this->address) {
            $this->log->write("CLIENT->DESTROY: HOST={$this->address} PORT={$this->port}", LogLevel::DEBUG);
        } else {
            $this->log->write("CLIENT->DESTROY: CLIENT={$this->id}", LogLevel::DEBUG);
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
            $this->log->write('Unable to parse request while initiating WebSocket handshake!', LogLevel::WARN);

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
            $this->log->write('CLIENT<-PACKET: '.$frame, LogLevel::DECODE);
            $payload = null;
            $time = null;
            $type = Main::$instance->protocol->decode($frame, $payload, $time);
            if ($type) {
                $this->offset = (time() - $time);

                try {
                    if (!$this->processCommand($type, $payload)) {
                        throw new \Exception('Error processing command: '.$type->name, 500);
                    }
                } catch (\Exception $e) {
                    $this->log->write('An error occurred processing the command: '.$type->name, LogLevel::ERROR);
                    $this->log->write("{$e->getMessage()} at {$e->getFile()}({$e->getLine()})", LogLevel::DEBUG);
                    $this->send(PacketType::ERROR, [
                        'reason' => $e->getMessage(),
                        'command' => $type,
                    ]);
                }
            } else {
                $reason = Main::$instance->protocol->getLastError();
                $this->log->write("Protocol error: {$reason}", LogLevel::ERROR);
                $this->send(PacketType::ERROR, [
                    'reason' => $reason,
                ]);
            }
        }
    }

    public function send(PacketType $command, mixed $payload = null): bool
    {
        $packet = Main::$instance->protocol->encode($command, $payload); // Override the timestamp.
        $this->log->write("CLIENT->PACKET: {$packet}", LogLevel::DECODE);
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
            Main::$instance->clientRemove($this->stream);
            stream_socket_shutdown($this->stream, STREAM_SHUT_RDWR);
            $this->log->write("CLIENT->CLOSE: HOST={$this->address} PORT={$this->port} CLIENT={$this->id}", LogLevel::DEBUG);

            return fclose($this->stream);
        }

        return false;
    }

    public function commandUnsubscribe(string $eventID): bool
    {
        $this->log->write("CLIENT<-UNSUBSCRIBE: HOST={$this->address} PORT={$this->port} CLIENT={$this->id}", LogLevel::DEBUG);
        if (($index = array_search($eventID, $this->subscriptions)) !== false) {
            unset($this->subscriptions[$index]);
        }

        return Main::$instance->unsubscribe($this, $eventID);
    }

    public function commandTrigger(string $eventID, mixed $data, bool $echoClient = true): bool
    {
        $this->log->write("CLIENT<-TRIGGER: HOST={$this->address} PORT={$this->port} CLIENT={$this->id}", LogLevel::DEBUG);

        return Main::$instance->trigger($eventID, $data, false === $echoClient ? $this->id : null);
    }

    public function sendEvent(string $eventID, string $triggerID, mixed $data): bool
    {
        if (ClientType::BASIC === $this->type && !in_array($eventID, $this->subscriptions)) {
            $this->log->write("Client {$this->id} is not subscribed to event {$eventID}", LogLevel::WARN);

            return false;
        }
        $packet = [
            'id' => $eventID,
            'trigger' => $triggerID,
            'time' => microtime(true),
            'data' => $data,
        ];

        return $this->send(PacketType::EVENT, $packet);
    }

    public function ping(): bool
    {
        if ((time() - $this->ping['wait']) < $this->ping['last']) {
            return false;
        }
        ++$this->ping['attempts'];
        if ($this->ping['attempts'] > $this->ping['pings']) {
            $this->log->write('Disconnecting client due to lack of PONG!', LogLevel::WARN);
            $this->disconnect();

            return false;
        }
        $this->ping['last'] = time();
        $this->log->write('WEBSOCKET->PING: ATTEMPTS='.$this->ping['attempts'].' LAST='.date('c', $this->ping['last']), LogLevel::DEBUG);

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
        // Check that the path is correct for the Warlock server
        if ('/warlock' != $parts['path']) {
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
        $response = "HTTP/1.1 {$code} ".Response::getText($code).$lf;
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
        $this->log->write('CLIENT->FRAME: '.implode(' ', $this->hexString($frame)), LogLevel::DECODE2);
        $this->log->write("CLIENT->STREAM: BYTES={$len} HOST={$this->address} PORT={$this->port}", LogLevel::DEBUG);
        $bytesSent = @fwrite($this->stream, $frame, $len);
        if (false === $bytesSent) {
            $this->log->write('An error occured while sending to the client. Could be disconnected.', LogLevel::WARN);
            $this->disconnect();

            return false;
        }
        if ($bytesSent != $len) {
            $this->log->write($bytesSent.' bytes have been sent instead of the '.$len.' bytes expected', LogLevel::ERROR);
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
        $this->log->write('CLIENT<-FRAME: '.implode(' ', $this->hexString($frameBuffer)), LogLevel::DECODE2);
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
            $this->log->write('Bad frame received from client. Disconnecting.', LogLevel::ERROR);
            $this->disconnect();

            return false;
        }
        if (true === $opcode) {
            $this->log->write("Websockets fragment frame received from {$this->address}:{$this->port}", LogLevel::WARN);
            $this->payloadBuffer .= $payload;

            return false;
        }
        if (-1 === $opcode) {
            $this->frameBuffer = $frameBuffer;

            return false;
        }
        $this->log->write("CLIENT<-OPCODE: {$opcode}", LogLevel::DECODE2);
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
                    $this->log->write('Got finaly continuation frame but there is no payload in the buffer!?', LogLevel::WARN);
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
                    $this->log->write("WEBSOCKET<-CLOSE: HOST={$this->address} PORT={$this->port} CLIENT={$this->id}", LogLevel::DEBUG);
                    $this->closing = true;
                    $frame = $this->frame('', 'close', false);
                    @fwrite($this->stream, $frame, strlen($frame));
                    $this->log->write("Websockets connection closed to {$this->address}:{$this->port}", LogLevel::NOTICE);
                    $this->disconnect();
                }

                return false;

            case 9: // Ping
                $this->log->write("WEBSOCKET<-PING: HOST={$this->address} PORT={$this->port} CLIENT={$this->id}", LogLevel::DEBUG);
                $frame = $this->frame('', 'pong', false);
                @fwrite($this->stream, $frame, strlen($frame));

                return false;

            case 10: // Pong
                $this->log->write("WEBSOCKET<-PONG: HOST={$this->address} PORT={$this->port} CLIENT={$this->id}", LogLevel::DEBUG);
                $this->pong();

                return false;

            default: // Unknown!
                $this->log->write("Bad opcode received on Websocket connection from {$this->address}:{$this->port}", LogLevel::ERROR);
                $this->disconnect();

                return false;
        }

        return $payload;
    }

    protected function processCommand(PacketType $command, mixed $payload = null): bool
    {
        $this->log->write("CLIENT<-COMMAND: {$command->name} HOST={$this->address} PORT={$this->port} CLIENT={$this->id}", LogLevel::DEBUG);

        switch ($command) {
            case PacketType::NOOP:
                $this->log->write('NOOP: '.print_r($payload, true), LogLevel::INFO);

                return true;

            case PacketType::OK:
                if ($payload) {
                    $this->log->write($payload, LogLevel::INFO);
                }

                return true;

            case PacketType::ERROR:
                $this->log->write($payload, LogLevel::ERROR);

                return true;

            case PacketType::AUTH:
                // return $this->commandAuthorise($payload);
                $this->log->write('Authorisation command not implemented!', LogLevel::WARN);

                return false;

            case PacketType::SUBSCRIBE:
                $filter = (property_exists($payload, 'filter') ? $payload->filter : null);

                return $this->commandSubscribe($payload->id, $filter);

            case PacketType::UNSUBSCRIBE:
                return $this->commandUnsubscribe($payload->id);

            case PacketType::TRIGGER:
                return $this->commandTrigger($payload->id, $payload->data ?? null, $payload->echo ?? false);

            case PacketType::PING:
                return $this->send(PacketType::PONG, $payload);

            case PacketType::PONG:
                if (is_int($payload)) {
                    $tripMs = (microtime(true) - $payload) * 1000;
                    $this->log->write('PONG received in '.$tripMs.'ms', LogLevel::INFO);
                } else {
                    $this->log->write('PONG received with invalid payload!', LogLevel::WARN);
                }

                break;

            case PacketType::LOG:
                return $this->commandLog($payload);

            case PacketType::DEBUG:
                $this->log->write(($payload->type ?? 'Client').': '.($payload->data ?? 'NO DATA'), LogLevel::DEBUG);

                return true;

            case PacketType::STATUS:
                if ($payload) {
                    return $this->commandStatus($payload);
                }

                // no break
            default:
                Main::$instance->processCommand($this, $command, $payload);

                return true;
        }

        return false;
    }

    protected function commandStatus(?\stdClass $payload = null): bool
    {
        $this->log->write('Client sent status but client is not a service!', LogLevel::WARN);

        return false;
    }

    /**
     * @param array<string,mixed> $filter
     */
    protected function commandSubscribe(string $eventID, ?array $filter = null): bool
    {
        $this->log->write("CLIENT<-SUBSCRIBE: HOST={$this->address} PORT={$this->port} CLIENT={$this->id}", LogLevel::DEBUG);
        $this->subscriptions[] = $eventID;
        Main::$instance->subscribe($this, $eventID, $filter);

        return true;
    }

    protected function commandLog(\stdClass $payload): bool
    {
        if (!property_exists($payload, 'msg')) {
            throw new \Exception('Unable to write to log without a log message!');
        }
        $level = $payload->level ?? LogLevel::INFO;
        $name = $payload->name ?? $this->name;
        if (is_array($payload->msg)) {
            foreach ($payload->msg as $msg) {
                $this->commandLog((object) ['level' => $level, 'msg' => $msg, 'name' => $name]);
            }
        } else {
            $this->log->write($payload->msg ?? '--', $level);
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
        $this->log->write("WEBSOCKETS<-HANDSHAKE: HOST={$this->address} PORT={$this->port} CLIENT={$this->id}", LogLevel::DEBUG);
        $responseHeaders = [];
        $results = [];
        $responseCode = $this->acceptHandshake($headers, $responseHeaders, null, $results);
        if (!(array_key_exists('get', $headers) && 101 === $responseCode)) {
            $responseHeaders['Connection'] = 'close';
            $responseHeaders['Content-Type'] = 'text/text';
            $body = $responseCode.' '.Response::getText($responseCode);
            $response = $this->httpResponse($responseCode, $body, $responseHeaders);
            $this->log->write("Handshake failed with code {$body}", LogLevel::WARN);
            @fwrite($this->stream, $response, strlen($response));

            return false;
        }
        if (array_key_exists('UID', $results['url'])) {
            $this->username = base64_decode($results['url']['UID']);
            if (null != $this->username) {
                $this->log->write("USER: {$this->username}", LogLevel::NOTICE);
            }
        }
        $this->log->write("WEBSOCKETS->ACCEPT: HOST={$this->address} PORT={$this->port} CLIENT={$this->id}", LogLevel::DEBUG);
        $response = $this->httpResponse($responseCode, null, $responseHeaders);
        $bytes = strlen($response);
        $result = @fwrite($this->stream, $response, $bytes);
        if (false === $result || $result !== $bytes) {
            return false;
        }
        $initPacket = Main::$instance->protocol->encode(PacketType::INIT, ['CID' => $this->id]);
        if (Main::$instance->protocol->encoded()) {
            $initPacket = base64_encode($initPacket);
        }
        $initFrame = $this->frame($initPacket, 'text', false);
        // If this is NOT a Warlock process request (ie: it's a browser) send the protocol init frame!
        if (!(array_key_exists('x-warlock-php', $headers) && 'true' === $headers['x-warlock-php'])) {
            $this->log->write("CLIENT->INIT: HOST={$this->address} POST={$this->port} CLIENT={$this->id}", LogLevel::DEBUG);
            $this->write($initFrame);
        }
        if (array_key_exists('authorization', $headers)) {
            [$type, $key] = preg_split('/\s+/', $headers['authorization']);
            if ('apikey' !== strtolower($type)) {
                return false;
            }
        // $payload = (object) [
        //     'client_id' => $this->id,
        //     'type' => $type = $headers['x-warlock-client-type'] ?? 'admin',
        //     'access_key' => base64_decode($key),
        // ];
        // if (!$this->commandAuthorise($payload, 'service' === $type)) {
        //     return false;
        // }
        } elseif (array_key_exists('x-cluster-access-key', $headers)) {
            $this->log->write('Cluster manager is disabled!', LogLevel::WARN);
        // Main::$instance->cluster->addPeer($headers, $this);
        } elseif (array_key_exists('x-warlock-agent-id', $headers)) {
            $this->log->write('Agent connecting in!', LogLevel::NOTICE);
            $this->type = ClientType::AGENT;
        }
        $this->log->write("WebSockets connection from {$this->address}:{$this->port}", LogLevel::NOTICE);

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
            $this->log->write('POST: '.$headers['post'], LogLevel::DEBUG);
            if (!array_key_exists('authorization', $headers)) {
                throw new \Exception('Unauthorised', 401);
            }
            [$type, $key] = preg_split('/\s+/', $headers['authorization']);
            if ('apikey' !== strtolower($type)) {
                throw new \Exception('Unacceptable authorization type', 401);
            }
            // $authPayload = (object) [
            //     'client_id' => $this->id,
            //     'type' => 'client',
            //     'access_key' => base64_decode($key),
            // ];
            // if (!$this->commandAuthorise($authPayload, false)) {
            //     throw new \Exception('Unauthorised', 401);
            // }
            $payload = null;
            $time = null;
            $type = Main::$instance->protocol->decode($body, $payload, $time);
            $this->offset = (time() - $time);
            if (!$type) {
                throw new \Exception('Bad request', 400);
            }
            if (PacketType::TRIGGER !== $type) {
                throw new \Exception('Unsupported command type: '.$type->name, 400);
            }
            if (!is_object($payload)) {
                throw new \Exception('Invalid payload for TRIGGER command');
            }
            if (!property_exists($payload, 'id')) {
                throw new \Exception('No event ID specified for TRIGGER command');
            }
            if ($this->commandTrigger($payload->id, $payload->data ?? null, $payload->echo ?? false)) {
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
