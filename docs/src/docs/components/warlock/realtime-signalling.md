# Real-Time Signalling

::: danger
This page is tagged as a draft and is a work in progress.  It is not yet complete and may contain errors or inaccuracies.
:::

Hazaar has the ability to send and receive signals in real time to or from a client or server process.  This is done using a variety of techniques that depend on where the signal is coming from or going to.  For example: A server process will use UNIX sockets. A client browser will use WebSockets if supported otherwise it will use long-polling.

::: info
WebSockets can also be used if the client supports it.
See also: [Global Events](global-events.md)
:::