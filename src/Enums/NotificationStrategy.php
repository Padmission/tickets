<?php

namespace Padmission\Tickets\Enums;

enum NotificationStrategy: string
{
    case Immediate = 'immediate';
    case Debounced = 'debounced';
}
