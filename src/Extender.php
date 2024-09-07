<?php

declare(strict_types=1);

namespace Hazaar;

/**
 * @brief       The Class Extender Class
 *
 * @detail      The Extender Class allows (or simulates) multiple class inheritance in PHP.  It does this
 *              by loading instances of the extended class under the covers and transparently passing non-existent
 *              method calls on to 'child' classes (if the method exist).  It will also work with member variables
 *              and honors the private, protected and public variable definitions.
 */
abstract class Extender
{
    /**
     * @var array<string, object>
     */
    private array $children = [];

    /**
     * @var array<string, array{0: string, 1: \ReflectionMethod}>
     */
    private array $methods = [];

    /**
     * @var array<string, array{0: string, 1: \ReflectionProperty}>
     */
    private array $properties = [];

    /**
     * @detail      This method call router will route the method call to the first child class that was
     *              found to have the requested method.  If the method does not exist an \Exception is thrown.
     *
     * @param string       $method Then name of the method being called
     * @param array<mixed> $args   An array of arguments from the method call
     *
     * @return mixed The returned data from the child method
     *
     * @throws \Exception
     */
    public function __call(string $method, array $args = [])
    {
        if (array_key_exists($method, $this->methods)
            || array_key_exists('__call', $this->methods)) {
            if (array_key_exists('__call', $this->methods)) {
                $args = [$method, $args];
                $method = '__call';
            }
            list($class, $rm) = $this->methods[$method];

            /**
             * Checks if the method is public, and if not, determines if the calling class is an instance of the current class.
             *
             * @param ReflectionMethod $rm the reflection method object representing the method being checked
             *
             * @return bool true if the method is public or if the calling class is an instance of the current class, false otherwise
             */
            if (!($call = $rm->isPublic())) {
                $trace = debug_backtrace();
                $callingClass = $trace[1]['class'];
                $call = (!$rm->isPrivate() && $this->instanceof($callingClass));
            }

            /**
             * Invokes a method on the child object with the given arguments.
             *
             * @param bool             $call  whether to invoke the method or not
             * @param ReflectionMethod $rm    the reflection method object
             * @param string           $class the class name
             * @param array            $args  the arguments to pass to the method
             *
             * @return mixed the result of the method invocation
             */
            if ($call) {
                return $rm->invokeArgs($this->children[$class], $args);
            }
        }

        throw new Exception\MethodUndefined(get_class($this), $method);
    }

    /**
     * @detail      Pass through method for converting the element to a string.  If one of the child classes
     *              had a __tostring() method then this method is called on the first child that has it.
     */
    public function __toString(): string
    {
        if (array_key_exists('__tostring', $this->methods)) {
            return self::__call('__tostring');
        }

        return '';
    }

    /**
     * @detail      Get a property from the instantiated class object for properties that do not exist.  This will
     *              return the value of a property if it exists in a child class.  This method honors the private,
     *              protected and public variable declarations.
     *
     * @param string $property The property being requested
     */
    public function __get($property): mixed
    {
        if (array_key_exists($property, $this->properties)) {
            list($class, $rp) = $this->properties[$property];
            if (!($return = $rp->isPublic())) {
                $trace = debug_backtrace();
                if ($rp->isPrivate()) {
                    throw new Exception\ExtenderAccessFailed('private', get_class($this), $property);
                }
                if (array_key_exists('class', $trace[1]) && $trace[1]['class'] == get_class($this)) {
                    $return = true;
                } elseif ($rp->isProtected()) {
                    throw new Exception\ExtenderAccessFailed('protected', get_class($this), $property);
                }
            }
            if ($return) {
                return $rp->getValue($this->children[$class]);
            }
        }

        throw new Exception\PropertyUndefined(get_class($this), $property);
    }

    /**
     * @detail      Set a property on the instantiated class object for properties that do not exist.  This will
     *              set the value of a property if it exists in a child class.  This method honors the private,
     *              protected and public variable declarations.  If the child class variable is not meant to be
     *              accessible, either by not existing, or being set public, or being protected and the call
     *              originated outside the object, then the variable is created on the fly in the main object.
     *              This is the same as a normal object.
     *
     * @param string $property The property being set
     * @param mixed  $value    The value to set
     */
    public function __set(string $property, mixed $value): void
    {
        if (!array_key_exists($property, $this->properties)) {
            $this->{$property} = $value;
        }

        list($class, $rp) = $this->properties[$property];
        if (!($set = $rp->isPublic())) {
            $trace = debug_backtrace();
            if ($rp->isPrivate()) {
                throw new Exception\ExtenderAccessFailed('private', get_class($this), $property);
            }
            if (array_key_exists('class', $trace[1]) && $trace[1]['class'] == get_class($this)) {
                $return = true;
            } elseif ($rp->isProtected()) {
                throw new Exception\ExtenderAccessFailed('protected', get_class($this), $property);
            }
        }
        if ($set) {
            $rp->setValue($this->children[$class], $value);
        }
    }

    /**
     * Test if the class extends another class.
     *
     * This implements the "instanceof" functionality of PHP and searches all the child classes to see if any of them extend
     * the specified class.
     *
     * @param string $class The name of the class to test
     *
     * @return bool true if any of the child classes extend the specified class
     */
    public function instanceOf($class): bool
    {
        if ($this instanceof $class) {
            return true;
        }
        foreach ($this->children as $child) {
            if ($child instanceof $class
                || ($child instanceof Extender && $child->instanceof($class))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @detail      Extend a class with a child class.
     *
     *              The arguments are as follows:
     *              # The name of the class to inherit from
     *              # ...  A list of arguments to pass on to the class constructor
     *
     * @return bool returns true if the class was successfully extended
     *
     * @throws \Exception
     */
    protected function extend(): bool
    {
        $args = func_get_args();
        if (!isset($args[0])) {
            return false;
        }
        $class = array_shift($args);
        if ('\\' !== $class[0]) {
            $t = new \ReflectionClass($this);
            $class = $t->getNamespaceName().'\\'.$class;
        }
        $r = new \ReflectionClass($class);
        if ($r->isFinal()) {
            throw new Exception\ExtenderMayNotInherit('final', get_class($this), $class);
        }
        if ($r->isAbstract()) {
            $wrapperClass = 'wrapper_'.str_replace('\\', '_', $class);
            eval("class {$wrapperClass} extends {$class} {}");
            $r = new \ReflectionClass($wrapperClass);
        }
        $obj = $r->newInstanceArgs($args);
        $this->children[$class] = $obj;
        foreach ($r->getMethods() as $method) {
            if (!array_key_exists($method->name, $this->methods)) {
                if (!$method->isPrivate()) {
                    $method->setAccessible(true);
                }
                $this->methods[$method->name] = [
                    $class,
                    $method,
                ];
            }
        }
        foreach ($r->getProperties() as $property) {
            if (!array_key_exists($property->name, $this->properties)) {
                if (!$property->isPrivate()) {
                    $property->setAccessible(true);
                }
                $this->properties[$property->name] = [
                    $class,
                    $property,
                ];
            }
        }

        return true;
    }
}
