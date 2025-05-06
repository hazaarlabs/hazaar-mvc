# Event System

Hazaar includes a simple yet powerful event dispatching system that allows different parts of your application to interact without being tightly coupled. This is achieved through the use of events and listeners.

## Introduction

The core idea is that certain actions within your application (like a user logging in, an order being placed, etc.) can trigger an "event". Other parts of the application can "listen" for these events and react accordingly (e.g., send a welcome email when a user registers, update inventory when an order is placed).

This system supports both immediate event handling and queued event handling. Queued events are useful for tasks that can be deferred, such as sending emails or notifications, which might otherwise slow down the primary user request.

## Defining Events

Events are simple PHP classes that typically hold data related to the event that occurred. To make an event class easily dispatchable, use the `Hazaar\Events\Dispatchable` trait.

```php
<?php

namespace App\Events;

use Hazaar\Events\Dispatchable;

class UserRegisteredEvent
{
    use Dispatchable;

    public string $userId;
    public string $email;

    public function __construct(string $userId, string $email)
    {
        $this->userId = $userId;
        $this->email = $email;
    }
}
```

The `Dispatchable` trait provides a static `dispatch()` method, allowing you to trigger the event like this:

```php
UserRegisteredEvent::dispatch('user-123', 'test@example.com');
```

## Defining Listeners

Listeners are classes that contain the logic to handle specific events. A listener class must have a public `handle` method. The type hint of the first parameter of the `handle` method determines which event the listener will react to.

```php
<?php

namespace App\Listeners;

use App\Events\UserRegisteredEvent;

class SendWelcomeEmailListener
{
    public function handle(UserRegisteredEvent $event): void
    {
        // Logic to send a welcome email
        echo "Sending welcome email to {$event->email} for user {$event->userId}\n";
        // mail($event->email, 'Welcome!', '...');
    }
}

```

When a `UserRegisteredEvent` is dispatched, the `handle` method of `SendWelcomeEmailListener` (if registered) will be called automatically, receiving the `UserRegisteredEvent` object as an argument.

## Registering Listeners

Before a listener can handle an event, it must be registered with the event dispatcher. You can do this manually:

```php
<?php

use Hazaar\Events\Event;
use App\Listeners\SendWelcomeEmailListener;

// Register an instance of the listener
Event::listen(new SendWelcomeEmailListener());

// Or using the EventDispatcher directly
$dispatcher = Hazaar\Events\EventDispatcher::getInstance();
$dispatcher->addListener(new SendWelcomeEmailListener());

```

Alternatively, you can place your listener classes in a specific directory (e.g., `app/Listener`) and the Application will use the `withEvents` method during bootstrap to automatically scan and register them:

This assumes listeners are in the `App\Listener` namespace and filenames match class names (e.g., `SendWelcomeEmailListener.php`).

## Using Queued Listeners

Sometimes, you don't want a listener to execute immediately when an event is dispatched, especially if the listener performs a time-consuming task like sending an email or calling an external API. This could slow down the response to the user.

For these scenarios, you can make a listener "queuable". Queued listeners are executed only when the event queue is explicitly processed, typically at the end of the request lifecycle.

To make a listener queuable, simply implement the `Hazaar\Events\Queuable` interface.

```php
<?php

namespace App\Listeners;

use App\Events\UserRegisteredEvent;
use Hazaar\Events\Queuable; // Import the interface

class SendWelcomeEmailQueuedListener implements Queuable // Implement the interface
{
    public function handle(UserRegisteredEvent $event): void
    {
        // Logic to send a welcome email (will run later)
        echo "Queueing welcome email to {$event->email} for user {$event->userId}\n";
        // Add to an actual background job queue or process later
    }
}
```

Now, when `UserRegisteredEvent::dispatch()` is called, `SendWelcomeEmailQueuedListener::handle()` will *not* execute immediately. The event is added to an internal queue.

### Dispatching Queued Events Manually

To process the event queue manually and execute the `handle` methods of queued listeners, you need to call `Event::dispatchQueue()`. This method can be called in two ways:

1. **Process All Queued Events:** Call `Event::dispatchQueue()` without any arguments to process all events in the queue regardless of their type.

   ```php
   // Process all queued events and clear the entire queue
   Event::dispatchQueue();
   ```

2. **Process Specific Event Types:** Call `Event::dispatchQueue()` with the fully qualified class name of an event to process only events of that specific type.

   ```php
   // Process only UserRegisteredEvent instances in the queue
   Event::dispatchQueue(UserRegisteredEvent::class);
   ```

Here's a complete example demonstrating both approaches:

```php
<?php

use Hazaar\Events\Event;
use App\Events\UserRegisteredEvent;
use App\Events\OrderPlacedEvent;
use App\Listeners\SendWelcomeEmailQueuedListener;
use App\Listeners\UpdateInventoryQueuedListener;

// Register queued listeners
Event::listen(new SendWelcomeEmailQueuedListener());  // Handles UserRegisteredEvent
Event::listen(new UpdateInventoryQueuedListener());   // Handles OrderPlacedEvent

// Dispatch different types of events
$userEvent = UserRegisteredEvent::dispatch('user-123', 'user@example.com');
$orderEvent = OrderPlacedEvent::dispatch('order-456', 99.99);

// At this point, both events are queued but not processed

// Process only user registration events
echo "Processing only user registration events...\n";
Event::dispatchQueue(UserRegisteredEvent::class);
// Now SendWelcomeEmailQueuedListener has processed $userEvent
// But $orderEvent is still in the queue

// Process the remaining events (the order event)
echo "Processing remaining events...\n";
Event::dispatchQueue();
// Now UpdateInventoryQueuedListener has processed $orderEvent
```

The ability to selectively process specific event types can be useful when you want to prioritize certain operations or manage resource usage more carefully. In most cases, however, calling `Event::dispatchQueue()` without arguments at the end of the request lifecycle is the most common pattern.

In a typical Hazaar application, the framework automatically calls `Event::dispatchQueue()` after the response has been sent to the client, ensuring all deferred processing happens without affecting response time.
