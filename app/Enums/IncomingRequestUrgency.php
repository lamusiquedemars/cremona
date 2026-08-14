<?php

namespace App\Enums;

enum IncomingRequestUrgency: string
{
    case Unknown = 'unknown';
    case Normal = 'normal';
    case High = 'high';
    case Urgent = 'urgent';
}
