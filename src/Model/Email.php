<?php

declare(strict_types=1);

namespace Hazaar\Model;

use Hazaar\Model;

/**
 * Email Address Strict Model.
 *
 * This is a simple model that enforces the format of an email address.
 *
 * It currently has a single field called 'address' that is used to validate the email address format.
 *
 * @author Jamie Carl <jamie@hazaar.io>
 */
class Email extends Model
{
    protected string $name;
    protected string $address;

    public function construct(array &$data): void
    {
        $this->defineRule('required', 'address');
        $this->defineRule('filter', 'address', FILTER_VALIDATE_EMAIL);
    }
}
