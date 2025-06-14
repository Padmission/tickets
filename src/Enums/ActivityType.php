<?php

namespace Padmission\Tickets\Enums;

enum ActivityType: string
{
    case Message = 'message';

    case Opened = 'opened';

    case InternalMessage = 'internal-message';

    case PriorityChanged = 'priority-changed';

    case StatusChanged = 'status-changed';

    case TurnChanged = 'turn-changed';

    case Closed = 'closed';

    case AssigneeChanged = 'assignee-changed';

    case Escalated = 'escalated';

    case Note = 'note';
}
