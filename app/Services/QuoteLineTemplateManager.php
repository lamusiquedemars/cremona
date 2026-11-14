<?php

namespace App\Services;

use App\Models\Quote;
use App\Models\QuoteLine;
use App\Models\QuoteLineTemplate;
use LogicException;

class QuoteLineTemplateManager
{
    public function addToQuote(Quote $quote, QuoteLineTemplate $template, ?float $quantity = null): QuoteLine
    {
        if ($quote->organization_id !== $template->organization_id) {
            throw new LogicException('Le modèle de ligne ne relève pas de l’organisation du devis.');
        }

        $lineQuantity = $quantity ?? (float) $template->default_quantity;
        if ($lineQuantity <= 0) {
            throw new LogicException('La quantité doit être positive.');
        }

        return $quote->lines()->create([
            'quote_line_template_id' => $template->getKey(),
            'template_label_snapshot' => $template->label,
            'kind' => $template->kind,
            'description' => $template->description,
            'quantity' => $lineQuantity,
            'unit_amount' => $template->default_unit_amount,
        ]);
    }
}
