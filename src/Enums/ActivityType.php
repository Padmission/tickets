<?php

namespace Padmission\Tickets\Enums;

enum ActivityType: string
{
    case Opened = 'opened';

    case Message = 'message';

    case InternalMessage = 'internal-message';

    case StatusChanged = 'status-changed';

    case TurnChanged = 'turn-changed';

    case Closed = 'closed';
}
