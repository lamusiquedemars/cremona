<?php

namespace App\Enums;

enum MessageThreadingStatus: string
{
    case Pending = 'pending';
    case Matched = 'matched';
    case Ambiguous = 'ambiguous';
    case Unmatched = 'unmatched';
    case Ignored = 'ignored';
}
