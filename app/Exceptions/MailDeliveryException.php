<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

/**
 * A time-critical email (an OTP) could not be handed to the mail transport.
 *
 * Raised instead of letting the endpoint answer "we emailed you a code" when
 * nothing was actually sent — the caller gets an honest failure and the admin
 * gets a pointer to Settings → Email delivery.
 */
class MailDeliveryException extends RuntimeException
{
    public function __construct(
        public readonly string $reason = '',
        ?Throwable $previous = null,
    ) {
        parent::__construct(
            'We could not send the email. Please try again shortly, or contact support if it keeps happening.',
            0,
            $previous
        );
    }
}
