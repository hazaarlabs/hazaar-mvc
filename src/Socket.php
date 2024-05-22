<?php

declare(strict_types=1);

namespace Hazaar;

/**
 * The PHP socket extensions class.
 *
 * The socket class implements a low-level interface to the socket communication functions based on the popular BSD sockets, providing
 * the possibility to act as a socket server as well as a client.
 *
 * When using this class, it is important to remember that while many of them have identical names to their C counterparts, they often
 * have different declarations. Please be sure to read the descriptions to avoid confusion.
 *
 * Those unfamiliar with socket programming can find a lot of useful material in the appropriate Unix man pages, and there is a great deal
 * of tutorial information on socket programming in C on the web, much of which can be applied, with slight modifications, to socket
 * programming in PHP. The » Unix Socket FAQ might be a good start.
 */
class Socket
{
    protected mixed $resource;

    /**
     * Socket object constructor.
     *
     * @param mixed $domain The domain parameter specifies the protocol family to be used by the socket.
     *
     *        Available address/protocol families:
     *        - *AF_INET*	IPv4 Internet based protocols. TCP and UDP are common protocols of this protocol family.
     *        - *AF_INET6*	IPv6 Internet based protocols. TCP and UDP are common protocols of this protocol family.
     *        - *AF_UNIX*	Local communication protocol family. High efficiency and low overhead make it a great form of IPC (Interprocess
     *        Communication).
     * @param int $type The type parameter selects the type of communication to be used by the socket.
     *
     *        Available socket types:
     *        - *SOCK_STREAM* Provides sequenced, reliable, full-duplex, connection-based byte streams. An out-of-band data transmission
     *        mechanism may be supported. The TCP protocol is based on this socket type.
     *        - *SOCK_DGRAM* Supports datagrams (connectionless, unreliable messages of a fixed maximum length). The UDP protocol is based
     *        on this socket type.
     *        - *SOCK_SEQPACKET* Provides a sequenced, reliable, two-way connection-based data transmission path for datagrams of fixed
     *        maximum length; a consumer is required to read an entire packet with each read call.
     *        - *SOCK_RAW* Provides raw network protocol access. This special type of socket can be used to manually construct any type of
     *        protocol. A common use for this socket type is to perform ICMP requests (like ping).
     *        - *SOCK_RDM* Provides a reliable datagram layer that does not guarantee ordering. This is most likely not implemented on your
     *        operating system.
     * @param int $protocol The protocol parameter sets the specific protocol within the specified domain to be used when communicating
     *                      on the returned socket. The proper value can be retrieved by name by using getprotobyname(). If the desired protocol is
     *                      TCP, or UDP the corresponding constants SOL_TCP, and SOL_UDP can also be used.
     *
     *        Common protocols:
     *        - *icmp* The Internet Control Message Protocol is used primarily by gateways and hosts to report errors in datagram
     *        communication. The "ping" command (present in most modern operating systems) is an example application of ICMP.
     *        - *udp* The User Datagram Protocol is a connectionless, unreliable, protocol with fixed record lengths. Due to these aspects,
     *        UDP requires a minimum amount of protocol overhead.
     *        - *tcp* The Transmission Control Protocol is a reliable, connection based, stream oriented, full duplex protocol. TCP
     *        guarantees that all data packets will be received in the order in which they were sent. If any packet is somehow lost during
     *        communication, TCP will automatically retransmit the packet until the destination host acknowledges that packet. For
     *        reliability and performance reasons, the TCP implementation itself decides the appropriate octet boundaries of the
     *        underlying datagram communication layer. Therefore, TCP applications must allow for the possibility of partial record
     *        transmission.
     */
    public function __construct(mixed $domain = AF_INET, int $type = SOCK_STREAM, int $protocol = SOL_TCP)
    {
        if (!extension_loaded('sockets')) {
            throw new \Exception('The sockets extension is not loaded.');
        }
        if (is_resource($domain) || ($domain && $domain instanceof \Socket)) {
            $this->resource = $domain;
        } elseif (!is_int($domain)) {
            throw new \Exception('Invalid domain type');
        } else {
            $this->resource = socket_create($domain, $type, $protocol);
            if (!$this->resource) {
                throw new \Exception('Unable to create socket');
            }
        }
    }

    public function __destruct()
    {
        $this->close();
    }

    /**
     * Accepts a connection on a socket.
     *
     * After the socket socket has been created using Socket::create(), bound to a name with Socket::bind(), and told to listen for
     * connections with Socket::listen(), this function will accept incoming connections on that socket. Once a successful connection is
     * made, a new socket resource is returned, which may be used for communication. If there are multiple connections queued on the socket,
     * the first will be used. If there are no pending connections, Socket::accept() will block until a connection becomes present. If
     * socket has been made non-blocking using Socket::set_blocking() or Socket::setNonblock(), FALSE will be returned. The socket resource
     * returned by Socket::accept() may not be used to accept new connections. The original listening socket socket, however, remains open
     * and may be reused.
     *
     * @return false|Socket A new socket resource on success, or FALSE on error. The actual error code can be retrieved by calling
     *                      Socket::last_error(). This error code may be passed to Socket::strerror() to get a textual explanation of the error.
     */
    public function accept(): false|Socket
    {
        if (!(is_resource($this->resource) || ($this->resource && $this->resource instanceof \Socket))) {
            return false;
        }

        return new Socket(socket_accept($this->resource));
    }

    /**
     * Binds a name to a socket.
     *
     * The error code can be retrieved with Socket::lastError(). This code may be passed to Socket::strerror() to get a textual
     * explanation of the error.
     *
     * @param string $address If the socket is of the AF_INET family, the address is an IP in dotted-quad notation (e.g. 127.0.0.1).
     * @param int    $port    The port parameter is only used when binding an AF_INET socket, and designates the port on which to listen for
     *                        connections
     *
     * @return bool returns TRUE on success or FALSE on failure
     */
    public function bind(string $address, int $port = 0)
    {
        if (!(is_resource($this->resource) || ($this->resource && $this->resource instanceof \Socket))) {
            return false;
        }

        return socket_bind($this->resource, $address, $port);
    }

    /**
     * Clears the error on the socket or the last error code.
     *
     * This function clears the error code on the given socket or the global last socket error if no socket is specified. This
     * function allows explicitly resetting the error code value either of a socket or of the extension global last error code. This may be
     * useful to detect within a part of the application if an error occurred or not.
     */
    public function clearError(): void
    {
        if (!(is_resource($this->resource) || ($this->resource && $this->resource instanceof \Socket))) {
            return;
        }
        socket_clear_error($this->resource);
    }

    /**
     * Closes a socket resource.
     *
     * Socket::close() closes the socket resource. This function is specific to sockets and cannot be used on any other type of
     * resources.
     */
    public function close(): void
    {
        if (!(is_resource($this->resource) || ($this->resource && $this->resource instanceof \Socket))) {
            return;
        }

        try {
            socket_close($this->resource);
        } catch (\Throwable $e) {
        }
    }

    /**
     * Calculate message buffer size.
     *
     * Calculates the size of the buffer that should be allocated for receiving the ancillary data.
     */
    public function cmsgSpace(int $level, int $type): ?int
    {
        if (!(is_resource($this->resource) || ($this->resource && $this->resource instanceof \Socket))) {
            return null;
        }

        return socket_cmsg_space($level, $type);
    }

    /**
     * Initiates a connection on a socket.
     *
     * @param string $address The address parameter is either an IPv4 address in dotted-quad notation (e.g. 127.0.0.1) if socket is AF_INET, a
     *                        valid IPv6 address (e.g. ::1) if IPv6 support is enabled and socket is AF_INET6 or the pathname of a Unix domain socket,
     *                        if the socket family is AF_UNIX.
     * @param int    $port    The port parameter is only used and is mandatory when connecting to an AF_INET or an AF_INET6 socket, and designates the
     *                        port on the remote host to which a connection should be made
     *
     * @return bool Returns TRUE on success or FALSE on failure. The error code can be retrieved with socket_last_error(). This code may
     *              be passed to socket_strerror() to get a textual explanation of the error.
     */
    public function connect(string $address, int $port = 0): bool
    {
        if (!(is_resource($this->resource) || ($this->resource && $this->resource instanceof \Socket))) {
            return false;
        }

        return socket_connect($this->resource, $address, $port);
    }

    /**
     * Gets socket options for the socket.
     *
     * The Socket::getOption() function retrieves the value for the option specified by the optname parameter for the socket.
     *
     * @param $level   The level parameter specifies the protocol level at which the option resides. For example, to retrieve options at the
     *                 socket level, a level parameter of SOL_SOCKET would be used. Other levels, such as TCP, can be used by specifying the
     *                 protocol number of that level. Protocol numbers can be found by using the getprotobyname() function.
     * @param $optname See http://au1.php.net/manual/en/function.socket-get-option.php
     *
     * @return array<mixed>
     */
    public function getOption(int $level, int $optname): array|false|int
    {
        if (!(is_resource($this->resource) || ($this->resource && $this->resource instanceof \Socket))) {
            return false;
        }

        return socket_get_option($this->resource, $level, $optname);
    }

    /**
     * Queries the remote side of the given socket which may either result in host/port or in a Unix filesystem path, dependent on its type.
     *
     * @param string $address If the given socket is of type AF_INET or AF_INET6, socket_getpeername() will return the peers (remote) IP address in
     *                        appropriate notation (e.g. 127.0.0.1 or fe80::1) in the address parameter and, if the optional port parameter is present,
     *                        also the associated port.
     *
     *                        If the given socket is of type AF_UNIX, socket_getpeername() will return the Unix filesystem path (e.g. /var/run/daemon.sock)
     *                        in the address parameter.
     * @param int $port If given, this will hold the port associated to address
     *
     * @return bool Returns TRUE on success or FALSE on failure. socket_getpeername() may also return FALSE if the socket type is not any
     *              of AF_INET, AF_INET6, or AF_UNIX, in which case the last socket error code is not updated.
     */
    public function getPeerName(string &$address, int &$port): bool
    {
        if (!(is_resource($this->resource) || ($this->resource && $this->resource instanceof \Socket))) {
            return false;
        }

        return socket_getpeername($this->resource, $address, $port);
    }

    /**
     * Queries the local side of the given socket which may either result in host/port or in a Unix filesystem path, dependent on its type.
     *
     * @param string $addr If the given socket is of type AF_INET or AF_INET6, socket_getsockname() will return the local IP address in appropriate
     *                     notation (e.g. 127.0.0.1 or fe80::1) in the address parameter and, if the optional port parameter is present, also the
     *                     associated port.
     *
     *                     If the given socket is of type AF_UNIX, socket_getsockname() will return the Unix filesystem path (e.g. /var/run/daemon.sock)
     *                     in the address parameter.
     * @param int $port If provided, this will hold the associated port
     */
    public function getSockName(string &$addr, int &$port): bool
    {
        if (!(is_resource($this->resource) || ($this->resource && $this->resource instanceof \Socket))) {
            return false;
        }

        return socket_getsockname($this->resource, $addr, $port);
    }

    /**
     * Returns the last error on the socket.
     *
     * If a socket resource is passed to this function, the last error which occurred on this particular socket is returned. If the socket
     * resource is omitted, the error code of the last failed socket function is returned. The latter is particularly helpful for functions
     * like Socket::select() which can fail for reasons not directly tied to a particular socket. The error code is suitable to be fed to
     * Socket::strerror() which returns a string describing the given error code.
     *
     * @return int function returns a socket error code
     */
    public function lastError(): int
    {
        if (!(is_resource($this->resource) || ($this->resource && $this->resource instanceof \Socket))) {
            return -1;
        }

        return socket_last_error($this->resource);
    }

    /**
     * Listens for a connection on a socket.
     *
     * After the socket socket has been created using socket_create() and bound to a name with Socket::bind(), it may be told to listen for
     * incoming connections on socket.
     *
     * Socket::listen() is applicable only to sockets of type SOCK_STREAM or SOCK_SEQPACKET.
     *
     * @param int $backlog A maximum of backlog incoming connections will be queued for processing. If a connection request arrives with the
     *                     queue full the client may receive an error with an indication of ECONNREFUSED, or, if the underlying protocol supports
     *                     retransmission, the request may be ignored so that retries may succeed.
     *
     * @return bool Returns TRUE on success or FALSE on failure. The error code can be retrieved with socket_last_error(). This code may
     *              be passed to socket_strerror() to get a textual explanation of the error.
     */
    public function listen(int $backlog = 0): bool
    {
        if (!(is_resource($this->resource) || ($this->resource && $this->resource instanceof \Socket))) {
            return false;
        }

        return socket_listen($this->resource, $backlog);
    }

    /**
     * Reads a maximum of length bytes from a socket.
     *
     * The function Socket::read() reads from the socket resource socket created by the socket_create() or socket_accept() functions.
     *
     * @param int $length The maximum number of bytes read is specified by the length parameter. Otherwise you can use \r, \n, or \0 to end
     *                    reading (depending on the type parameter, see below).
     * @param int $type   Optional type parameter is a named constant:
     *
     *                    - PHP_BINARY_READ (Default) - use the system recv() function. Safe for reading binary data.
     *                    - PHP_NORMAL_READ - reading stops at \n or \r.
     *
     * @return false|string Socket::read() returns the data as a string on success, or FALSE on error (including if the remote host has closed the
     *                      connection). The error code can be retrieved with Socket::lastError(). This code may be passed to Socket::strerror() to get a
     *                      textual representation of the error.
     */
    public function read(int $length, int $type = PHP_BINARY_READ): false|string
    {
        if (!(is_resource($this->resource) || ($this->resource && $this->resource instanceof \Socket))) {
            return false;
        }

        return socket_read($this->resource, $length, $type);
    }

    /**
     * Receives data from a connected socket.
     *
     * The socket_recv() function receives len bytes of data in buf from socket. socket_recv() can be used to gather data from connected
     * sockets. Additionally, one or more flags can be specified to modify the behaviour of the function.
     *
     * buf is passed by reference, so it must be specified as a variable in the argument list. Data read from socket by socket_recv() will
     * be returned in buf.
     *
     * @param string $buf   The data received will be fetched to the variable specified with buf. If an error occurs, if the connection is reset, or
     *                      if no data is available, buf will be set to NULL.
     * @param int    $len   Up to len bytes will be fetched from remote host
     * @param int    $flags The value of flags can be any combination of the following flags, joined with the binary OR (|) operator.
     *
     *                      Possible values for flags:
     *                      - *MSG_OOB*	Process out-of-band data.
     *                      - *MSG_PEEK*	Receive data from the beginning of the receive queue without removing it from the queue.
     *                      - *MSG_WAITALL*	Block until at least len are received. However, if a signal is caught or the remote host disconnects, the
     *                          function may return less data.
     *                      - *MSG_DONTWAIT*	With this flag set, the function returns even if it would normally have blocked.
     *
     * @return false|int Socket::recv() returns the number of bytes received, or FALSE if there was an error. The actual error code can be
     *                   retrieved by calling socket_last_error(). This error code may be passed to Socket::strerror() to get a textual explanation of
     *                   the error.
     */
    public function recv(string &$buf, int $len, int $flags = 0): false|int
    {
        if (!(is_resource($this->resource) || ($this->resource && $this->resource instanceof \Socket))) {
            return false;
        }

        return socket_recv($this->resource, $buf, $len, $flags);
    }

    /**
     * Receives data from a socket whether or not it is connection-oriented.
     *
     * The Socke::recvFrom() function receives len bytes of data in buf from name on port port (if the socket is not of type AF_UNIX) using
     * socket. Socket::recvFrom() can be used to gather data from both connected and unconnected sockets. Additionally, one or more flags
     * can be specified to modify the behaviour of the function.
     *
     * The name and port must be passed by reference. If the socket is not connection-oriented, name will be set to the internet protocol
     * address of the remote host or the path to the UNIX socket. If the socket is connection-oriented, name is NULL. Additionally, the port
     * will contain the port of the remote host in the case of an unconnected AF_INET or AF_INET6 socket.
     *
     * @param string $buf   The data received will be fetched to the variable specified with buf
     * @param int    $len   Up to len bytes will be fetched from remote host
     * @param int    $flags The value of flags can be any combination of the following flags, joined with the binary OR (|) operator.
     *
     *                      Possible values for flags:
     *                      - *MSG_OOB*	Process out-of-band data.
     *                      - *MSG_PEEK*	Receive data from the beginning of the receive queue without removing it from the queue.
     *                      - *MSG_WAITALL*	Block until at least len are received. However, if a signal is caught or the remote host disconnects, the
     *                       function may return less data.
     *                      - *MSG_DONTWAIT*	With this flag set, the function returns even if it would normally have blocked.
     * @param string $name If the socket is of the type AF_UNIX type, name is the path to the file. Else, for unconnected sockets, name is the IP
     *                     address of, the remote host, or NULL if the socket is connection-oriented.
     * @param int    $port This argument only applies to AF_INET and AF_INET6 sockets, and specifies the remote port from which the data is
     *                     received. If the socket is connection-oriented, port will be NULL.
     *
     * @return false|int Socket::recvFrom() returns the number of bytes received, or FALSE if there was an error. The actual error code can be
     *                   retrieved by calling Socket::lastError(). This error code may be passed to Socket::strerror() to get a textual explanation of
     *                   the error.
     */
    public function recvFrom(string &$buf, int $len, int $flags, string &$name, int &$port = 0): false|int
    {
        if (!(is_resource($this->resource) || ($this->resource && $this->resource instanceof \Socket))) {
            return false;
        }

        return socket_recvfrom($this->resource, $buf, $len, $flags, $name, $port);
    }

    /**
     * Read a message.
     *
     * This function is currently not document!
     *
     * @param array<mixed> $message
     */
    public function recvMsg(array $message, int $flags = 0): false|int
    {
        if (!(is_resource($this->resource) || ($this->resource && $this->resource instanceof \Socket))) {
            return false;
        }

        return socket_recvmsg($this->resource, $message, $flags);
    }

    /**
     * Runs the select() system call on the socket and waits for data to be available for reading.
     *
     * Socket::readSelect() will execute a socket_select call on the socket and wait for data to be available for reading. This is really
     * only useful if you are working with a single socket as it does no allow the select call to wait on multiple sockets.
     *
     * @param int $tv_sec The tv_sec and tv_usec together form the timeout parameter. The timeout is an upper bound on the amount of time
     *                    elapsed before socket_select() return. tv_sec may be zero , causing socket_select() to return immediately. This is useful
     *                    for polling. If tv_sec is NULL (no timeout), socket_select() can block indefinitely.
     *
     * @return bool Returns TRUE if the socket had data to be read. FALSE otherwise.
     */
    public function select(?int $tv_sec, int $tv_usec = 0): bool
    {
        if (!(is_resource($this->resource) || ($this->resource && $this->resource instanceof \Socket))) {
            return false;
        }
        $read = [
            $this->resource,
        ];
        $write = null;
        $exempt = null;
        socket_select($read, $write, $exempt, $tv_sec, $tv_usec);
        if (count($read) > 0 && $read[0] == $this->resource) {
            return true;
        }

        return false;
    }

    /**
     * Runs the select() system call on the socket and waits for data to be available for writing.
     *
     * Socket::readSelect() will execute a socket_select call on the socket and wait for the socket to be available to write to. This is
     * really only useful if you are working with a single socket as it does no allow the select call to wait on multiple sockets.
     *
     * @param int $tv_sec The tv_sec and tv_usec together form the timeout parameter. The timeout is an upper bound on the amount of time
     *                    elapsed before socket_select() return. tv_sec may be zero , causing socket_select() to return immediately. This is useful
     *                    for polling. If tv_sec is NULL (no timeout), socket_select() can block indefinitely.
     *
     * @return bool Returns TRUE if the socket can be written to. FALSE otherwise.
     */
    public function writeSelect(?int $tv_sec, int $tv_usec = 0): bool
    {
        if (!(is_resource($this->resource) || ($this->resource && $this->resource instanceof \Socket))) {
            return false;
        }
        $read = null;
        $write = [
            $this->resource,
        ];
        $exempt = null;
        socket_select($read, $write, $exempt, $tv_sec, $tv_usec);
        if (count($write) > 0 && $write[0] == $this->resource) {
            return true;
        }

        return false;
    }

    /**
     * Sends data to a connected socket.
     *
     * The function socket_send() sends len bytes to the socket socket from buf.
     *
     * @param string $buf   The function socket_send() sends len bytes to the socket socket from buf
     * @param int    $len   The number of bytes that will be sent to the remote host from buf
     * @param int    $flags The value of flags can be any combination of the following flags, joined with the binary OR (|) operator.
     *
     *                      Possible values for flags:
     *                      - *MSG_OOB*	Send OOB (out-of-band) data.
     *                      - *MSG_EOR*	Indicate a record mark. The sent data completes the record.
     *                      - *MSG_EOF*	Close the sender side of the socket and include an appropriate notification of this at the end of the sent data.
     *                      The sent data completes the transaction.
     *                      - *MSG_DONTROUTE*	Bypass routing, use direct interface.
     *
     * @return false|int socket::send() returns the number of bytes sent, or FALSE on error
     */
    public function send(string $buf, int $len, int $flags = 0): false|int
    {
        if (!(is_resource($this->resource) || ($this->resource && $this->resource instanceof \Socket))) {
            return false;
        }

        return socket_send($this->resource, $buf, $len, $flags);
    }

    /**
     * Send a message.
     *
     * @param array<mixed> $message
     */
    public function sendMsg(array $message, int $flags): false|int
    {
        if (!(is_resource($this->resource) || ($this->resource && $this->resource instanceof \Socket))) {
            return false;
        }

        return socket_sendmsg($this->resource, $message, $flags);
    }

    /**
     * Sends a message to a socket, whether it is connected or not.
     *
     * The function socket_sendto() sends len bytes from buf through the socket socket to the port at the address addr.
     *
     * @param string $buf   The sent data will be taken from buffer buf
     * @param int    $len   len bytes from buf will be sent
     * @param int    $flags The value of flags can be any combination of the following flags, joined with the binary OR (|) operator.
     *
     *                      Possible values for flags:
     *                      - *MSG_OOB*	Send OOB (out-of-band) data.
     *                      - *MSG_EOR*	Indicate a record mark. The sent data completes the record.
     *                      - *MSG_EOF*	Close the sender side of the socket and include an appropriate notification of this at the end of the sent data.
     *                      The sent data completes the transaction.
     *                      - *MSG_DONTROUTE*	Bypass routing, use direct interface.
     * @param string $addr IP address of the remote host
     * @param int    $port port is the remote port number at which the data will be sent
     *
     * @return false|int socket::sendto() returns the number of bytes sent to the remote host, or FALSE if an error occurred
     */
    public function sendTo(string $buf, int $len, int $flags, string $addr, int $port = 0): false|int
    {
        if (!(is_resource($this->resource) || ($this->resource && $this->resource instanceof \Socket))) {
            return false;
        }

        return socket_sendto($this->resource, $buf, $len, $flags, $addr, $port);
    }

    /**
     * Sets blocking mode on a socket resource.
     *
     * The socket_set_block() function removes the O_NONBLOCK flag on the socket specified by the socket parameter.
     *
     * When an operation (e.g. receive, send, connect, accept, ...) is performed on a blocking socket, the script will pause its execution
     * until it receives a signal or it can perform the operation.
     *
     * @return bool returns TRUE on success or FALSE on failure
     */
    public function setBlock(): bool
    {
        if (!(is_resource($this->resource) || ($this->resource && $this->resource instanceof \Socket))) {
            return false;
        }

        return socket_set_block($this->resource);
    }

    /**
     * Sets nonblocking mode for file descriptor fd.
     *
     * The socket_set_nonblock() function sets the O_NONBLOCK flag on the socket specified by the socket parameter.
     *
     * When an operation (e.g. receive, send, connect, accept, ...) is performed on a non-blocking socket, the script will not pause its
     * execution until it receives a signal or it can perform the operation. Rather, if the operation would result in a block, the called
     * function will fail.
     *
     * @return bool returns TRUE on success or FALSE on failure
     */
    public function setNonblock(): bool
    {
        if (!(is_resource($this->resource) || ($this->resource && $this->resource instanceof \Socket))) {
            return false;
        }

        return socket_set_nonblock($this->resource);
    }

    /**
     * Sets socket options for the socket.
     *
     * The socket_set_option() function sets the option specified by the optname parameter, at the specified protocol level, to the value
     * pointed to by the optval parameter for the socket.
     *
     * @param int   $level   The level parameter specifies the protocol level at which the option resides. For example, to retrieve options at the
     *                       socket level, a level parameter of SOL_SOCKET would be used. Other levels, such as TCP, can be used by specifying the
     *                       protocol number of that level. Protocol numbers can be found by using the getprotobyname() function.
     * @param int   $optname The available socket options are the same as those for the socket_get_option() function
     * @param mixed $optval  The option value
     *
     * @return bool returns TRUE on success or FALSE on failure
     */
    public function setOption(int $level, int $optname, mixed $optval): bool
    {
        if (!(is_resource($this->resource) || ($this->resource && $this->resource instanceof \Socket))) {
            return false;
        }

        return socket_set_option($this->resource, $level, $optname, $optval);
    }

    /**
     * Shuts down a socket for receiving, sending, or both.
     *
     * The Socket::shutdown() function allows you to stop incoming, outgoing or all data (the default) from being sent through the socket
     *
     * @param int $how The value of how can be one of the following:
     *
     *        Possible values for how:
     *        - *0*	Shutdown socket reading
     *        - *1*	Shutdown socket writing
     *        - *2*	Shutdown socket reading and writing
     *
     * @return bool returns TRUE on success or FALSE on failure
     */
    public function shutdown(int $how = 2): bool
    {
        if (!(is_resource($this->resource) || ($this->resource && $this->resource instanceof \Socket))) {
            return false;
        }

        return socket_shutdown($this->resource, $how);
    }

    /**
     * Return a string describing a socket error.
     *
     * Socket::strerror() takes as its errno parameter a socket error code as returned by Socket::lastError() and returns the corresponding
     * explanatory text.
     *
     * @param int $errno A valid socket error number, likely produced by Socket::lastError()
     *
     * @return string returns the error message associated with the errno parameter
     */
    public function strerror(int $errno): ?string
    {
        if (!(is_resource($this->resource) || ($this->resource && $this->resource instanceof \Socket))) {
            return null;
        }

        return socket_strerror($errno);
    }

    /**
     * Write to a socket.
     *
     * The function socket_write() writes to the socket from the given buffer.
     *
     * @param string $buffer The buffer to be written
     * @param int    $length The optional parameter length can specify an alternate length of bytes written to the socket. If this length is
     *                       greater than the buffer length, it is silently truncated to the length of the buffer.
     *
     * @return false|int Returns the number of bytes successfully written to the socket or FALSE on failure. The error code can be retrieved
     *                   with Socket:lastError(). This code may be passed to Socket::strerror() to get a textual explanation of the error.
     */
    public function write(string $buffer, int $length = 0): false|int
    {
        if (!(is_resource($this->resource) || ($this->resource && $this->resource instanceof \Socket))) {
            return false;
        }

        return socket_write($this->resource, $buffer, $length);
    }
}
