<?php

namespace App\Modules\Billing\Exceptions;

use RuntimeException;

/** A gateway call failed or a callback was not authentic. Messages are safe to show and log. */
class GatewayException extends RuntimeException {}
