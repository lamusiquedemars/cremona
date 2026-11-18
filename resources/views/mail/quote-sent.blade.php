<p>Bonjour{{ filled($recipient = $quote->person?->display_name ?? $quote->company?->name ?? $quote->company?->legal_name) ? ' '.$recipient : '' }},</p>

<p>Veuillez trouver en pièce jointe notre devis {{ $quote->reference }}{{ filled($quote->title) ? ' — '.$quote->title : '' }}.</p>

<p>Nous restons à votre disposition pour toute question.</p>
