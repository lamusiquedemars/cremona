<?php

namespace App\Contracts;

use App\Models\ConversationMessage;

interface CorrespondenceTransport
{
    /**
     * Returns the RFC Message-ID assigned by the transport.
     */
    public function send(ConversationMessage $message): string;
}
