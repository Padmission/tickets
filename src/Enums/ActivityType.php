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

    case Reopened = 'reopened';

    case AssigneeChanged = 'assignee-changed';

    case Escalated = 'escalated';

    case AddedToEscalation = 'added-to-escalation';

    case RemovedFromEscalation = 'removed-from-escalation';

    case OriginalAdded = 'original-added';

    case OriginalRemoved = 'original-removed';

    case HandedOver = 'handed-over';

    case FollowsUp = 'follows-up';

    case OpenedFor = 'opened-for';

    case SubjectChanged = 'subject-changed';

    // Keeps an escalation that was never about an original ticket an escalation.
    case AskedDirectly = 'asked-directly';
}
