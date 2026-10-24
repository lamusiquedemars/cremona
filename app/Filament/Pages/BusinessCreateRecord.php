<?php

namespace App\Filament\Pages;

use Filament\Resources\Pages\CreateRecord;
use Illuminate\Contracts\Support\Htmlable;

abstract class BusinessCreateRecord extends CreateRecord
{
    public function getTitle(): string|Htmlable
    {
        return $this->createTitle(static::getResource()::getModelLabel());
    }

    public function getBreadcrumb(): string
    {
        return 'Nouveau';
    }

    private function createTitle(string $label): string
    {
        return match ($label) {
            'entreprise', 'location', 'facture', 'tâche', 'campagne', 'organisation', 'prestation', 'formule', 'gamme', 'catégorie', 'ligne de devis enregistrée' => 'Nouvelle '.$label,
            default => 'Nouveau '.$label,
        };
    }

}
