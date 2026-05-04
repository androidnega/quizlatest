<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when student verification SMS cannot be sent (disabled, incomplete config, etc.).
 */
final class StudentSmsVerificationUnavailable extends RuntimeException
{
    public static function smsDisabled(): self
    {
        return new self(__('SMS verification is currently disabled. Please contact your coordinator.'));
    }

    public static function smsIncompleteConfiguration(): self
    {
        return new self(__('SMS verification is not fully configured. Please contact your coordinator.'));
    }
}
