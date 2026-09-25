<?php

namespace App\Domains\Notification\Exceptions;

use RuntimeException;

/**
 * A provider rejected or could not accept a message. Thrown inside the
 * queued job so the queue retries with backoff.
 */
class ChannelDeliveryException extends RuntimeException {}
