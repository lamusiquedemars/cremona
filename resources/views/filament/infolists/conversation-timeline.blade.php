@php
    use App\Enums\MessageDirection;
    use App\Enums\MessageParticipantRole;
    use App\Support\EmailReplyComposer;
    use App\Support\EmailReplyExcerpt;

    $messages = collect($getState());
    $latest = $messages->last();
    $history = $messages->slice(0, -1)->reverse()->values();
@endphp

<x-dynamic-component :component="$getEntryWrapperView()" :entry="$entry">
    <div class="cremona-conversation-thread">
        @if ($latest)
            @php
                $message = $latest;
                $from = $message->participants->first(fn ($participant) => $participant->role === MessageParticipantRole::From);
                $to = $message->participants->first(fn ($participant) => $participant->role === MessageParticipantRole::To);
                $inbound = $message->direction === MessageDirection::Inbound;
                $author = $inbound ? ($from?->name ?: $from?->address ?: __('cremona.crm.unknown_sender')) : __('cremona.crm.you');
                $recipient = $inbound ? ($to?->name ?: $to?->address ?: $message->mailbox?->address) : ($to?->name ?: $to?->address);
                $parts = EmailReplyExcerpt::split($message->body_text);
                $previous = $messages->slice(0, -1)->last();

                if (! $inbound && $previous !== null) {
                    $previousFrom = $previous->participants->first(fn ($participant) => $participant->role === MessageParticipantRole::From);
                    $composed = app(EmailReplyComposer::class)->compose(
                        $message->body_text,
                        $previous->body_text,
                        $previousFrom?->name ?: $previousFrom?->address ?: $previous->author?->name ?: $previous->mailbox?->display_name,
                        $previous->authored_at,
                        $message->organization->timezone(),
                    );
                    $parts = EmailReplyExcerpt::split($composed['text']);
                }
            @endphp

            <article class="cremona-conversation-message">
                <header class="cremona-conversation-message-header">
                    <div>
                        <div class="cremona-conversation-message-author">{{ __('cremona.crm.from', ['name' => $author]) }}</div>
                        <div class="cremona-conversation-message-recipient">{{ __('cremona.crm.to', ['name' => $recipient ?: '—']) }}</div>
                    </div>
                    <time class="cremona-conversation-message-date">{{ __('cremona.crm.on_date', ['date' => $message->authored_at?->format('d/m/Y H:i')]) }}</time>
                </header>

                @if ($message->subject)
                    <div class="cremona-conversation-message-subject">{{ __('cremona.crm.subject').': '.$message->subject }}</div>
                @endif

                <div class="cremona-conversation-message-body">{{ $parts['reply'] }}</div>

                @if ($parts['quoted'])
                    <details class="cremona-conversation-quoted">
                        <summary>{{ __('cremona.crm.show_quoted_content') }}</summary>
                        <pre>{{ EmailReplyExcerpt::quotedForDisplay($parts['quoted']) }}</pre>
                    </details>
                @endif
            </article>
        @endif

        @if ($history->isNotEmpty())
            <details class="cremona-conversation-history">
                <summary>{{ trans_choice('cremona.crm.history', $history->count(), ['count' => $history->count()]) }}</summary>
                <div>
                    @foreach ($history as $message)
                        @php
                            $from = $message->participants->first(fn ($participant) => $participant->role === MessageParticipantRole::From);
                        @endphp
                        <div class="cremona-conversation-history-item">
                            <div>{{ $from?->name ?: $from?->address ?: __('cremona.crm.you') }}</div>
                            <div>{{ $message->authored_at?->format('d/m/Y H:i') }} · {{ $message->subject ?: __('cremona.crm.untitled') }}</div>
                        </div>
                    @endforeach
                </div>
            </details>
        @endif
    </div>
</x-dynamic-component>
