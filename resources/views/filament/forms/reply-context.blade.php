@php
    use App\Enums\MessageParticipantRole;

    $from = $message?->participants->first(fn ($participant) => $participant->role === MessageParticipantRole::From);
    $author = $from?->name ?: $from?->address ?: $message?->author?->name ?: $message?->mailbox?->display_name ?: 'votre correspondant';
@endphp

@if ($message)
    <div style="margin-top: -.25rem; border-left: 2px solid rgb(225 219 208); padding: .75rem 1rem; color: rgb(100 95 88);">
        <div style="margin-bottom: .5rem; font-size: .875rem;">Le {{ $message->authored_at?->format('d/m/Y à H:i') }}, {{ $author }} a écrit :</div>
        <div style="white-space: pre-wrap; font-size: .875rem; line-height: 1.55;">{{ $message->body_text }}</div>
    </div>
@endif
