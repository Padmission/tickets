<?php

namespace Padmission\Tickets\Enums;

enum NotificationRecipient: int
{
    case None = 0;

    case User = 1;

    case Supporter = 2;

    case Both = 3;
}
