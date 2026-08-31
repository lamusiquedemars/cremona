@php
    use App\Enums\MessageDirection;
    use App\Enums\MessageParticipantRole;
    use App\Support\EmailReplyComposer;
    use App\Support\EmailReplyExcerpt;

    $messages = $getState();
    $latest = $messages->last();
    $history = $messages->slice(0, -1)->reverse()->values();
@endphp

<x-dynamic-component :component="$getEntryWrapperView()" :entry="$entry">
    <div style="display: grid; gap: 1rem;">
        @if ($latest)
            @php
                $message = $latest;
                $from = $message->participants->first(fn ($participant) => $participant->role === MessageParticipantRole::From);
                $to = $message->participants->first(fn ($participant) => $participant->role === MessageParticipantRole::To);
                $inbound = $message->direction === MessageDirection::Inbound;
                $author = $inbound ? ($from?->name ?: $from?->address ?: 'Expéditeur inconnu') : 'Vous';
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

            <article style="border: 1px solid rgb(225 219 208); border-radius: 14px; background: rgb(255 254 251); padding: 1.25rem;">
                <header style="display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; margin-bottom: 1rem;">
                    <div>
                        <div style="font-weight: 650;">De : {{ $author }}</div>
                        <div style="margin-top: .15rem; color: rgb(100 95 88); font-size: .875rem;">À : {{ $recipient ?: '—' }}</div>
                    </div>
                    <time style="color: rgb(100 95 88); font-size: .875rem; white-space: nowrap;">Le {{ $message->authored_at?->format('d/m/Y à H:i') }}</time>
                </header>

                @if ($message->subject)
                    <div style="margin-bottom: .85rem; color: rgb(100 95 88); font-size: .875rem;">Objet : {{ $message->subject }}</div>
                @endif

                <div style="white-space: pre-wrap; line-height: 1.6;">{{ $parts['reply'] }}</div>

                @if ($parts['quoted'])
                    <details style="margin-top: 1rem; color: rgb(100 95 88);">
                        <summary style="cursor: pointer; font-size: .875rem;">Afficher le contenu cité</summary>
                        <pre style="margin: .75rem 0 0; border-left: 2px solid rgb(225 219 208); padding-left: 1rem; white-space: pre-wrap; overflow-wrap: anywhere; font: inherit; line-height: 1.55;">{{ EmailReplyExcerpt::quotedForDisplay($parts['quoted']) }}</pre>
                    </details>
                @endif
            </article>
        @endif

        @if ($history->isNotEmpty())
            <details>
                <summary style="cursor: pointer; font-weight: 600;">Historique ({{ $history->count() }} message{{ $history->count() > 1 ? 's' : '' }})</summary>
                <div style="display: grid; gap: .75rem; margin-top: .75rem;">
                    @foreach ($history as $message)
                        @php
                            $from = $message->participants->first(fn ($participant) => $participant->role === MessageParticipantRole::From);
                        @endphp
                        <div style="border-left: 2px solid rgb(225 219 208); padding-left: 1rem;">
                            <div style="font-weight: 600;">{{ $from?->name ?: $from?->address ?: 'Vous' }}</div>
                            <div style="color: rgb(100 95 88); font-size: .875rem;">{{ $message->authored_at?->format('d/m/Y H:i') }} · {{ $message->subject ?: 'Sans objet' }}</div>
                        </div>
                    @endforeach
                </div>
            </details>
        @endif
    </div>
</x-dynamic-component>
