@php
    use App\Enums\MessageDirection;
    use App\Enums\MessageParticipantRole;
    use App\Support\EmailReplyExcerpt;
@endphp

<x-dynamic-component :component="$getEntryWrapperView()" :entry="$entry">
    <div style="display: grid; gap: 1rem;">
        @foreach ($getState() as $message)
            @php
                $from = $message->participants->first(fn ($participant) => $participant->role === MessageParticipantRole::From);
                $to = $message->participants->first(fn ($participant) => $participant->role === MessageParticipantRole::To);
                $inbound = $message->direction === MessageDirection::Inbound;
                $author = $inbound ? ($from?->name ?: $from?->address ?: 'Expéditeur inconnu') : 'Vous';
                $recipient = $inbound ? null : ($to?->name ?: $to?->address);
                $parts = EmailReplyExcerpt::split($message->body_text);
            @endphp

            <article style="border: 1px solid rgb(225 219 208); border-radius: 14px; background: {{ $loop->last ? 'rgb(255 254 251)' : 'rgb(250 248 243)' }}; padding: 1.25rem;">
                <header style="display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; margin-bottom: 1rem;">
                    <div>
                        <div style="font-weight: 650;">{{ $author }}</div>
                        @if ($recipient)
                            <div style="margin-top: .15rem; color: rgb(100 95 88); font-size: .875rem;">à {{ $recipient }}</div>
                        @elseif ($from?->name && $from?->address)
                            <div style="margin-top: .15rem; color: rgb(100 95 88); font-size: .875rem;">{{ $from->address }}</div>
                        @endif
                    </div>
                    <time style="color: rgb(100 95 88); font-size: .875rem; white-space: nowrap;">{{ $message->authored_at?->format('d/m/Y H:i') }}</time>
                </header>

                @if ($message->subject)
                    <div style="margin-bottom: .85rem; color: rgb(100 95 88); font-size: .875rem;">{{ $message->subject }}</div>
                @endif

                <div style="white-space: pre-wrap; line-height: 1.6;">{{ $parts['reply'] }}</div>

                @if ($parts['quoted'])
                    <details style="margin-top: 1rem; color: rgb(100 95 88);">
                        <summary style="cursor: pointer; font-size: .875rem;">Afficher le contenu cité</summary>
                        <pre style="margin: .75rem 0 0; border-left: 2px solid rgb(225 219 208); padding-left: 1rem; white-space: pre-wrap; overflow-wrap: anywhere; font: inherit; line-height: 1.55;">{{ $parts['quoted'] }}</pre>
                    </details>
                @endif
            </article>
        @endforeach
    </div>
</x-dynamic-component>
