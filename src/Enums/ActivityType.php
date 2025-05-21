<?php

namespace Padmission\Tickets\Enums;

enum ActivityType: string
{
    case Message = 'message';

    case InternalMessage = 'internal-message';
}
