<?php

namespace App\Console\Commands;

use App\Models\ConversationMessage;
use App\Models\Organization;
use App\Services\WebsiteContactResolver;
use App\Support\WebsiteFormEmail;
use App\Tenancy\OrganizationContext;
use Illuminate\Console\Command;

class NormalizeWebsiteFormNotifications extends Command
{
    protected $signature = 'cremona:normalize-website-form-notifications
        {--organization= : Identifiant numérique de l’organisation à traiter}
        {--dry-run : Affiche les éléments reconnus sans les modifier}';

    protected $description = 'Rattache les anciennes notifications de formulaire à leurs contacts, sans modifier les messages archivés.';

    public function handle(OrganizationContext $context, WebsiteContactResolver $contacts): int
    {
        $organizations = Organization::withoutGlobalScopes()->orderBy('id');
        if (filled($this->option('organization'))) {
            $organizations->whereKey((int) $this->option('organization'));
        }

        $normalized = 0;
        $linked = 0;
        foreach ($organizations->cursor() as $organization) {
            [$organizationNormalized, $organizationLinked] = $context->run($organization, function () use ($contacts): array {
                $normalized = 0;
                $linked = 0;

                ConversationMessage::query()
                    ->with('conversation')
                    ->orderBy('id')
                    ->each(function (ConversationMessage $message) use ($contacts, &$normalized, &$linked): void {
                        $form = WebsiteFormEmail::parse($message->subject ?? '', $message->body_text);
                        if ($form === null || $message->conversation === null) {
                            return;
                        }

                        $normalized++;
                        if ($this->option('dry-run')) {
                            return;
                        }

                        $resolved = $contacts->resolve(
                            $form['name'],
                            $form['email'],
                            $form['phone'],
                            'website_form_email',
                        );
                        $conversation = $message->conversation;
                        $updates = [];
                        if ($resolved !== null && $conversation->person_id === null) {
                            $updates['person_id'] = $resolved['person']->getKey();
                            $linked++;
                        }
                        if ($conversation->subject === $message->subject) {
                            $updates['subject'] = $form['subject'] ?? 'Message du site';
                        }
                        if ($updates !== []) {
                            $conversation->update($updates);
                        }
                    });

                return [$normalized, $linked];
            });
            $normalized += $organizationNormalized;
            $linked += $organizationLinked;
        }

        $prefix = $this->option('dry-run') ? 'Simulation : ' : '';
        $this->info("{$prefix}{$normalized} notification(s) reconnue(s), {$linked} contact(s) rattaché(s).");

        return self::SUCCESS;
    }
}
