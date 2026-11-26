<p>Bonjour{{ filled($recipient = data_get($invoice->recipient_snapshot, 'name')) ? ' '.$recipient : '' }},</p>

<p>Veuillez trouver en pièce jointe une copie de votre facture {{ $invoice->reference }}{{ filled($invoice->title) ? ' — '.$invoice->title : '' }}.</p>

<p>Nous restons à votre disposition pour toute question.</p>
