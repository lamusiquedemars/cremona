<?php

namespace App\Enums;

enum IncomingRequestOutcome: string
{
    case Answered = 'answered';
    case Converted = 'converted';
    case NoFollowUp = 'no_follow_up';
    case Spam = 'spam';
    case Duplicate = 'duplicate';
}
