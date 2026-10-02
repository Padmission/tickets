<?php

namespace Padmission\Tickets\Exceptions;

use Exception;

// The host's replyDisabledUsing() kept someone from writing in the chat; the message is its reason.
class ReplyDisabledException extends Exception {}
