<?php

namespace Padmission\Tickets\Enums;

enum NotificationTrigger: string
{
    case User = 'user';

    case Supporter = 'supporter';
}
