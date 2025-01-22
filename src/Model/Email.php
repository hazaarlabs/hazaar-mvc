<?php

declare(strict_types=1);

namespace Hazaar\Model;

use Hazaar\Model;
use Hazaar\Model\Attribute\Required;

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
    #[Required]
    protected string $name;

    #[Required]
    protected string $address;
}
