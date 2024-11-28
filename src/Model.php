<?php

declare(strict_types=1);

namespace Hazaar;

use Hazaar\Model\Exception\DefineEventHookException;
use Hazaar\Model\Exception\PropertyException;
use Hazaar\Model\Exception\PropertyValidationException;
use Hazaar\Model\Exception\UnsetPropertyException;

/**
 * This is an abstract class that implements the \jsonSerializable interface.
 * It serves as a base class for models in the Hazaar MVC framework.
 *
 * @implements \Iterator<string,mixed>
 */
abstract class Model implements \jsonSerializable, \Iterator
{
    /**
     * @var array<callable>
     */
    private array $eventHooks = [];

    /**
     * @var array<mixed>
     */
    private array $propertyRules = [];

    /**
     * @var array<string>
     */
    private static array $objectHooks = [
        'populate',
        'populated',
        'extend',
        'extended',
        'serialize',
        'serialized',
        'json',
    ];

    /**
     * @var array<string>
     */
    private array $propertyNames = [];

    /**
     * @var array<string,mixed>
     */
    private array $userProperties = [];

    /**
     * @var array<string>
     */
    private static array $allowTypes = [
        'bool',
        'int',
        'float',
        'string',
        'array',
        'object',
        'null',
    ];

    /**
     * Model constructor.
     *
     * @param array<mixed> $data the data to initialize the model with
     */
    final public function __construct(array|\stdClass $data = [], mixed ...$args)
    {
        if ($data instanceof \stdClass) {
            $data = get_object_vars($data);
        }
        $this->construct($data, ...$args);
        $protectedProperties = (new \ReflectionClass(static::class))->getProperties(\ReflectionProperty::IS_PROTECTED);
        foreach ($protectedProperties as $reflectionProperty) {
            $propertyName = $reflectionProperty->getName();
            $propertyValue = $data[$propertyName] ?? null;
            if (null !== $propertyValue) {
                try {
                    $this->convertPropertyValueDataType($reflectionProperty, $propertyValue);
                } catch (\Exception $e) {
                    throw new \Exception("Error initialising property '{$propertyName}' in class '".static::class."': ".$e->getMessage());
                }
                $reflectionProperty->setValue($this, $propertyValue);
            }
            if ($reflectionProperty->isInitialized($this) && isset($this->propertyRules[$propertyName])) {
                $this->execPropertyRules($propertyName, $propertyValue, $this->propertyRules[$propertyName]);
            }
            $this->propertyNames[] = $propertyName;
        }
        foreach ($this->userProperties as $propertyName => $propertyData) {
            if (!array_key_exists($propertyName, $data)) {
                continue;
            }
            $this->setUserProperty($propertyName, $data[$propertyName]);
        }
        $this->constructed($data, ...$args);
        sort($this->propertyNames);
    }

    final public function __destruct()
    {
        if (method_exists($this, 'destruct')) {
            $this->destruct();
        }
    }

    public function __get(string $propertyName): mixed
    {
        return $this->get($propertyName);
    }

    public function __set(string $propertyName, mixed $propertyValue): void
    {
        $this->set($propertyName, $propertyValue);
    }

    /**
     * Checks if a property is set.
     *
     * @param string $propertyName the name of the property to check
     *
     * @return bool returns true if the property is set, false otherwise
     */
    public function __isset(string $propertyName)
    {
        return $this->has($propertyName);
    }

    /**
     * Unsets a specific property of the model.
     *
     * This magic method is called when an unset operation is performed on an inaccessible property of the model.
     * It allows you to unset a specific property by its name.
     *
     * @param string $propertyName the name of the property to unset
     */
    public function __unset(string $propertyName): void
    {
        if (property_exists($this, $propertyName)) {
            throw new UnsetPropertyException(static::class, $propertyName);
        }
        if (array_key_exists($propertyName, $this->userProperties)) {
            unset($this->userProperties[$propertyName], $this->propertyNames[array_search($propertyName, $this->propertyNames)]);
        }
    }

    /**
     * Applies the minimum value rule to a property.
     *
     * @param string   $propertyName  the name of the property
     * @param null|int $propertyValue the value of the property
     * @param int      $minValue      the minimum allowed value
     *
     * @return mixed the property value after applying the minimum value rule
     *
     * @phpstan-ignore-next-line
     */
    private function __propertyRule__min(string $propertyName, ?int $propertyValue, int $minValue): mixed
    {
        return max($propertyValue, $minValue);
    }

    /**
     * Applies a maximum value rule to a property.
     *
     * @param string   $propertyName  the name of the property
     * @param null|int $propertyValue the value of the property
     * @param int      $maxValue      the maximum allowed value
     *
     * @return mixed the property value limited to the maximum value
     *
     * @phpstan-ignore-next-line
     */
    private function __propertyRule__max(string $propertyName, ?int $propertyValue, int $maxValue): mixed
    {
        return min($propertyValue, $maxValue);
    }

    /**
     * Applies a range validation rule to a property value.
     *
     * @param string   $propertyName  the name of the property
     * @param null|int $propertyValue the value of the property
     * @param int      $minValue      the minimum allowed value
     * @param int      $maxValue      the maximum allowed value
     *
     * @return mixed the validated property value within the specified range
     *
     * @phpstan-ignore-next-line
     */
    private function __propertyRule__range(string $propertyName, ?int $propertyValue, int $minValue, int $maxValue): mixed
    {
        return min(max($propertyValue, $minValue), $maxValue);
    }

    /**
     * Validates if a property is required and throws an exception if it is empty.
     *
     * @param string $propertyName  the name of the property being validated
     * @param mixed  $propertyValue the value of the property being validated
     *
     * @return mixed the validated property value
     *
     * @throws PropertyValidationException if the property value is empty
     *
     * @phpstan-ignore-next-line
     */
    private function __propertyRule__required(string $propertyName, mixed $propertyValue): mixed
    {
        if (empty($propertyValue)) {
            throw new PropertyValidationException($propertyName, 'required');
        }

        return $propertyValue;
    }

    /**
     * Validates the minimum length of a property value.
     *
     * @param string      $propertyName  the name of the property being validated
     * @param null|string $propertyValue the value of the property being validated
     * @param int         $minLength     the minimum length required for the property value
     *
     * @return null|string the validated property value
     *
     * @throws PropertyValidationException if the property value is shorter than the minimum length
     *
     * @phpstan-ignore-next-line
     */
    private function __propertyRule__minlength(string $propertyName, ?string $propertyValue, int $minLength): ?string
    {
        if (null !== $propertyValue && strlen($propertyValue) < $minLength) {
            throw new PropertyValidationException($propertyName, 'minlength');
        }

        return $propertyValue;
    }

    /**
     * Validates the maximum length of a property value.
     *
     * @param string      $propertyName  the name of the property being validated
     * @param null|string $propertyValue the value of the property being validated
     * @param int         $maxLength     the maximum length allowed for the property value
     *
     * @return null|string the validated property value
     *
     * @throws PropertyValidationException if the property value exceeds the maximum length
     *
     * @phpstan-ignore-next-line
     */
    private function __propertyRule__maxlength(string $propertyName, ?string $propertyValue, int $maxLength): ?string
    {
        if (null !== $propertyValue && strlen($propertyValue) > $maxLength) {
            throw new PropertyValidationException($propertyName, 'maxlength');
        }

        return $propertyValue;
    }

    /**
     * Pads a string property value with spaces to a specified length.
     *
     * @param string      $propertyName  the name of the property
     * @param null|string $propertyValue the value of the property
     * @param int         $padLength     the desired length of the padded string
     *
     * @return null|string the padded string value or null if the property value is null
     *
     * @phpstan-ignore-next-line
     */
    private function __propertyRule__pad(string $propertyName, ?string $propertyValue, int $padLength): ?string
    {
        if (null !== $propertyValue) {
            $propertyValue = str_pad($propertyValue, $padLength);
        }

        return $propertyValue;
    }

    /**
     * Applies a filter to a property value based on the specified filter type.
     *
     * @param string      $propertyName  the name of the property
     * @param null|string $propertyValue the value of the property
     * @param int         $filterType    the filter type to apply
     *
     * @return null|string the filtered property value
     *
     * @throws PropertyValidationException if the property value fails the filter
     *
     * @see https://www.php.net/manual/en/function.filter-var.php The PHP filter_var() function.
     *
     * @phpstan-ignore-next-line
     */
    private function __propertyRule__filter(string $propertyName, ?string $propertyValue, int $filterType): ?string
    {
        if (null !== $propertyValue && false === filter_var($propertyValue, $filterType)) {
            throw new PropertyValidationException($propertyName, 'email');
        }

        return $propertyValue;
    }

    /**
     * Checks if the given property value contains the specified value.
     *
     * @param string     $propertyName  the name of the property being validated
     * @param null|array $propertyValue the value of the property being validated
     * @param mixed      $contains      the value to check if it is contained in the property value
     *
     * @return null|array the validated property value
     *
     * @throws PropertyValidationException if the property value does not contain the specified value
     *
     * @phpstan-ignore-next-line
     */
    private function __propertyRule__contains(string $propertyName, ?array $propertyValue, mixed $contains): ?array
    {
        if (null !== $propertyValue && !in_array($contains, $propertyValue, true)) {
            throw new PropertyValidationException($propertyName, 'contains');
        }

        return $propertyValue;
    }

    /**
     * Formats the given property value according to the specified format.
     *
     * @param string      $propertyName  the name of the property
     * @param null|string $propertyValue the value of the property
     * @param string      $format        the format string to apply to the property value
     *
     * @return null|string the formatted property value, or null if the original value was null
     *
     * @phpstan-ignore-next-line
     */
    private function __propertyRule__format(string $propertyName, ?string $propertyValue, string $format): ?string
    {
        if (null !== $propertyValue) {
            $propertyValue = sprintf($format, $propertyValue);
        }

        return $propertyValue;
    }

    /**
     * Applies a custom property rule to the given property.
     *
     * @param string   $propertyName  the name of the property
     * @param mixed    $propertyValue the value of the property
     * @param callable $callback      the callback function to apply the custom rule
     *
     * @return mixed the result of the callback function
     *
     * @throws PropertyValidationException if the custom rule returns false
     *
     * @phpstan-ignore-next-line
     */
    private function __propertyRule__custom(string $propertyName, mixed $propertyValue, callable $callback): mixed
    {
        $result = $callback($propertyName, $propertyValue);
        if (false === $result) {
            throw new PropertyValidationException($propertyName, 'custom');
        }

        return true;
    }

    /**
     * Trims the specified character from the given property value.
     *
     * @param string $propertyName  the name of the property
     * @param mixed  $propertyValue the value of the property
     * @param string $char          the character to be trimmed (default is ' ')
     *
     * @return string the trimmed property value
     *
     * @phpstan-ignore-next-line
     */
    private function __propertyRule__trim(string $propertyName, mixed $propertyValue, string $char = ' '): string
    {
        return trim($propertyValue, $char);
    }

    public function __serialize(): array
    {
        return $this->toArray();
    }

    /**
     * @param array<mixed> $data
     */
    public function __unserialize(array $data): void
    {
        foreach ($data as $propertyName => $propertyValue) {
            $this->__set($propertyName, $propertyValue);
        }
    }

    /**
     * Magic method called when reading inaccessible properties.
     *
     * @param string $propertyName The name of the property being accessed
     *
     * @return mixed The value of the property
     */
    public function get(string $propertyName)
    {
        // Check if the property exists
        if (!property_exists($this, $propertyName)) {
            if (array_key_exists($propertyName, $this->userProperties)) {
                $propertyValue = $this->userProperties[$propertyName]['value'];
            } else {
                trigger_error('Undefined property: '.static::class.'::$'.$propertyName, E_USER_NOTICE);

                return null; // or throw an exception
            }
        } else {
            // Get the property value using reflection
            $reflectionProperty = new \ReflectionProperty(static::class, $propertyName);
            if ($reflectionProperty->isPrivate()) {
                throw new PropertyException(static::class, $propertyName, 'is private and cannot be accessed');
            }
            if (false === $reflectionProperty->isInitialized($this)) {
                throw new PropertyException(static::class, $propertyName, 'must not be accessed before initialization');
            }
            $propertyValue = $reflectionProperty->getValue($this);
        }

        /**
         * Executes read event hooks and property rules for a given property.
         *
         * If a read event hook is registered for the property, it is executed with the current property value as the argument.
         * If property rules are defined for the property, they are executed using the execPropertyRules() method.
         *
         * @param string $propertyName  the name of the property
         * @param mixed  $propertyValue the current value of the property
         */
        if (isset($this->eventHooks['read'][$propertyName])) {
            $propertyValue = $this->eventHooks['read'][$propertyName]($propertyValue);
            if (isset($this->propertyRules[$propertyName])) {
                $this->execPropertyRules($propertyName, $propertyValue, $this->propertyRules[$propertyName]);
            }
        }
        if (isset($this->eventHooks['read'][true])) {
            $propertyValue = $this->eventHooks['read'][true]($propertyValue);
            if (isset($this->propertyRules[$propertyName])) {
                $this->execPropertyRules($propertyName, $propertyValue, $this->propertyRules[$propertyName]);
            }
        }

        // Return the value of the property
        return $propertyValue;
    }

    /**
     * Sets the value of a property dynamically.
     *
     * @param string $propertyName  the name of the property
     * @param mixed  $propertyValue the value to set for the property
     */
    public function set(string $propertyName, mixed $propertyValue): void
    {
        // Check if the property exists
        if (!property_exists($this, $propertyName)) {
            if (array_key_exists($propertyName, $this->userProperties)) {
                settype($propertyValue, $this->userProperties[$propertyName]['type']);
                $this->userProperties[$propertyName]['value'] = $propertyValue;
            } else {
                trigger_error('Undefined property: '.static::class.'::$'.$propertyName, E_USER_NOTICE);

                return;
            }
        }
        if (isset($this->propertyRules[$propertyName])) {
            $this->execPropertyRules($propertyName, $propertyValue, $this->propertyRules[$propertyName]);
        }
        if (isset($this->eventHooks['write'][$propertyName])) {
            $propertyValue = $this->eventHooks['write'][$propertyName]($propertyValue);
        }
        if (isset($this->eventHooks['write'][true])) {
            $propertyValue = $this->eventHooks['write'][true]($propertyValue);
        }
        if (property_exists($this, $propertyName)) {
            $reflectionProperty = new \ReflectionProperty(static::class, $propertyName);
            if ($reflectionProperty->isPrivate()) {
                trigger_error('Cannot write to private property: '.static::class.'::$'.$propertyName, E_USER_NOTICE);

                return;
            }

            try {
                $this->convertPropertyValueDataType($reflectionProperty, $propertyValue);
            } catch (\Exception $e) {
                throw new \Exception("Error setting property '{$propertyName}' in class '".static::class."': ".$e->getMessage());
            }
            if (true === $reflectionProperty->isInitialized($this)) {
                $currentValue = $reflectionProperty->getValue($this);
                if ($currentValue === $propertyValue) {
                    return;
                }
            }
            $reflectionProperty->setValue($this, $propertyValue);
        }
        if (isset($this->eventHooks['written'][$propertyName])) {
            $propertyValue = $this->eventHooks['written'][$propertyName]($propertyValue);
        }
        if (isset($this->eventHooks['written'][true])) {
            $propertyValue = $this->eventHooks['write'][true]($propertyValue);
        }
    }

    /**
     * (re)Populates the model with data from an array or object.
     *
     * This method is used to populate the model with data from an array or object. If the
     * object already has data, it will be overwritten by the new data.  This includes any
     * setting properties to null if they are not present in the new data.
     *
     * @param array<mixed>|object $data the data to populate the model with
     */
    public function populate(array|object $data): void
    {
        if (isset($this->eventHooks['populate'])) {
            $this->eventHooks['populate']($data);
        }
        foreach ($this->propertyNames as $propertyName) {
            $newValue = null;
            if (is_object($data) && property_exists($data, $propertyName)) {
                $newValue = $data->{$propertyName};
            } elseif (is_array($data) && array_key_exists($propertyName, $data)) {
                $newValue = $data[$propertyName];
            }
            $this->__set($propertyName, $newValue);
        }
        if (isset($this->eventHooks['populated'])) {
            $this->eventHooks['populated']($data);
        }
    }

    /**
     * Extends the model with additional data.
     *
     * @param array<mixed>|object $data the data to extend the model with
     */
    public function extend(array|object $data): void
    {
        if (isset($this->eventHooks['extend'])) {
            $this->eventHooks['extend']($data);
        }
        if (is_object($data)) {
            $data = get_object_vars($data);
        }
        foreach ($data as $propertyName => $propertyValue) {
            if (false === property_exists($this, $propertyName)) {
                continue;
            }
            $this->__set($propertyName, $propertyValue);
        }
        if (isset($this->eventHooks['extended'])) {
            $this->eventHooks['extended']($data);
        }
    }

    public function count(): int
    {
        return count($this->propertyNames);
    }

    /**
     * Converts the object to an array representation.
     *
     * @return array<string,mixed> the array representation of the object
     */
    public function toArray(bool $ignoreNullPropertyValues = false): array
    {
        $array = [];
        if (isset($this->eventHooks['serialize'])) {
            $this->eventHooks['serialize']($array);
        }
        foreach ($this->propertyNames as $propertyName) {
            if (array_key_exists($propertyName, $this->userProperties)) {
                $propertyValue = $this->userProperties[$propertyName]['value'];
            } else {
                $reflectionProperty = new \ReflectionProperty(static::class, $propertyName);
                if ($reflectionProperty->isPrivate() || false === $reflectionProperty->isInitialized($this)) {
                    continue;
                }
                $propertyValue = $reflectionProperty->getValue($this);
            }
            if (true === $ignoreNullPropertyValues && null === $propertyValue) {
                continue;
            }
            if (isset($this->eventHooks['read'][$propertyName])) {
                $propertyValue = $this->eventHooks['read'][$propertyName]($propertyValue);
            }
            $array[$propertyName] = $propertyValue;
        }
        if (isset($this->eventHooks['serialized'])) {
            $this->eventHooks['serialized']($array);
        }

        return $array;
    }

    /**
     * Returns the object data as a JSON serializable array.
     *
     * @return array<string,mixed> the JSON serializable array representation of the object data
     */
    public function jsonSerialize(): array
    {
        return self::toArray();
    }

    public function current(): mixed
    {
        $propertyName = current($this->propertyNames);

        return $this->__get($propertyName);
    }

    public function next(): void
    {
        next($this->propertyNames);
    }

    public function key(): mixed
    {
        return current($this->propertyNames);
    }

    public function valid(): bool
    {
        return null !== key($this->propertyNames);
    }

    public function rewind(): void
    {
        reset($this->propertyNames);
    }

    /**
     * Converts the model object to a JSON string.
     *
     * @return string the JSON representation of the model object
     */
    public function toJSON(): string
    {
        if (isset($this->eventHooks['json'])) {
            $this->eventHooks['json']();
        }

        return json_encode($this);
    }

    /**
     * Creates a new instance of the model class from a JSON string.
     *
     * @param string $json the JSON string to parse
     *
     * @return static the newly created instance of the model class
     */
    public static function fromJSONString(string $json): static
    {
        return new static(json_decode($json, true));
    }

    public function has(string $propertyName): bool
    {
        $propertyExists = in_array($propertyName, $this->propertyNames);
        if (false === $propertyExists) {
            $propertyExists = array_key_exists($propertyName, $this->userProperties);
        }

        return $propertyExists;
    }

    public function defineProperty(string $propertyType, string $propertyName, mixed $propertyValue = null): bool
    {
        if (property_exists($this, $propertyName) || array_key_exists($propertyName, $this->userProperties)) {
            return false;
        }
        $this->userProperties[$propertyName] = ['type' => $propertyType, 'value' => $propertyValue];
        $this->propertyNames[] = $propertyName;

        return true;
    }

    /**
     * Defines an event hook for the model.
     *
     * @param string $hookName the name of the hook
     * @param mixed  ...$args  The arguments for the hook.
     */
    public function defineEventHook(string $hookName, mixed ...$args): void
    {
        if (in_array($hookName, self::$objectHooks, true)) {
            $this->eventHooks[$hookName] = $args[0];
        } else {
            if (isset($args[0]) && is_string($args[0])) {
                $propertyName = $args[0];
                $callback = $args[1];
                if (!property_exists($this, $propertyName)) {
                    $this->defineProperty('mixed', $propertyName);
                }
            } else {
                $propertyName = true;
                $callback = $args[0];
            }
            if (!is_callable($callback)) {
                throw new DefineEventHookException(static::class, $hookName, 'Invalid callback');
            }
            $this->eventHooks[$hookName][$propertyName] = $callback;
        }
    }

    /**
     * Defines a rule for a property in the model.
     *
     * @param string        $rule          the name of the rule
     * @param array<string> $propertyNames the name of one or more properties
     * @param mixed         ...$args       Additional arguments for the rule.
     *
     * @throws \Exception if the specified rule does not exist
     */
    public function defineRule(string $rule, array|string $propertyNames, mixed ...$args): void
    {
        $ruleName = '__propertyRule__'.$rule;
        if (!method_exists($this, $ruleName)) {
            throw new \Exception("Rule '{$rule}' does not exist");
        }
        if (!is_array($propertyNames)) {
            $propertyNames = [$propertyNames];
        }
        foreach ($propertyNames as $propertyName) {
            if (!array_key_exists($propertyName, $this->propertyRules)) {
                $this->propertyRules[$propertyName] = [];
            }
            $this->propertyRules[$propertyName][$rule] = [
                $ruleName,
                $args,
            ];
        }
    }

    /**
     * @param array<string,mixed> $data
     */
    protected function construct(array &$data): void {}

    /**
     * @param array<string,mixed> $data
     */
    protected function constructed(array &$data): void {}

    private function setUserProperty(string $propertyName, mixed $propertyValue): void
    {
        if (!array_key_exists($propertyName, $this->userProperties)) {
            throw new PropertyException(static::class, $propertyName, 'is not a user defined property');
        }
        $this->convertValueDataType($this->userProperties[$propertyName]['type'], $propertyValue);
        $this->userProperties[$propertyName]['value'] = $propertyValue;
    }

    /**
     * Converts the data type of a property value based on its reflection.
     *
     * @param \ReflectionProperty $reflectionProperty the reflection of the property
     * @param mixed               &$propertyValue     The value of the property to be converted
     *
     * @throws \Exception if the property type is unsupported or not a subclass of 'Hazaar\Model'
     */
    private function convertPropertyValueDataType(\ReflectionProperty $reflectionProperty, mixed &$propertyValue): void
    {
        /** 
         * @var ?\ReflectionNamedType $propertyType 
         */
        $propertyType = $reflectionProperty->getType();
        if (null === $propertyType) {
            return;
        }
        $propertyTypeName = $propertyType->getName();
        if (false === $propertyType->isBuiltin()) {
            if (is_subclass_of($propertyTypeName, 'Hazaar\Model')) {
                if (null !== $propertyValue && !$propertyValue instanceof $propertyTypeName) {
                    $propertyValue = new $propertyTypeName($propertyValue);
                }
            } elseif ('Hazaar\Date' === $propertyTypeName) {
                if (null !== $propertyValue && !$propertyValue instanceof Date) {
                    $propertyValue = new Date($propertyValue);
                }
            } elseif (!(is_object($propertyValue)
                && ($propertyTypeName === get_class($propertyValue) || is_subclass_of($propertyTypeName, get_class($propertyValue))))) {
                throw new \Exception("Implicit conversion of unsupported type '{$propertyTypeName}'.  Type must be a subclass of 'Hazaar\\Model'");
            }
        } elseif (null !== $propertyValue && false === $propertyType->allowsNull()) {
            if ('bool' === $propertyTypeName) {
                $propertyValue = boolify($propertyValue);
            }
        }
        if (null === $propertyValue && $reflectionProperty->hasDefaultValue()) {
            $propertyValue = $reflectionProperty->getDefaultValue();
        }
    }

    private function convertValueDataType(string $propertyType, mixed &$propertyValue): void
    {
        if (in_array($propertyType, self::$allowTypes, true)) {
            if ('bool' === $propertyType) {
                $propertyValue = boolify($propertyValue);
            } else {
                settype($propertyValue, $propertyType);
            }
        } elseif (is_subclass_of($propertyType, 'Hazaar\Model')) {
            if (null !== $propertyValue && !$propertyValue instanceof $propertyType) {
                $propertyValue = new $propertyType($propertyValue);
            }
        } elseif ('Hazaar\Date' === $propertyType) {
            if (null !== $propertyValue && !$propertyValue instanceof Date) {
                $propertyValue = new Date($propertyValue);
            }
        } elseif (!(is_object($propertyValue)
            && ($propertyType === get_class($propertyValue) || is_subclass_of($propertyType, get_class($propertyValue))))) {
            throw new \Exception("Implicit conversion of unsupported type '{$propertyType}'.  Type must be a subclass of 'Hazaar\\Model'");
        }
    }

    /**
     * Executes the property rules for a given property.
     *
     * @param string                     $propertyName   the name of the property
     * @param mixed                      &$propertyValue The value of the property
     * @param array<int,callable|string> $rules          the array of rules to be applied
     */
    private function execPropertyRules(string $propertyName, mixed &$propertyValue, array $rules): void
    {
        foreach ($rules as $rule => $ruleData) {
            $propertyValue = call_user_func_array([$this, $ruleData[0]], array_merge([$propertyName, $propertyValue], $ruleData[1]));
        }
    }
}
