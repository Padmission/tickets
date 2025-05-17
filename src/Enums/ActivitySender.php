<?php

namespace Padmission\Tickets\Enums;

enum ActivitySender: string
{
    case System = 'system';

    case User = 'user';

    case Supporter = 'supporter';
}
