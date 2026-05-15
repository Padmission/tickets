<?php

declare(strict_types=1);

// Originally by Eslam Reda (eslam-reda-div/filament-copilot, MIT). See LICENSE.md.

namespace Padmission\Tickets\Copilot\Enums;

enum AuditAction: string
{
    case MessageSent = 'message_sent';
    case MessageReceived = 'message_received';
    case ToolCalled = 'tool_called';
    case ToolExecuted = 'tool_executed';
    case ToolRejected = 'tool_rejected';
    case PlanCreated = 'plan_created';
    case PlanApproved = 'plan_approved';
    case PlanRejected = 'plan_rejected';
    case PlanExecuted = 'plan_executed';
    case RecordRead = 'record_read';
    case RecordSearched = 'record_searched';
    case RecordUpdated = 'record_updated';
    case RecordCreated = 'record_created';
    case RecordDeleted = 'record_deleted';
    case RecordRestored = 'record_restored';
    case RecordForceDeleted = 'record_force_deleted';
    case FormFilled = 'form_filled';
    case FormSaved = 'form_saved';
    case ActionExecuted = 'action_executed';
    case FilterApplied = 'filter_applied';
    case NavigatedTo = 'navigated_to';
    case ApprovalRequested = 'approval_requested';
    case ApprovalGranted = 'approval_granted';
    case ApprovalDenied = 'approval_denied';
    case ConversationStarted = 'conversation_started';
    case ConversationExported = 'conversation_exported';
    case RateLimitHit = 'rate_limit_hit';
    case ResponseReceived = 'response_received';
    case RecordSorted = 'record_sorted';
    case RecordFiltered = 'record_filtered';
}
