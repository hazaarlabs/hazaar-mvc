# AJAX Streaming

::: danger
This page is tagged as a draft and is a work in progress.  It is not yet complete and may contain errors or inaccuracies.
:::

Streaming data via HTTP is certainly not a new concept, but having it built into the framework definitely makes creating dynamic, fast response websites so much easier.  Normally you mess around with headers and output buffer settings to get this to work.  Then on the client side you have to figure out some way of manging the chunks of data when they are received.  Doing this is in Hazaar MVC is a cinch!

There are two parts to making this work:

* Client initiates the stream from a view using the built-in jQuery $.stream() plugin.
* Application responds with stream data by calling the $controller->stream() method.

That's it!  The framework has taken care of everything else required to make streaming a reality.  Here's how.

## Client Side

The client side is where most of the magic happens.  Streaming HTTP data itself is not that complicated but by using the Hazaar MVC built-int jQuery plugin called $.stream(), we have provided a robust, reliable method of managing your chunks of data.  Basically when you call $controller->stream() on the server side, it pops out in the $.stream().progress() call back on the client.

```html
<script>
    $(document).ready(function(){
        $.stream('http://example.com/stream/test')
            .progress(function(packet){
                console.log('Received: ' + packet);
            })
            .done(function(packet){
                console.log('And we are done!');
            });
    });
</script>
```

What we have done here is initiated a stream with the application server and using callbacks we process received packets and simply dump them out to the browser console.

## Server Side

On the server side we don't really need to do much different than when we use a normal Action controller.

```php
<?php
    StreamController extends \Hazaar\Controller\Action {

        public function test(){

            $this->stream('Starting stream test');

            for($i=1; $i<=10; $i++){

                $this->stream('i=' . $i);

                sleep(1);

            }

        }

    }
?>
```
Now all this example does is sends a bit of text to the client before counting from 1 to 10.  The client browser will see this text appear in the console every second.

Pretty simple ay?