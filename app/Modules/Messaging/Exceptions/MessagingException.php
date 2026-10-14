<?php

namespace App\Modules\Messaging\Exceptions;

use RuntimeException;

/** A provider refused or could not deliver a message. The text never contains credentials. */
class MessagingException extends RuntimeException {}
