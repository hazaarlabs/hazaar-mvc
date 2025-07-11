# Early Hints in Hazaar Framework

## What are Early Hints?

HTTP 103 Early Hints is a status code defined in [RFC 8297](https://tools.ietf.org/html/rfc8297) that allows servers to send preliminary HTTP headers to clients before the final response is ready. This mechanism enables browsers to begin preloading critical resources while the server is still generating the main response.

> **Note:** Currently, Early Hints support in Hazaar Framework has been tested only with FrankenPHP. Other PHP servers may have varying levels of support or may not support this feature at all.

## Why Use Early Hints?

Early Hints can significantly improve page load performance by:

- Reducing perceived loading time
- Improving Core Web Vitals scores
- Parallelizing resource loading and server processing
- Optimizing the critical rendering path
- Decreasing Time to First Contentful Paint (FCP)

Early Hints are particularly valuable for:
- Resource-heavy applications
- Pages that require complex server-side processing
- Applications where frontend performance is critical

## Using sendEarlyHints in Controllers

The `sendEarlyHints` method allows you to send Link headers to the browser before the main response is ready:

```php
<?php

namespace App\Controller;

use Hazaar\Controller\Action;
use Hazaar\HTTP\Link;

class ProductController extends Action
{
    public function myAction()
    {
        // Create Link objects for resources
        $links = [
            new Link('/css/styles.css', 'preload'),
            new Link('/js/main.js', 'preload')->attr('as', 'script'),
            new Link('https://cdn.example.com', 'preconnect')
        ];
        
        // Send early hints with 103 status code
        $this->sendEarlyHints($links);
        
        // Continue with normal processing...
        return $this->render('my-view');
    }
}
```

### Alternative Approach

You can also create Link objects inline:

```php
<?php

namespace App\Controller;

use Hazaar\Controller\Action;
use Hazaar\HTTP\Link;

class ProductController extends Action
{
    public function myAction()
    {
        $this->sendEarlyHints([
            (new Link('/css/styles.css'))->attr('as', 'style'),
            (new Link('/js/main.js'))->attr('as', 'script'),
            (new Link('/fonts/font.woff2'))->attr('as', 'font')->attr('crossorigin', 'anonymous')
        ]);
        
        // Process and render response...
        return $this->render('my-view');
    }
}
```

## Available 'rel' Attributes

The `rel` attribute defines the relationship between the current document and the linked resource:

| Attribute | Description |
|-----------|-------------|
| `preload` | Tells the browser to download a resource as soon as possible. Default in Link objects. |
| `preconnect` | Establishes early connections to origins (DNS, TCP, TLS) |
| `prefetch` | Suggests the browser should fetch a resource that might be needed for the next navigation |
| `dns-prefetch` | Performs DNS resolution for specified domain in advance |
| `prerender` | Suggests prerendering a page that may be navigated to next |

## Resource Types with 'as' Attribute

When using `preload`, it's important to specify the resource type using the `as` attribute:

| 'as' value | Resource type |
|------------|--------------|
| `style` | CSS stylesheet |
| `script` | JavaScript file |
| `font` | Font file (typically requires `crossorigin` attribute) |
| `image` | Image file |
| `audio` | Audio file |
| `video` | Video file |
| `document` | HTML document |
| `fetch` | Resource to be accessed by fetch or XHR |

## Best Practices and Tips

::: tip
Only preload truly critical resources needed for initial rendering.
:::

::: info
Pair Early Hints with server-side caching for even better performance.
:::

::: warning
Over-preloading resources can negatively impact performance by competing for bandwidth.
:::

::: danger
The `prerender` hint consumes significant client resources and should be used sparingly.
:::

## Browser Compatibility

Early Hints support varies across browsers:
- Chrome: Supported since version 103
- Edge: Supported since version 103
- Firefox: Limited support, behind a flag
- Safari: Limited support in recent versions

Browsers that don't support 103 status codes will simply ignore Early Hints and wait for the final response.

## Fallback Strategy

Always include standard `<link>` elements in your HTML as a fallback for browsers that don't support Early Hints:

```html
<link rel="preload" href="/css/styles.css" as="style">
<link rel="stylesheet" href="/css/styles.css">
```

## Technical Implementation Notes

The Hazaar Framework implementation:

1. Uses the `headers_send()` function with status code 103
2. Requires PHP 8.1+ with a compatible web server (FrankenPHP recommended)
3. Automatically converts `Link` objects to proper HTTP header format
4. Returns `false` if the required functions aren't available

> **Note:** Early Hints will silently fail on unsupported server configurations without affecting the main response.
