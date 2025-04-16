<?php

namespace Hazaar\HTTP;

/**
 * Represents a early hint link for HTTP/2 or HTTP/3.
 *
 * This class is used to create and manage HTTP Link headers, which are
 * used to provide hints to the client about resources that should be
 * preloaded or prefetched. The Link header is typically used in the
 * response headers of an HTTP request to inform the client about
 * additional resources that may be needed for rendering the page.
 */
class Link
{
    /**
     * The URL or reference of the link.
     *
     * @var null|string the hyperlink reference, or null if not set
     */
    public ?string $href;

    /**
     * The relationship between the current document and the linked resource.
     *
     * @var array<string, string>
     */
    public array $attributes = [];

    public function __construct(?string $href = null, ?string $rel = 'preload')
    {
        $this->href = $href;
        $this->attr('rel', $rel);
    }

    /**
     * Converts the Link object to its string representation.
     *
     * This method generates a string representation of the Link object
     * in the format of an HTTP Link header. The `href` property is used
     * as the main URL, and any additional attributes are appended as
     * key-value pairs.
     *
     * @return string the string representation of the Link object
     */
    public function __toString(): string
    {
        $link = 'Link: <'.$this->href.'>';
        foreach ($this->attributes as $name => $value) {
            $link .= "; {$name}={$value}";
        }

        return $link;
    }

    /**
     * Sets an attribute for the link and returns the current instance.
     *
     * @param string $name  the name of the attribute to set
     * @param string $value the value of the attribute to set
     *
     * @return static returns the current instance for method chaining
     */
    public function attr(string $name, string $value): static
    {
        $this->attributes[$name] = $value;

        return $this;
    }
}
