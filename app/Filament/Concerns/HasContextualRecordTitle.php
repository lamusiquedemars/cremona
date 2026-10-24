<?php

namespace App\Filament\Concerns;

/**
 * Keeps page headings about the record being consulted, rather than the action
 * that led to it. The action belongs in the breadcrumb ("Voir", "Modifier").
 */
trait HasContextualRecordTitle
{
    protected function contextualRecordTitle(): string
    {
        $record = $this->getRecord();

        foreach (['title', 'display_name', 'name', 'label', 'subject', 'reference', 'email'] as $attribute) {
            $value = $record->getAttribute($attribute);

            if (filled($value)) {
                return (string) $value;
            }
        }

        return (string) $this->getRecordTitle();
    }
}
