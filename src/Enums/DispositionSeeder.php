<?php

namespace Padmission\Tickets\Enums;

enum DispositionSeeder: string
{
    case Issue_Resolved = 'issue_resolved';
    case Follow_Up_Required = 'follow_up_required';
    case Escalated = 'escalated';
    case Customer_Unreachable = 'customer_unreachable';
    case Satisfied = 'satisfied';
    case Unsatisfied = 'unsatisfied';
}
