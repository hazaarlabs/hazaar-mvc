<?php

declare(strict_types=1);

namespace Hazaar\XML;

/**
 * @brief       Represents an element in an XML document.
 *
 * @detail      XML document creation and parsing has been greatly simplified with the class.  Documents can be created
 *              rapidly and will full support for attributes and namespaces.
 *
 *              ```php
 *              $xml = new Hazaar\Xml\Element('list');
 *              $xml->properties['name'] = 'myProperties';
 *              $xml->properties->add('property', 'Property #1');
 *              $xml->properties->add('property', 'Property #2');
 *              echo $xml;
 *              ```
 *
 *              The above example will output the following XML:
 *
 *              ```xml
 *              <?xml version="1.0" encoding="utf-8" ?>
 *              <list>
 *                  <properties name="myProperties">
 *                     <property>
 *                         Property #1
 *                     </property>
 *                     <property>
 *                          Property #2
 *                      </property>
 *                  </properties>
 *              </list>
 *              ```
 *
 * @implements \ArrayAccess<string, mixed>
 * @implements \Iterator<string, mixed>
 */
class Element implements \ArrayAccess, \Iterator
{
    /**
     * Current supported XML version.
     */
    public const VERSION = '1.0';

    /**
     * Encoding format to use when generating XML as a string.
     */
    public const ENCODING = 'utf-8';

    /**
     * The direct parent object of the current node element.
     */
    public ?Element $__parent = null;
    public string $open_tag = '<';
    public string $close_tag = '>';

    /**
     * The name of the current node element.
     */
    protected ?string $__name = null;

    /**
     * The namespace prefix for the current node element.
     */
    protected ?string $__namespace = null;

    /**
     * The default namespace prefix to use when accessing child members.
     */
    protected ?string $__default_namespace = null;

    /**
     * An array of namespaces defined on this node.
     *
     * @var array<string>
     */
    protected array $__namespaces = [];

    /**
     * An array of attributed defined on this node.
     *
     * @var array<string,string>
     */
    protected array $__attributes = [];

    /**
     * An array to contain all the child nodes of this node element.
     *
     * @var array<Element>
     */
    protected array $__children = [];

    /**
     * @var array<array<Element>|Element>
     */
    protected $__children_index = [];

    /**
     * If there are no child nodes, the value that will used for this node element.
     */
    private mixed $__value;

    /**
     * Toggle to indicate if the Element is being accessed as an array and is reset.
     */
    private bool $__reset = true;

    /**
     * Creates a new \Hazaar\XML\Element object.
     *
     * @param string        $name       The name of the element to create, optionally including a namespace
     * @param mixed         $value      The value of the element.  This can be pretty much anything, including another
     *                                  \Hazaar\Xml\Element object.
     * @param array<string> $namespaces Array of namespaces to declare where the key is the prefix and the value is the
     *                                  namespace URI
     * @param string        $open_tag   Configurable open tag for elements.  XML defines this as '<'.  This can be changed for
     *                                  use with alternative file formats.
     * @param string        $close_tag  Configurable close tag for elements.  XML defines this as '>'.  This can be changed for
     *                                  use with alternative file formats.
     */
    public function __construct(
        ?string $name = null,
        mixed $value = null,
        ?array $namespaces = null,
        string $open_tag = '<',
        string $close_tag = '>'
    ) {
        $this->open_tag = $open_tag;
        $this->close_tag = $close_tag;
        if (null !== $name) {
            $this->setName($name);
        }
        if ($value instanceof Element) {
            $name = $value->getName();
            $this->__children[$name] = $value;
        } else {
            $this->__value = $value;
        }
        if (is_array($namespaces)) {
            $this->__namespaces = $namespaces;
        }
    }

    public function __clone(): void
    {
        if (count($this->__children) > 0) {
            foreach ($this->__children as &$child) {
                $child = clone $child;
            }
        }
    }

    /**
     * Magic method to return the current node element as a string.
     */
    public function __toString(): string
    {
        return $this->toXML();
    }

    /**
     * Magic method to access a child element by it's name.
     *
     * This will automatically create a new child element when accessing a node that does not yet exist.
     *
     * @warning This only works when not working with namespaces.
     *
     * @param string $name the name of the child element to return
     *
     * @return Element the child element being requested
     */
    public function __get($name): ?Element
    {
        if (0 === count($this->__children_index)) {
            return null;
        }
        if ($ns = $this->getDefaultNamespace()) {
            $name = $ns.':'.$name;
        }
        if (array_key_exists($name, $this->__children_index)) {
            return $this->__children_index[$name];
        }

        return $this->child($name);
    }

    /**
     * Magic method to automatically create a new child element on access.
     *
     * @warning This only works when not working with namespaces.
     *
     * @param string $name  the name key of child node to modify
     * @param mixed  $value he value to set
     */
    public function __set(string $name, mixed $value): void
    {
        $this->child($name, $value);
    }

    /**
     * Set the open and close tag characters for parsing and generating XML.
     *
     * By changing the open and close tags we are able to use the \Hazaar\XML\Element class to work with text formats
     * other than XML but that have a similar format.
     *
     * @param string $open_tag  the open tag to set
     * @param string $close_tag the close tag to set
     */
    public function setTagChars(string $open_tag, string $close_tag): void
    {
        $this->open_tag = $open_tag;
        $this->close_tag = $close_tag;
        if (0 === count($this->__children)) {
            return;
        }
        foreach ($this->__children as $element) {
            $element->setTagChars($open_tag, $close_tag);
        }
    }

    /**
     * Sets the default namespace to use when accessing child nodes as members.
     *
     * The namespace MUST be a valid namespace already defined in the objects.  If the namespace is not defined then
     * FALSE will be returned.  The namespace can be referenced with by it's alias or it's URL.  In the latter case
     * the alias will be looked up.  Also, if the URL is not found the shortcut URL will be tried which is the URL
     * with a colon appended (eg: DAV:).
     *
     * @param string $ns the name/url of the namespace to use/prefix as default
     */
    public function setDefaultNamespace(string $ns): bool
    {
        if (!array_key_exists($ns, $this->__namespaces)) {
            $key = null;
            $search = [$ns, $ns.':'];
            foreach ($search as $cur) {
                if ($ns = array_search($cur, $this->__namespaces)) {
                    break;
                }
            }
        }
        if (!$ns) {
            return false;
        }
        $this->__default_namespace = $ns;

        return true;
    }

    /**
     * Returns the default namespace for the current node object.
     *
     * The default namespace does not need to be set on the current object and can be set on a parent object.  This
     * method will look at it's own default namespace and if one is not set it will then request one from the parent.
     */
    public function getDefaultNamespace(): ?string
    {
        if ($this->__default_namespace) {
            return $this->__default_namespace;
        }
        if ($this->__parent instanceof Element) {
            return $this->__parent->getDefaultNamespace();
        }

        return null;
    }

    /**
     * Declare a namespace on the current node element.
     *
     * @param string $prefix a namespace prefix
     * @param string $value  the value of the namespace
     */
    public function addNamespace(string $prefix, string $value): void
    {
        $this->__namespaces[$prefix] = $value;
    }

    /**
     * Test if a namespace is available for the current node element.
     *
     * This is a recursive test, meaning if the namespace isn't defined on the current element then it's parent will be
     * checked.  This is because namespaces, once declared, are available to all child elements of the node the
     * namespace is declared on.
     *
     * @param string $prefix the namespace prefix to check
     */
    public function namespaceExists(string $prefix): bool
    {
        if (!($ret = array_key_exists($prefix, $this->__namespaces))) {
            if ($this->__parent instanceof Element) {
                $ret = $this->__parent->namespaceExists($prefix);
            }
        }

        return $ret;
    }

    /**
     * Set an attribute on the current Hazaar\XML\Element object.
     *
     * @param string $name  the name of the attribute
     * @param string $value the value of the attribute
     */
    public function attr(string $name, string $value): void
    {
        $this->__attributes[$name] = $value;
    }

    /**
     * Return an attribute value.
     *
     * @param string $name The name of the attribute to return
     */
    public function getAttr(string $name): string
    {
        return ake($this->__attributes, $name);
    }

    /**
     * Returns a list of attributes current declared on the node element.
     *
     * @return array<string,string>
     */
    public function attributes(): array
    {
        return $this->__attributes;
    }

    /**
     * Adds a new element to the current node element.
     *
     * @param string        $name       The name of the element to create, optionally including a namespace
     * @param mixed         $value      The value of the element.  This can be pretty much anything, including another
     *                                  \Hazaar\Xml\Element object.
     * @param array<string> $namespaces Array of namespaces to declare where the key is the prefix and the value is the
     *                                  namespace URI
     *
     * @return Element the new child object
     */
    public function add(string $name, mixed $value = null, ?array $namespaces = null): Element
    {
        $child = new Element($name, $value, $namespaces, $this->open_tag, $this->close_tag);
        $child->setParent($this);
        if (!$this->__name && !$this->__parent) {
            $child->__namespaces = $this->__namespaces;
        }
        $this->__children[] = &$child;
        if (array_key_exists($name, $this->__children_index)) {
            if (!is_array($this->__children_index[$name])) {
                $this->__children_index[$name] = [
                    $this->__children_index[$name],
                ];
            }
            $this->__children_index[$name][] = $child;
        } else {
            $this->__children_index[$name] = $child;
        }

        return $child;
    }

    /**
     * Creates and adds child elements to the current XML element from an array.
     *
     * @param string       $name  the name of the current XML element
     * @param array<mixed> $array the array containing the data for creating child elements
     * @param string       $tag   the tag name for the child elements (default: 'child')
     *
     * @return Element the current XML element with added child elements
     */
    public function addFromArray(string $name, array $array, string $tag = 'child'): Element
    {
        $element = $this->add($name);
        foreach ($array as $index => $item) {
            $childName = is_int($index) ? $tag : $index;
            if (is_array($item)) {
                $element->addFromArray($childName, $item, $tag);
            } else {
                $element->add($childName, $item);
            }
        }

        return $element;
    }

    /**
     * Returns a child by it's node name.
     *
     * If the node doesn't exist, then it is automatically added.
     *
     * @param string        $name       The name of the element to create, optionally including a namespace
     * @param mixed         $value      The value of the element.  This can be pretty much anything, including another
     *                                  Hazaar\Xml\Element object.
     * @param array<string> $namespaces Array of namespaces to declare where the key is the prefix and the value is the
     *                                  namespace URI
     *
     * @return array<mixed>|\Hazaar\XML\Element
     */
    public function child(string $name, mixed $value = null, ?array $namespaces = null): array|Element
    {
        if ($ns = $this->getDefaultNamespace()) {
            $name = $ns.':'.$name;
        }
        if (array_key_exists($name, $this->__children_index)) {
            $child = $this->__children_index[$name];
            if (!is_null($value)) {
                $child->value($value);
            }
        } else {
            $child = $this->add($name, $value, $namespaces);
        }

        return $child;
    }

    /**
     * Returns a list of all child elements on the current node element.
     *
     * @return array<Element>
     */
    public function &children(): array
    {
        return $this->__children;
    }

    /**
     * Returns the number of children on the current node element.
     */
    public function count(): int
    {
        return count($this->__children);
    }

    /**
     * Returns the TRUE or FALSE to indicate that the node has child elements.
     */
    public function hasChildren(): bool
    {
        return count($this->__children) > 0;
    }

    /**
     * Get the full valid name of the current node element.
     *
     * This will return the full name which includes the namespace that was originally defined ONLY if the namespace is
     * valid.  Namespaces are valid if they have also been defined with a call to Hazar\Xml\Element::addNamespace() on
     * this element or a parent element.
     *
     * If the namespace is NOT valid, then it is simply ignored.  If the namespace should be returned regardless of it's
     * validity, use the $include_invalid_namespace parameter.
     *
     * @param bool $include_invalid_namespace include namespaces that have no been defined in the current or parent
     *                                        nodes
     */
    public function getName(bool $include_invalid_namespace = false): string
    {
        if ($include_invalid_namespace || ($this->__namespace && $this->namespaceExists($this->__namespace))) {
            return ($this->__namespace ? $this->__namespace.':' : null).$this->__name;
        }

        return $this->__name;
    }

    /**
     * Get the local name of the XML element.
     *
     * @return string the local name of the XML element
     */
    public function getLocalName(): string
    {
        return $this->__name;
    }

    /**
     * Get the namespace of the XML element.
     *
     * @return string the namespace of the XML element
     */
    public function getNamespace(): ?string
    {
        return $this->__namespace;
    }

    /**
     * Get or set the current value of the node element.
     *
     * If the node element does not have any child nodes then it's value can be set directly.  If there are child nodes
     * defined then this value is ignored when generating XML output using the Hazaar\Xml\Element::toXML() method.
     *
     * If the $value parameter is not defined then current value is returned without modification
     *
     * @param string $value The value to set.  If this is null then the value will not be modified.  To empty the value
     *                      set it to an empty string.
     *
     * @return mixed the current value of the element
     */
    public function value(?string $value = null): mixed
    {
        if (null !== $value) {
            $value = trim($value);
            if ($value) {
                $this->__value = $value;
            }
        }

        return $this->__value;
    }

    /**
     * Returns an array of namespaces currently defined on the current node element.
     *
     * Optionally return any namespaces defined anywhere in the data object model.
     *
     * @param bool $recursive recurse through all child node elements and append their namespace declarations to the
     *                        list
     *
     * @return array<string> an array of defined namespaces with keys as the prefix and URIs as the value
     */
    public function getNamespaces(bool $recursive = false): array
    {
        $namespaces = $this->__namespaces;
        if ($recursive && count($this->__children) > 0) {
            foreach ($this->__children as $child) {
                $namespaces = array_merge($namespaces, $child->getNamespaces($recursive));
            }
        }

        return $namespaces;
    }

    /**
     * Output the current XML structure as a string or save to file.
     *
     * This method generates the current XML structure as a returns it as a string resolving all names, namespaces and
     * child elements.
     *
     * @param string $filename optional filename to save XML directly into
     *
     * @return bool|string If saving to file, returns true/false indicating the file write result.  Otherwise returns the XML
     *                     as a string.
     */
    public function toXML(?string $filename = null): bool|string
    {
        $xml = (!$this->__parent ? $this->open_tag.'?xml version="'.$this::VERSION.'" encoding="'.$this::ENCODING.'" ?'.$this->close_tag."\n" : '');
        if ($this->__name) {
            $xml .= $this->open_tag.$this->getName();
            foreach ($this->__attributes as $name => $value) {
                $xml .= ' '.$name.'="'.$value.'"';
            }
            foreach ($this->__namespaces as $prefix => $value) {
                $xml .= ' xmlns:'.$prefix.'="'.$value.'"';
            }
        }
        if (count($this->__children) > 0) {
            if ($this->__name) {
                $xml .= $this->close_tag;
            }
            foreach ($this->__children as $children) {
                $xml .= $this->resolveXML($children);
            }
            if ($this->__name) {
                $xml .= $this->open_tag.'/'.$this->getName().$this->close_tag;
            }
        } elseif ($this->__name) {
            if ($this->__value) {
                $xml .= $this->close_tag.$this->__value.$this->open_tag.'/'.$this->getName().$this->close_tag;
            } else {
                $xml .= ' /'.$this->close_tag;
            }
        }
        if ($filename) {
            return file_put_contents($filename, $xml);
        }

        return $xml;
    }

    /**
     * Load an XML definition from a string.
     *
     * The [[Hazaar\Xml\Element]] class can not only be used to generate XML, but also parse it to allow programmatic
     * access to the data structure.  This method takes a single string argument and attempts to parse it as valid XML.
     *
     * @param string $xml The XML source string
     *
     * @return bool Indicates success or failure
     */
    public function loadXML(string $xml): bool
    {
        $data = '';
        $parents = [];
        $parent = null;
        $child = null;
        $len = strlen($xml);
        for ($i = 0; $i < $len; ++$i) {
            $c = $xml[$i];
            if ($c == $this->open_tag) {
                $node = '';
                $l = null;
                $in_str = false;
                for ($i++; $i < $len; ++$i) {
                    $c = $xml[$i];
                    if ($c == $this->close_tag && !$in_str) {
                        break;
                    }
                    if ('"' == $c && '\\' != $l) {
                        $in_str = !$in_str;
                    } elseif ('!' == $c && !$in_str) {
                        ++$i;
                        if ('--' == substr($xml, $i, 2)) { // It's a comment!
                            $exit = 0;
                            for ($i++; $i < $len; ++$i) {
                                $c = $xml[$i];
                                if ('-' == $c) {
                                    ++$exit;
                                } elseif ($c == $this->close_tag && 2 == $exit) {
                                    break;
                                } else {
                                    $exit = 0;
                                }
                            }
                            ++$i;

                            continue 2;
                        }
                        if ('[CDATA' == substr($xml, $i, 6)) {
                            $i += 6;
                            if ('[' == !$xml[$i]) {
                                continue;
                            }
                            $cdata = '';
                            for ($i++; $i < $len; ++$i) {
                                $c = $xml[$i];
                                if (']' == $c) {
                                    if (substr($xml, $i, strlen($this->close_tag) + 2) == (']]'.$this->close_tag)) {
                                        $i += 3;
                                        if ($parent) {
                                            $parent->value($cdata);
                                        }

                                        continue 2;
                                    }
                                }
                                $cdata .= $c;
                            }
                        }
                    } elseif ((' ' == $c && ' ' == $l) || ($in_str && '\\' == $c)) {
                        continue;
                    }
                    $l = $c;
                    $node .= $c;
                }
                if ('?' == substr($node, 0, 1)) {
                    continue;
                }
                if ('/' == substr($node, 0, 1)) {
                    if ($parent && substr($node, 1) == $parent->getName(true)) {
                        $parent->value($data);
                        $parent = array_pop($parents);
                        $data = '';
                    }
                } else {
                    $parts = preg_split('/(?:\'[^\']*\'|"[^"]*")(*SKIP)(*F)|\h+/', rtrim(str_replace("\n", '', $node), '/ '));
                    if (!$parent) {
                        $this->setName(array_shift($parts));
                        $child = $this;
                    } else {
                        $child = $parent->add(array_shift($parts));
                    }
                    if (count($parts) > 0) {
                        foreach ($parts as $attribute) {
                            if (strpos($attribute, '=')) {
                                list($key, $value) = explode('=', $attribute);
                                if ('xmlns:' == substr($key, 0, 6)) {
                                    $prefix = explode(':', $key)[1];
                                    $child->addNamespace($prefix, trim($value, '"'));
                                } else {
                                    $child[$key] = trim($value, '"');
                                }
                            }
                        }
                    }
                    if ('/' !== substr(trim($node), -1, 1)) {
                        array_push($parents, $parent);
                        $parent = $child;
                    }
                }
            } else {
                $data .= $c;
            }
        }

        return true;
    }

    /**
     * Tests if a child element exists on the current node.
     *
     * @param string $name The name of the child element with optional namespace
     *
     * @return bool true if the child element exists, false otherwise
     */
    public function hasChild(string $name): bool
    {
        return array_key_exists($name, $this->__children_index);
    }

    /**
     * Tests if an attribute exists on the current node.
     *
     * @param string $name The name of the child element with optional namespace
     *
     * @return bool true if the child element exists, false otherwise
     */
    public function hasAttr($name)
    {
        return array_key_exists($name, $this->__attributes);
    }

    /**
     * Searches children to find an element that matches search criteria.
     *
     * @param array<string> $criteria An array of attribute criteria to search on.  Example: ['name' => 'test'] will find elements who have a name attribute with a value of 'test'.
     * @param string        $name     optional node name to filter on
     *
     * @return Element The child element if found.  NULL Otherwise.
     */
    public function find(array $criteria, ?string $name = null): ?Element
    {
        if ($this->count() > 0) {
            foreach (ake($this->__children_index, $name, []) as $nodeName => $child) {
                foreach ($criteria as $key => $value) {
                    if (!($child->hasAttr($key) && $child[$key] == $value)) {
                        continue 2;
                    }
                }

                return $child;
            }
        }

        return null;
    }

    /**
     * Set element attribute.
     *
     * @param mixed $name  the name key of child node to modify
     * @param mixed $value the value to set
     */
    public function offsetSet(mixed $name, mixed $value): void
    {
        $this->attr($name, $value);
    }

    /**
     * Test if element attribute exists.
     *
     * @param string $name the name key of child node to check
     *
     * @return bool TRUE if the child node exists, FALSE otherwise
     */
    public function offsetExists(mixed $name): bool
    {
        return $this->hasAttr($name);
    }

    /**
     * Return an attribute value.
     *
     * @param mixed $name The name of the attribute to return
     *
     * @return mixed the value of the attribute
     */
    public function offsetGet(mixed $name): mixed
    {
        return ake($this->__attributes, $name);
    }

    /**
     * Unset an elements attribute.
     *
     * @param mixed $name the key name of the child to unset
     */
    public function offsetUnset(mixed $name): void
    {
        if (array_key_exists($name, $this->__attributes)) {
            unset($this->__attributes[$name]);
        }
    }

    /**
     * Return the current child element.
     */
    public function current(): mixed
    {
        if (0 === count($this->__children)) {
            return $this->__reset ? $this : null;
        }

        return current($this->__children);
    }

    /**
     * Move to the next child element.
     */
    public function next(): void
    {
        if (0 === count($this->__children)) {
            $this->__reset = false;
        }
        next($this->__children);
    }

    /**
     * Return the key of the current child element.
     */
    public function key(): mixed
    {
        if (0 === count($this->__children)) {
            return $this->__reset ? $this->__name : null;
        }

        return key($this->__children);
    }

    /**
     * Test if the current child element is valid.
     */
    public function valid(): bool
    {
        if (0 === count($this->__children)) {
            return $this->__reset;
        }

        return true;
    }

    /**
     * Reset the internal pointer to the first child element.
     */
    public function rewind(): void
    {
        if (0 === count($this->__children)) {
            $this->__reset = true;
        }
        reset($this->__children);
    }

    /**
     * Returns the child element at the specified index.
     *
     * @param int $index The index of the child element to return
     */
    public function removeIndex(int $index): void
    {
        if (array_key_exists($index, $this->__children)) {
            unset($this->__children[$index]);
        }
    }

    /**
     * Returns the child element at the specified index.
     *
     * @param int $index The index of the child element to return
     */
    public function getIndex(int $index): mixed
    {
        return ake($this->__children, $index);
    }

    /**
     * Sets the name of the current \Hazaar\XML\Element object.
     *
     * Namespaces can be specifed using the colon separator.  eg: 'namespace:name'.
     *
     *
     * ```php
     * $node->setName('C:mynode');
     * ```
     *
     * @param string $name the name to set for the current object, optionally including a namespace
     */
    protected function setName(string $name): void
    {
        if (($ns_sep = strpos($name, ':')) !== false) {
            $this->__namespace = substr($name, 0, $ns_sep);
            $name = substr($name, $ns_sep + 1);
        }
        $this->__name = $name;
    }

    /**
     * Sets the parent object for the current Hazaar\Xml\Element object.
     *
     * This is an internal method and is not accessible outside of the Hazaar\Xml\Element class.
     *
     * @param Element $parent the Hazaar\Xml\Element object to use as the parent
     */
    private function setParent(Element $parent): void
    {
        $this->__parent = $parent;
    }

    /**
     * @param array<Element> $element
     */
    private function resolveXML(array|Element $element): string
    {
        if (is_array($element)) {
            $xml = '';
            foreach ($element as $e) {
                $xml .= $this->resolveXML($e);
            }

            return $xml;
        }

        return $element->toXML();
    }
}
