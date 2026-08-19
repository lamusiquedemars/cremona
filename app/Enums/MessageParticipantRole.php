<?php

namespace App\Enums;

enum MessageParticipantRole: string
{
    case From = 'from';
    case To = 'to';
    case Cc = 'cc';
    case Bcc = 'bcc';
    case ReplyTo = 'reply_to';
}
