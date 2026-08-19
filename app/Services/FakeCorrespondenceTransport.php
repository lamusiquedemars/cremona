<?php

namespace App\Services;

use App\Contracts\CorrespondenceTransport;
use App\Models\ConversationMessage;

class FakeCorrespondenceTransport implements CorrespondenceTransport
{
    public function send(ConversationMessage $message): string
    {
        return "<{$message->public_id}@cremona.test>";
    }
}
