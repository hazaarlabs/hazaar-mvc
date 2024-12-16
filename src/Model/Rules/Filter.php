<?php

namespace Hazaar\Model\Rules;

use Hazaar\Model\Exception\PropertyValidationException;
use Hazaar\Model\Interfaces\AttributeRule;

/**
 * The Filter rule is used to apply a filter to a value.
 *
 * @param int              $type    The filter type to apply.  See FILTER_VALIDATE_* constants at https://www.php.net/manual/en/filter.filters.validate.php
 * @param array<mixed>|int $options Additional options for the filter.  See https://www.php.net/manual/en/filter.filters.validate.php
 *
 * @throws PropertyValidationException
 *
 * @example
 *
 * ```php
 * #[Filter(FILTER_VALIDATE_EMAIL)]
 * public $my_property;
 * ```
 */
#[\Attribute]
class Filter implements AttributeRule
{
    private ?int $type = null;

    /**
     * Additional options for the filter.
     *
     * @var array<mixed>|int See https://www.php.net/manual/en/filter.filters.validate.php
     */
    private array|int $options = 0;

    /**
     * Create a new Filter rule.
     *
     * @param int              $type    The filter type to apply.  See FILTER_VALIDATE_* constants at https://www.php.net/manual/en/filter.filters.validate.php
     * @param array<mixed>|int $options Additional options for the filter.  See https://www.php.net/manual/en/filter.filters.validate.php
     */
    public function __construct(int $type, array|int $options = [])
    {
        $this->type = $type;
        $this->options = $options;
    }

    public function evaluate(mixed &$value, \ReflectionProperty &$property): bool
    {
        if (!(null === $this->type || empty($value))) {
            $value = filter_var($value, $this->type, $this->options);
            if (false === $value) {
                return false;
            }
        }

        return true;
    }
}
