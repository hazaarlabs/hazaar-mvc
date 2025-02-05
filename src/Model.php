<?php

declare(strict_types=1);

namespace Hazaar;

use Hazaar\Model\Exception\DefineEventHookException;
use Hazaar\Model\Exception\PropertyAttributeException;
use Hazaar\Model\Exception\PropertyException;
use Hazaar\Model\Interface\AttributeRule;

/**
 * This is an abstract class that implements the \jsonSerializable interface.
 * It serves as a base class for models in the Hazaar framework.
 *
 * @implements \Iterator<string,mixed>
 */
abstract class Model implements \jsonSerializable, \Iterator
{
    /**
     * Legacy property rules that can be replaced with PHP 8.4+ style property hooks.
     *
     * This array will still be used to store property rules for PHP 8.3 and below as well
     * as user defined property hooks that can be triggered with the trigger() method.
     *
     * @var array<array<callable|string>|callable>
     */
    private array $eventHooks = [];

    /**
     * @var array<string,array<AttributeRule>>
     */
    private array $propertyAttributes = [];

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
        'boolean',
        'int',
        'integer',
        'float',
        'double',
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
        $this->initialize($data, ...$args);
    }

    final public function __destruct()
    {
        $this->destruct();
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
    public function __isset(string $propertyName): bool
    {
        if (property_exists($this, $propertyName)) {
            return isset($this->{$propertyName});
        }
        if (array_key_exists($propertyName, $this->userProperties)) {
            return isset($this->userProperties[$propertyName], $this->propertyNames[array_search($propertyName, $this->propertyNames)]);
        }

        return false;
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
            unset($this->{$propertyName});
        } elseif (array_key_exists($propertyName, $this->userProperties)) {
            unset($this->userProperties[$propertyName], $this->propertyNames[array_search($propertyName, $this->propertyNames)]);
        }
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
        $this->initialize($data);
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
         * If property rules are defined for the property, they are executed using the execPropertyAttributes() method.
         *
         * @param string $propertyName  the name of the property
         * @param mixed  $propertyValue the current value of the property
         */
        if (isset($this->eventHooks['get'][$propertyName])) {
            $propertyValue = $this->eventHooks['get'][$propertyName]($propertyValue);
            if (isset($this->propertyAttributes[$propertyName])) {
                $this->execPropertyAttributes($propertyValue, new \ReflectionProperty($this, $propertyName), $this->propertyAttributes[$propertyName]);
            }
        }
        if (isset($this->eventHooks['get'][true])) {
            $propertyValue = $this->eventHooks['get'][true]($propertyValue);
            if (isset($this->propertyAttributes[$propertyName])) {
                $this->execPropertyAttributes($propertyName, $propertyValue, $this->propertyAttributes[$propertyName]);
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
        if (isset($this->propertyAttributes[$propertyName]) && count($this->propertyAttributes[$propertyName]) > 0) {
            $result = $this->execPropertyAttributes($propertyValue, new \ReflectionProperty($this, $propertyName), $this->propertyAttributes[$propertyName]);
            if (false === $result) {
                return;
            }
        }
        if (isset($this->eventHooks['set'][$propertyName])) {
            $propertyValue = $this->eventHooks['set'][$propertyName]($propertyValue);
        }
        if (isset($this->eventHooks['set'][true])) {
            $propertyValue = $this->eventHooks['set'][true]($propertyValue);
        }
        if (property_exists($this, $propertyName)) {
            $reflectionProperty = new \ReflectionProperty(static::class, $propertyName);
            if ($reflectionProperty->isPrivate()) {
                trigger_error('Cannot set private property: '.static::class.'::$'.$propertyName, E_USER_NOTICE);

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
        if (isset($this->eventHooks['update'][$propertyName])) {
            $propertyValue = $this->eventHooks['update'][$propertyName]($propertyValue);
        }
        if (isset($this->eventHooks['update'][true])) {
            $propertyValue = $this->eventHooks['update'][true]($propertyValue);
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
     * Returns the keys of the model object.
     *
     * @return array<string> the keys of the model object
     */
    public function keys(): array
    {
        return $this->propertyNames;
    }

    /**
     * Converts the object to an array representation.
     *
     * @return array<string,mixed> the array representation of the object
     */
    public function toArray(?string $context = null, int $depth = 256): array
    {
        $array = [];
        if (isset($this->eventHooks['serialize'])) {
            $this->eventHooks['serialize']($array);
        }
        foreach ($this->propertyNames as $propertyName) {
            // Get the value of the property
            if (array_key_exists($propertyName, $this->userProperties)) {
                $propertyValue = $this->userProperties[$propertyName]['value'];
            } else {
                $reflectionProperty = new \ReflectionProperty(static::class, $propertyName);
                if ($reflectionProperty->isPrivate() || false === $reflectionProperty->isInitialized($this)) {
                    continue;
                }
                $propertyValue = $reflectionProperty->getValue($this);
                if (isset($this->propertyAttributes[$propertyName])) {
                    foreach ($this->propertyAttributes[$propertyName] as $rule) {
                        if (!$rule->serialize($propertyValue, $reflectionProperty, $context)) {
                            continue 2;
                        }
                    }
                }
                if (is_array($propertyValue)) {
                    $this->modelArrayToArray($propertyValue, $context);
                }
            }
            if (isset($this->eventHooks['get'][$propertyName])) {
                // Execute the get event hook for the property
                $propertyValue = $this->eventHooks['get'][$propertyName]($propertyValue);
            }
            if ($depth > 0 && $propertyValue instanceof Model) {
                // Convert model object to array
                $propertyValue = $propertyValue->toArray($context, $depth - 1);
            } elseif ($depth > 0 && is_array($propertyValue)) {
                // Convert array of models to array of arrays
                $propertyValue = array_map(function ($value) use ($context, $depth) {
                    if ($depth > 1 && $value instanceof Model) {
                        return $value->toArray($context, $depth - 2);
                    }

                    return $value;
                }, $propertyValue);
            } elseif (is_object($propertyValue) && enum_exists($propertyValue::class)) {
                // Convert enum object to its value or name if it is untyped
                $propertyValue = property_exists($propertyValue, 'value') ? $propertyValue->value : $propertyValue->name;
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
    public function toJSON(int $flags = 0, int $depth = 512): string
    {
        if (isset($this->eventHooks['json'])) {
            $this->eventHooks['json']();
        }

        return json_encode($this, $flags, $depth);
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

    /**
     * Defines a new user property for the model.
     *
     * @param string $propertyType  the type of the property
     * @param string $propertyName  the name of the property
     * @param mixed  $propertyValue The value of the property. Default is null.
     *
     * @return bool returns true if the property was successfully defined, false if the property already exists
     */
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
            if (!array_key_exists($propertyName, $this->propertyAttributes)) {
                $this->propertyAttributes[$propertyName] = [];
            }
            $attributeRuleClass = 'Hazaar\Model\Rules\\'.ucfirst($rule);
            $this->propertyAttributes[$propertyName][] = new $attributeRuleClass(...$args);
        }
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
     * Triggers an event hook if it exists.
     *
     * This method checks if an event hook with the given name exists in the
     * $eventHooks array. If it does, it calls the hook with the provided arguments.
     *
     * @param string $hookName the name of the event hook to trigger
     * @param mixed  ...$args  The arguments to pass to the event hook.
     */
    public function trigger(string $hookName, mixed ...$args): void
    {
        if (isset($this->eventHooks[$hookName]) && is_callable($this->eventHooks[$hookName])) {
            call_user_func_array($this->eventHooks[$hookName], $args);
        }
    }

    /**
     * Constroctor placeholder method.
     *
     * This method is called before the model has been constructed and the data has been populated.
     *
     * @param array<string,mixed> $data
     */
    protected function construct(array &$data): void {}

    /**
     * Destructor placeholder method.
     *
     * This method is called when the model is being destructed.
     */
    protected function destruct(): void {}

    /**
     * Constructed placeholder method.
     *
     * This method is called after the model has been constructed and the data has been populated.
     */
    protected function constructed(): void {}

    /**
     * Convert an array of model objects to an array of arrays.
     *
     * This method is used to convert an array of model objects to an array of arrays.
     *
     * @param array<mixed> $array the array of model objects to convert
     */
    private function modelArrayToArray(array &$array, ?string $context = null): void
    {
        foreach ($array as &$value) {
            if ($value instanceof Model) {
                $value = $value->toArray($context);
            } elseif (is_array($value)) {
                $this->modelArrayToArray($value, $context);
            }
        }
    }

    /**
     * Sets the value of a user-defined property.
     *
     * @param string $propertyName  the name of the property to set
     * @param mixed  $propertyValue the value to set for the property
     *
     * @throws PropertyException if the property is not a user-defined property
     */
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
                if (!is_subclass_of($propertyValue, $propertyTypeName)
                    && null !== $propertyValue
                    && !$propertyValue instanceof $propertyTypeName) {
                    $propertyValue = new $propertyTypeName($propertyValue);
                }
            } elseif ('Hazaar\DateTime' === $propertyTypeName) {
                if (null !== $propertyValue && !$propertyValue instanceof DateTime) {
                    $propertyValue = new DateTime($propertyValue);
                }
            } elseif (enum_exists($propertyTypeName) && !is_object($propertyValue)) {
                $enumReflection = new \ReflectionEnum($propertyTypeName);
                if ($enumReflection->isBacked()) {
                    $enumTypeName = $enumReflection->getBackingType()->getName();
                    if (get_debug_type($propertyValue) !== $enumTypeName) {
                        settype($propertyValue, $enumTypeName);
                    }
                    // @phpstan-ignore staticMethod.notFound
                    $propertyValue = $propertyTypeName::tryFrom($propertyValue);
                } else {
                    if (false === $enumReflection->hasCase($propertyValue)) {
                        throw new \Exception("Invalid enum value '{$propertyValue}' for enum '{$propertyTypeName}'");
                    }
                    $propertyValue = $enumReflection->getCase($propertyValue)->getValue();
                }
            } elseif (!(is_object($propertyValue)
                && ($propertyTypeName === get_class($propertyValue) || is_subclass_of($propertyTypeName, get_class($propertyValue))))) {
                throw new \Exception("Implicit conversion of unsupported type '{$propertyTypeName}'.  Type must be a subclass of 'Hazaar\\Model'");
            }
        } elseif (null !== $propertyValue && false === $propertyType->allowsNull()) {
            if ('bool' === $propertyTypeName) {
                $propertyValue = boolify($propertyValue);
            } elseif ('array' === $propertyTypeName) {
                $docComment = $reflectionProperty->getDocComment();
                if (false !== $docComment && preg_match('/^\s*\*\s*@var\s+array<(.+)>\s*$/m', $docComment, $matches)) {
                    if (false === strpos($matches[1], ',')) {
                        $matches[1] = 'int,'.$matches[1];
                    }
                    list($propertyKeyType, $propertyArrayType) = explode(',', $matches[1], 2);
                    if (!in_array($propertyArrayType, self::$allowTypes, true)) {
                        if (false !== strpos($propertyArrayType, '|')) {
                            throw new \Exception("Implicit conversion of unsupported type '{$propertyArrayType}'.  Type must be a single subclass of 'Hazaar\\Model'");
                        }
                        if ('\\' !== substr($propertyArrayType, 0, 1)) {
                            $propertyFullClassName = $this->getFullClassName($propertyArrayType, $reflectionProperty);
                            if (null === $propertyFullClassName) {
                                $propertyFullClassName = $reflectionProperty->getDeclaringClass()->getNamespaceName().'\\'.$propertyArrayType;
                            }
                            $propertyArrayType = $propertyFullClassName;
                        }
                    }
                    foreach ($propertyValue as $key => $value) {
                        $this->convertValueDataType($propertyArrayType, $propertyValue[$key]);
                    }
                }
            }
        }
        if (null === $propertyValue && $reflectionProperty->hasDefaultValue()) {
            $propertyValue = $reflectionProperty->getDefaultValue();
        }
    }

    /**
     * Converts the value of a property to the specified data type.
     *
     * @param string $propertyType  the type to which the property value should be converted
     * @param mixed  $propertyValue The value of the property to be converted. This value is passed by reference.
     *
     * @throws \Exception if the conversion type is unsupported or if the type is not a subclass of 'Hazaar\Model'
     */
    private function convertValueDataType(string $propertyType, mixed &$propertyValue): void
    {
        if (in_array($propertyType, self::$allowTypes, true)) {
            if ('bool' === $propertyType || 'boolean' === $propertyType) {
                $propertyValue = boolify($propertyValue);
            } else {
                settype($propertyValue, $propertyType);
            }
        } elseif (is_subclass_of($propertyType, 'Hazaar\Model')) {
            if (null !== $propertyValue && !$propertyValue instanceof $propertyType) {
                $propertyValue = new $propertyType($propertyValue);
            }
        } elseif ('Hazaar\DateTime' === $propertyType) {
            if (null !== $propertyValue && !$propertyValue instanceof DateTime) {
                $propertyValue = new DateTime($propertyValue);
            }
        } elseif (!is_object($propertyValue)) {
            if (!class_exists($propertyType)) {
                throw new \Exception("Implicit conversion to unknown class '{$propertyType}'.");
            }

            throw new \Exception("Implicit conversion of unsupported type '{$propertyType}'.  Type must be a subclass of 'Hazaar\\Model'.");
        }
    }

    private function getFullClassName(string $className, \ReflectionProperty $reflectionProperty): ?string
    {
        $sourceFile = $reflectionProperty->getDeclaringClass()->getFileName();
        $code = file_get_contents($sourceFile);
        $tokens = token_get_all($code, TOKEN_PARSE);
        while ($token = next($tokens)) {
            // Quit early if the class name is found in the use statement.
            if (match ($token[0]) {
                T_INTERFACE, T_TRAIT, T_CLASS, T_FUNCTION => true,
                default => false
            }) {
                break;
            }
            if (!(is_array($token) && T_USE === $token[0])) {
                continue;
            }
            $useStatememnt = '';
            while ($token = next($tokens)) {
                if (';' === $token) {
                    break;
                }
                $useStatememnt .= trim(is_array($token) ? $token[1] : $token);
            }
            if ($className === substr($useStatememnt, strrpos($useStatememnt, '\\') + 1)) {
                return $useStatememnt;
            }
        }

        return null;
    }

    /**
     * Executes the property rules for a given property.
     *
     * @param array<AttributeRule> $rules the array of rules to be applied
     */
    private function execPropertyAttributes(mixed &$value, \ReflectionProperty $property, array $rules): bool
    {
        foreach ($rules as $rule) {
            if (!$rule->evaluate($value, $property)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Model initializer.
     *
     * @param array<mixed> $data the data to initialize the model with
     */
    private function initialize(array|\stdClass $data, mixed ...$args): void
    {
        if ($data instanceof \stdClass) {
            $data = get_object_vars($data);
        }
        $this->construct($data, ...$args);
        $publicProperties = (new \ReflectionClass(static::class))->getProperties(\ReflectionProperty::IS_PROTECTED | \ReflectionProperty::IS_PUBLIC);
        foreach ($publicProperties as $reflectionProperty) {
            $propertyName = $reflectionProperty->getName();
            $propertyValue = $data[$propertyName] ?? null;
            $this->propertyNames[] = $propertyName;
            if (null !== $propertyValue) {
                try {
                    $this->convertPropertyValueDataType($reflectionProperty, $propertyValue);
                } catch (\Exception $e) {
                    throw new \Exception("Error initialising property '{$propertyName}' in class '".static::class."': ".$e->getMessage());
                }
            }
            if (count($reflectionAttributes = $reflectionProperty->getAttributes()) > 0) {
                if ($reflectionProperty->isPublic()) {
                    throw new PropertyAttributeException(static::class, $propertyName, 'is public.  Only protected properties can have attributes');
                }
                if (!isset($this->propertyAttributes[$propertyName])) {
                    $this->propertyAttributes[$propertyName] = [];
                }
                foreach ($reflectionAttributes as $reflectionAttribute) {
                    $reflectionAttributeClass = new \ReflectionClass($reflectionAttribute->getName());
                    if (!$reflectionAttributeClass->isSubclassOf('Hazaar\Model\Interface\AttributeRule')) {
                        continue;
                    }
                    $modelRule = $reflectionAttribute->newInstance();
                    $this->propertyAttributes[$propertyName][] = $modelRule;
                    // We don't use the execPropertyAttributes() method here because we only need to check the rule once.
                    if (!$modelRule->evaluate($propertyValue, $reflectionProperty)) {
                        continue 2;
                    }
                }
            }
            if (null !== $propertyValue) {
                $reflectionProperty->setValue($this, $propertyValue);
            }
        }
        foreach ($this->userProperties as $propertyName => $propertyData) {
            if (!array_key_exists($propertyName, $data)) {
                continue;
            }
            $this->setUserProperty($propertyName, $data[$propertyName]);
        }
        $this->constructed(...$args);
    }
}
