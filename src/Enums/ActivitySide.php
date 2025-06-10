<?php

namespace Padmission\Tickets\Enums;

enum ActivitySide: string
{
    case System = 'system';

    case Me = 'me';

    case Other = 'other';
}
