<?php

namespace App\Enums;

enum ConsentStatus: string
{
    case Unknown = 'unknown';
    case Granted = 'granted';
    case Withdrawn = 'withdrawn';
}
