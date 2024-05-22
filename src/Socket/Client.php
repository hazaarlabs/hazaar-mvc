<?php

declare(strict_types=1);

namespace Hazaar\Socket;

use Hazaar\Application\Protocol;
use Hazaar\Socket\Exception\CreateFailed;

/**
 * The socket client class.
 *
 * The socket client class is used to establish connections via TCP or send data via UDP sockets to another host. It can be implemented as
 * a stand-alone class object and used to send or receive data by polling. It can also be extended and use event handler methods that are
 * triggered when a connection is established, data is received, etc. It's also possible to register event callback methods on the stand-alone
 * object to allow for maximum flexibility in how the class can be used.
 *
 * h3. Example #1 - Standalone Object:
 *
 * This example creates the client as a standalone object.  It requests a connection and then once connected we send some data to the
 * remote server.  After that we wait for $pollInterval milliseconds (default 1000) for data to arrive before dumping it to the output
 * buffer and closing the connection.
 *
 * <pre><code class="php">
 * //Create the socket client object
 * $client = new \Hazaar\Socket\Client();
 *
 * //Connect to the remote host
 * $client->connect('www.google.com.au', 80) or die('Connection failed!');
 *
 * //Send some data
 * $client->send("GET /\n");
 *
 * //Waits for $pollInterval milliseconds for data to arrive
 * if ($client->wait()) {
 *
 *      var_dump($client->recv());
 * }
 *
 * //Close the connection
 * $client->close();
 *
 * exit();
 * </code></pre>
 *
 * h3. Example #2 - Standalone object using callbacks (simple):
 *
 * This example does the same thing as example #1 but using callback methods.
 *
 * <pre><code class="php">
 * //Create the socket client object
 * $client = new \Hazaar\Socket\Client();
 *
 * //Register a connect callback.  This call back will send some data when it's triggered.
 * $client->on('connect', function($client){
 *      $client->send("GET /\n");
 * });
 *
 * //Register a receive data callback.  This call back will dump out the received data and then close the connection.
 * $client->on('recv', function($client, $data){
 *      var_dump($data);
 *      $client->close();
 * });
 *
 * //Connect to the remote host
 * $client->connect('www.google.com.au', 80) or die('Connection failed!');
 *
 * //Run the socket client for 5 seconds.
 * $client->run(5);
 *
 * exit();
 * </code></pre>
 *
 * h3. Example #3 - Standalone object using callbacks (advanced):
 *
 * This example does the same thing as example #2 except instead of just running the client for 5 seconds until data arrives, we register
 * a poll event handler to implement our own timeout code.
 *
 * <pre><code class="php">
 * //Create the socket client object
 * $client = new \Hazaar\Socket\Client();
 *
 * //Register a connect callback.  This call back will send some data when it's triggered.
 * $client->on('connect', function($client){
 *      $client->set('start', time());
 *      $client->send("GET /\n");
 * });
 *
 * //Register a receive data callback.  This call back will dump out the received data
 * //and then close the connection.
 * $client->on('recv', function($client, $data){
 *      var_dump($data);
 *      $client->close();
 * });
 *
 * //Register a poll callback.  This is triggered each time the main run() loop cycles.
 * //Here we wait 5 seconds before returning false to tell the main loop to exit.
 * $client->on('poll', function($client){
 *      if((time() - $client->get('start')) >= 5 )
 *          return false;
 * });
 *
 * //Connect to the remote host
 * $client->connect('www.google.com.au', 80) or die('Connection failed!');
 *
 * //Run the socket client indefinitely.
 * $client->run();
 *
 * exit();
 * </code></pre>
 *
 * h3. Example #4 - Class extension:
 *
 * This example creates the client as a sub-class of \Hazaar\Socket\Client.  This allows you to override the default event handlers
 * and execute your code within the context of the sub-class.
 *
 * <pre><code class="php">
 * class MyGoogleClient extends \Hazaar\Socket\Client {
 *
 *      private $start;
 *      private $timeout = 5;
 *
 *      function __construct() {
 *          parent::__construct();
 *          $this->connect('www.google.com.au', 80) or die('Connection failed!');
 *          $this->run();
 *      }
 *
 *      protected function onConnect() {
 *          echo "Connected to Google!\n";
 *          $this->set('start', time());
 *          $this->send("GET /\n");
 *      }
 *
 *      protected function onRecv($data) {
 *          var_dump($data);
 *          $this->close();
 *      }
 *
 *      protected function onPoll() {
 *          if ((time() - $this->start) >= $this->timeout)
 *              return false;
 *      }
 * }
 *
 * $client = new MyGoogleClient();
 *
 * exit;
 * </code></pre>
 */
class Client
{
    /**
     * @var int The socket protocol.  SOL_TCP or SOL_UDP
     */
    private int $protocol;

    /**
     * @var \Socket The socket resource
     */
    private \Socket $socket;

    /**
     * @var int The length of the read buffer in bytes
     */
    private int $maxBufferSize = 1024;

    /**
     * @var int The timeout used when the client calls socket_select internally when in non-blocking mode
     */
    private int $selectTimeout = 1000;

    /**
     * @var bool Use socket blocking mode
     */
    private bool $blocking = false;

    /**
     * @var bool The current connection state
     */
    private bool $connected = false;

    /**
     * @var array<string, array<\Closure>> An array of registered event handlers
     */
    private array $events = [];

    /**
     * @var array<string,int|string> an array of user accessible variables for use in callbacks
     */
    private array $data = [];

    /**
     * The \Hazaar\Socket\Client constructor.
     *
     * The class can be instantiated without any arguments and defaults to a TCP socket with 1 second polling interval.
     *
     * @param int $protocol one of SOL_TCP, SOL_UDP or SOL_SOCKET
     */
    public function __construct(int $selectTimeout = 1000, int $protocol = SOL_TCP)
    {
        $this->selectTimeout = $selectTimeout;
        $this->protocol = $protocol;
        $this->socket = socket_create(AF_INET, SOCK_STREAM, $protocol);
        if (false === $this->socket) {
            throw new CreateFailed();
        }
    }

    /**
     * Set the socket blocking state.
     *
     * @param bool $state TRUE will set the socket to blocking mode.  FALSE will use non-blocking mode.
     */
    public function setBlocking(bool $state): void
    {
        if ($this->blocking = $state) {
            socket_set_block($this->socket);
        } else {
            socket_set_nonblock($this->socket);
        }
    }

    /**
     * Set the maximum receive buffer size in bytes.
     *
     * This is the size of the buffer used to retrieve data from the socket.  If the buffer is smaller than the amount of data
     * waiting to be retrieved then multiple calls to recv() will be required.
     *
     * @param int $bytes the size of the buffer in bytes
     */
    public function setMaxReceiveBuffer(int $bytes): void
    {
        $this->maxBufferSize = $bytes;
    }

    /**
     * @param int $milliseconds set the timeout used when waiting for data to arrive
     */
    public function setSelectTimeout(int $milliseconds): void
    {
        $this->selectTimeout = $milliseconds;
    }

    /**
     * Returns the remote host address as resolved by the socket connections IP.  This can be different to the host name used
     * to start the connection.
     */
    public function getRemoteHost(): string
    {
        return gethostbyaddr($this->getRemoteIP());
    }

    /**
     * Returns the IP address of the remote host.
     */
    public function getRemoteIP(): string
    {
        socket_getpeername($this->socket, $address);

        return $address;
    }

    /**
     * Return the port number at the remote end of the current connection.
     */
    public function getRemotePort(): int
    {
        $address = null;
        $port = 0;
        socket_getpeername($this->socket, $address, $port);

        return $port;
    }

    /**
     * Get the local IP address of the socket connection.
     */
    public function getLocalIP(): string
    {
        $address = null;
        socket_getsockname($this->socket, $address);

        return $address;
    }

    /**
     * Get the local port number for the current socket connection.
     */
    public function getLocalPort(): int
    {
        $address = null;
        $port = 0;
        socket_getsockname($this->socket, $address, $port);

        return $port;
    }

    /**
     * Initiates a connection to a remote host.
     *
     * @param string $host the remote host to connect to specified as either a resolvable host name or an IP address
     * @param int    $port the port to connect to on the remote host
     *
     * @return bool TRUE if the connnection is successful.  FALSE otherwise.
     */
    public function connect(string $host, int $port): bool
    {
        if (!is_numeric($port)) {
            $port = getservbyname($port, (SOL_TCP == $this->protocol) ? 'tcp' : 'udp');
        }
        $this->connected = @socket_connect($this->socket, $host, $port);
        if (true === $this->connected) {
            $this->onConnect();
        }

        return $this->connected;
    }

    /**
     * Closes the current socket connection.
     *
     * @return bool TRUE if the socket was connected and is now closed.  FALSE if the socket was not already connected.
     */
    public function close(): bool
    {
        if (!$this->connected) {
            return false;
        }
        socket_close($this->socket);
        $this->connected = false;

        return true;
    }

    /**
     * Get the current connection status of the socket.
     *
     * @return bool TRUE if the connection is established.  FALSE otherwise.
     */
    public function isConnected(): bool
    {
        return $this->connected;
    }

    /**
     * Store a key/value pair in the socket client object.
     *
     * This is useful if you are using Closures as callback methods.  Using a Closure will stomp the scope where the Closure is defined
     * meaning you won't have access to variables defined outside of the Closure.  This allows data to be stored in the current client
     * object that can then be accessed later from any other callback method, or from anywhere that has access to the client object.
     *
     * @param string $key   the named key to store the value under
     * @param mixed  $value The value to store.  Can be pretty much anything you want.
     */
    public function set(string $key, mixed $value): void
    {
        $this->data[$key] = $value;
    }

    /**
     * Return a value previously stored using Client::set().
     *
     * @param string $key the key used to store the value
     */
    public function get(string $key): null|int|string
    {
        if (array_key_exists($key, $this->data)) {
            return $this->data[$key];
        }

        return null;
    }

    /**
     * Register an event handler.
     *
     * This method is used to register an event that will be called when an event is triggered.
     *
     * Valid event names are:
     * * *connect* - Called when a socket connection is successfully established
     * * *recv* - Called when data is received
     * * *close* - Called when the socket connection is closed.
     * * *poll* - Called when using the Client::run() method to wait for data.
     *
     * @param string   $event    The name of the event to register the callback on
     * @param \Closure $callback A standard PHP callable.  See: http://au2.php.net/manual/en/language.types.callable.php
     */
    public function on(string $event, \Closure $callback): bool
    {
        if (!is_callable($callback)) {
            return false;
        }
        $event = strtolower($event);
        if (!array_key_exists($event, $this->events)) {
            $this->events[$event] = [];
        }
        $this->events[$event][] = $callback;

        return true;
    }

    /**
     * Send data to the remote host.
     *
     * @return false|int The number of bytes written to the socket.  FALSE if an error has occurred.
     */
    public function send(string $data): false|int
    {
        if (!$this->connected) {
            return false;
        }

        return socket_send($this->socket, $data, strlen($data), 0);
    }

    /**
     * Receive data from the socket.
     *
     * This method will return up to Client::$maxBufferSize bytes of data received on the socket from the remote host.
     *
     * This call will block if blocking mode is enabled.  See: Client::setBlocking()
     */
    public function recv(?int $bytes = null): string
    {
        if (!$bytes) {
            $bytes = $this->maxBufferSize;
        }
        $data = '';
        socket_recv($this->socket, $data, $bytes, 0);

        return $data;
    }

    /**
     * Wait for data to become available for reading on the socket.
     *
     * This method wraps the standard socket_select() system call using the Client::$selectTimeout value.  It will block for
     * Client::$selectTimeout milliseconds or until data is available for reading on the socket.  If blocking mode is enabled
     * however, it will wait indefinitely for data to be available.
     *
     * @param int $timeout If set, specifies the time in milliseconds to wait for data.  If not set uses internal selectTimeout value.
     *
     * @return bool TRUE if data is available.  FALSE otherwise.
     */
    public function wait(?int $timeout = null): bool
    {
        if (!$this->connected) {
            return false;
        }
        $read = [
            $this->socket,
        ];
        $write = null;
        $except = null;
        if (null === $timeout) {
            $timeout = $this->selectTimeout;
        }
        if ($this->blocking) {
            $tvSec = null;
            $tvUsec = null;
        } else {
            $tvSec = (int) floor(($timeout >= 1000) ? $timeout / 1000 : 0);
            $tvUsec = (($timeout >= 1000) ? $timeout - ($tvSec * 1000) : $timeout);
        }
        socket_select($read, $write, $except, $tvSec, $tvUsec);
        if (count($read) > 0) {
            return true;
        }

        return false;
    }

    /**
     * Run the \Hazaar\Socket\Client main loop.
     *
     * Calling this method causes the socket client to execute as though it was it's own application.  By default it will return
     * only once the socket connection is closed, or an onPoll() callback returns false to indicate the main loop should exit (after
     * which a Client::close() should be called separately).
     *
     * An optional timeout can be specified here as will.  This will cause the client 'application' to run, but only for $timeout seconds,
     * allowing a short run process to send and receive a single response easily without hanging the system.
     *
     * @param int $timeout How long to run the main loop for.  This may not end up being exact as it is influenced by execution time
     *                     of any callbacks as well as the Client::$selectTimeout value.
     *
     * @return bool TRUE if the main loop exited cleanly.  FALSE if the socket is not currently connected.
     */
    public function run(?int $timeout = null): bool
    {
        if (!$this->connected) {
            return false;
        }
        $start = time();
        while ($this->connected) {
            if ($this->wait($timeout)) {
                $data = $this->recv();
                $this->onRecv($data);
            }
            $this->onPoll();
            if ($timeout && (time() - $start) >= $timeout) {
                break;
            }
        }

        return true;
    }

    /**
     * Built-in callback method used to handle registered event callbacks.
     */
    protected function onConnect(): void
    {
        if (array_key_exists('connect', $this->events)) {
            $this->triggerEventQueue($this->events['connect'], $this);
        }
    }

    /**
     * Built-in callback method used to handle registered event callbacks.
     */
    protected function onRecv(string $data): void
    {
        if (array_key_exists('revc', $this->events)) {
            $this->triggerEventQueue($this->events['revc'], $this, $data);
        }
    }

    /**
     * Built-in callback method used to handle registered event callbacks.
     */
    protected function onClose(): void
    {
        if (array_key_exists('close', $this->events)) {
            $this->triggerEventQueue($this->events['close'], $this);
        }
    }

    /**
     * Built-in callback method used to handle registered event callbacks.
     */
    protected function onPoll(): void
    {
        if (array_key_exists('poll', $this->events)) {
            $this->triggerEventQueue($this->events['poll'], $this);
        }
    }

    /**
     * Triggers any registered callbacks for the specified event.
     *
     * @param array<\Closure> $queue the event queue of callbacks
     */
    private function triggerEventQueue(array $queue): void
    {
        foreach ($queue as $event) {
            $args = func_get_args();
            array_shift($args);
            call_user_func_array($event, $args);
        }
    }
}
