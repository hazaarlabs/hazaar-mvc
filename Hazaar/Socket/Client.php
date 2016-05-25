<?php

namespace Hazaar\Socket;

/**
 * The socket client class
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
 *      public function __construct() {
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
 *
 * @since 2.0.0
 */
class Client {

    /**
     * @var int The socket protocol
     */
    private $protocol;

    /**
     * @var resource The socket resource
     */
    private $socket;

    /**
     *
     * @var int The length of the read buffer in bytes
     */
    private $maxBufferSize = 1024;

    /**
     * @var The timeout used when the client calls socket_select internally when in non-blocking mode
     */
    private $select_timeout = 1000;

    /**
     * @var boolean Use socket blocking mode
     */
    private $blocking = FALSE;

    /**
     * @var boolean The current connection state
     */
    private $connected = FALSE;

    /**
     * @var array An array of registered event handlers
     */
    private $events = array();

    /**
     * @var array An array of user accessible variables for use in callbacks.
     */
    private $data = array();

    /**
     * The \Hazaar\Socket\Client constructor
     *
     * The class can be instantiated without any arguments and defaults to a TCP socket with 1 second polling interval.
     *
     * @param integer $pollInterval The polling interval when used when waiting for data to be received.  This affects the frequency
     *      by which the onPoll callback is executed.
     *
     * @param integer $protocol One of SOL_TCP, SOL_UDP or SOL_SOCKET.
     *
     * @throws Exception\CreateFailed
     */
    function __construct($pollInterval = 1000, $protocol = SOL_TCP) {

        $this->protocol = $protocol;

        $this->socket = socket_create(AF_INET, SOCK_STREAM, $protocol);

        if($this->socket === FALSE)
            throw new Exception\CreateFailed($this->socket);

        socket_set_block($this->socket);

    }

    /**
     * Set the socket blocking state.
     *
     * @param boolean $state TRUE will set the socket to blocking mode.  FALSE will use non-blocking mode.
     */
    public function setBlocking($state) {

        $this->blocking = $state;

    }

    /**
     * Set the maximum receive buffer size in bytes.
     *
     * This is the size of the buffer used to retrieve data from the socket.  If the buffer is smaller than the amount of data
     * waiting to be retrieved then multiple calls to recv() will be required.
     *
     * @param integer $bytes The size of the buffer in bytes.
     */
    public function setMaxReceiveBuffer($bytes) {

        $this->maxBufferSize = $bytes;

    }

    /**
     *
     * @param ineteger $milliseconds Set the timeout used when waiting for data to arrive.
     */
    public function setSelectTimeout($milliseconds) {

        $this->select_timeout = $usec;

    }

    /**
     * Returns the remote host address as resolved by the socket connections IP.  This can be different to the host name used
     * to start the connection.
     *
     * @return string
     */
    public function getRemoteHost() {

        return gethostbyaddr($this->getRemoteIP());

    }

    /**
     * Returns the IP address of the remote host.
     *
     * @return string
     */
    public function getRemoteIP() {

        socket_getpeername($this->socket, $address);

        return $address;

    }

    /**
     * Return the port number at the remote end of the current connection.
     *
     * @return integer
     */
    public function getRemotePort() {

        $address = NULL;

        $port = 0;

        socket_getpeername($this->socket, $address, $port);

        return $port;

    }

    /**
     * Get the local IP address of the socket connection
     *
     * @return string
     */
    public function getLocalIP() {

        $address = NULL;

        socket_getsockname($this->socket, $address);

        return $address;

    }

    /**
     * Get the local port number for the current socket connection.
     *
     * @return integer
     */
    public function getLocalPort() {

        $address = NULL;

        $port = 0;

        socket_getsockname($this->socket, $address, $port);

        return $port;

    }

    /**
     * Initiates a connection to a remote host.
     *
     * @param string $host The remote host to connect to specified as either a resolvable host name or an IP address.
     *
     * @param integer $port The port to connect to on the remote host.
     *
     * @return boolean TRUE if the connnection is successful.  FALSE otherwise.
     */
    public function connect($host, $port) {

        if(! is_numeric($port))
            $port = getservbyname($port, ($this->protocol == SOL_TCP) ? 'tcp' : 'udp');

        $this->connected = socket_connect($this->socket, $host, $port);

        if($this->connected === TRUE)
            $this->onConnect();

        return $this->connected;

    }

    /**
     * Closes the current socket connection.
     *
     * @return boolean TRUE if the socket was connected and is now closed.  FALSE if the socket was not already connected.
     */
    public function close() {

        if(! $this->connected)
            return FALSE;

        socket_close($this->socket);

        $this->connected = FALSE;

        return TRUE;

    }

    /**
     * Get the current connection status of the socket.
     *
     * @return boolean TRUE if the connection is established.  FALSE otherwise.
     */
    public function isConnected() {

        return $this->connected;

    }

    /**
     * Store a key/value pair in the socket client object.
     *
     * This is useful if you are using Closures as callback methods.  Using a Closure will stomp the scope where the Closure is defined
     * meaning you won't have access to variables defined outside of the Closure.  This allows data to be stored in the current client
     * object that can then be accessed later from any other callback method, or from anywhere that has access to the client object.
     *
     * @param string $key The named key to store the value under.
     *
     * @param mixed $value The value to store.  Can be pretty much anything you want.
     */
    public function set($key, $value) {

        $this->data[$key] = $value;

    }

    /**
     * Return a value previously stored using Client::set()
     *
     * @param string $key The key used to store the value.
     *
     * @return mixed
     */
    public function get($key) {

        if(array_key_exists($key, $this->data))
            return $this->data[$key];

        return NULL;

    }

    /**
     * Register an event handler
     *
     * This method is used to register an event that will be called when an event is triggered.
     *
     * Valid event names are:
     * * *connect* - Called when a socket connection is successfully established
     * * *recv* - Called when data is received
     * * *close* - Called when the socket connection is closed.
     * * *poll* - Called when using the Client::run() method to wait for data.
     *
     * @param string $event The name of the event to register the callback on
     *
     * @param callable $callback A standard PHP callable.  See: http://au2.php.net/manual/en/language.types.callable.php
     *
     * @return boolean
     */
    public function on($event, $callback) {

        if(! is_callable($function))
            return FALSE;

        $event = strtolower($event);

        if(! array_key_exists($event, $this->events) || ! is_array($this->events[$event]))
            $this->events[$event] = array();

        $this->events[$event][] = $function;

        return TRUE;

    }

    /**
     * Built-in callback method used to handle registered event callbacks
     */
    protected function onConnect() {

        if(array_key_exists('connect', $this->events))
            return $this->triggerEventQueue($this->events['connect'], $this);

    }

    /**
     * Built-in callback method used to handle registered event callbacks
     */
    protected function onRecv($data) {

        if(array_key_exists('revc', $this->events))
            return $this->triggerEventQueue($this->events['revc'], $this, $data);

    }

    /**
     * Built-in callback method used to handle registered event callbacks
     */
    protected function onClose() {

        if(array_key_exists('close', $this->events))
            return $this->triggerEventQueue($this->events['close'], $this);

    }

    /**
     * Built-in callback method used to handle registered event callbacks
     */
    protected function onPoll() {

        if(array_key_exists('poll', $this->events))
            return $this->triggerEventQueue($this->events['poll'], $this);

    }

    /**
     * Triggers any registered callbacks for the specified event.
     *
     * @param array $queue The event queue of callbacks.
     */
    private function triggerEventQueue($queue) {

        if(! is_array($queue))
            return FALSE;

        foreach($queue as $event) {

            $args = func_get_args();

            array_shift($args);

            return call_user_func_array($event, $args);
        }

    }

    /**
     * Send data to the remote host
     *
     * @param string $data
     *
     * @return mixed The number of bytes written to the socket.  FALSE if an error has occurred.
     */
    public function send($data) {

        if(! $this->connected)
            return FALSE;

        return socket_send($this->socket, $data, strlen($data), 0);

    }

    /**
     * Receive data from the socket.
     *
     * This method will return up to Client::$maxBufferSize bytes of data received on the socket from the remote host.
     *
     * This call will block if blocking mode is enabled.  See: Client::setBlocking()
     *
     * @return string
     */
    public function recv() {

        $data = '';

        $flags = ($this->blocking ? NULL : MSG_DONTWAIT);

        socket_recv($this->socket, $data, $this->maxBufferSize, $flags);

        return $data;

    }

    /**
     * Wait for data to become available for reading on the socket.
     *
     * This method wraps the standard socket_select() system call using the Client::$select_timeout value.  It will block for
     * Client::$select_timeout milliseconds or until data is available for reading on the socket.  If blocking mode is enabled
     * however, it will wait indefinitely for data to be available.
     *
     * @param int $timeout If set, specifies the time in milliseconds to wait for data.  If not set uses internal select_timeout value.
     *
     * @return boolean TRUE if data is available.  FALSE otherwise.
     */
    public function wait($timeout = NULL) {

        if(! $this->connected)
            return FALSE;

        $read = array(
            $this->socket
        );

        $write = NULL;

        $except = NULL;

        if($timeout === NULL)
            $timeout = $this->select_timeout;

        if($this->blocking) {

            $tv_sec = NULL;

            $tv_usec = NULL;

        } else {

            $tv_sec = floor((($timeout >= 1000) ? $timeout / 1000 : 0));

            $tv_usec = (($timeout >= 1000) ? $timeout - ($tv_sec * 1000) : $timeout);

        }

        socket_select($read, $write, $except, $tv_sec, $tv_usec);

        if(count($read) > 0)
            return TRUE;

        return FALSE;

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
     * @param integer $timeout How long to run the main loop for.  This may not end up being exact as it is influenced by execution time
     *      of any callbacks as well as the Client::$select_timeout value.
     *
     * @return boolean TRUE if the main loop exited cleanly.  FALSE if the socket is not currently connected.
     */
    public function run($timeout = NULL) {

        if(! $this->connected)
            return FALSE;

        $start = time();

        while($this->connected) {

            if($this->wait($timeout)) {

                $data = $this->recv();

                $this->onRecv($data);
            }

            if($this->onPoll() === FALSE)
                break;

            if($timeout && (time() - $start) >= $timeout)
                break;
        }

        return TRUE;

    }

}
