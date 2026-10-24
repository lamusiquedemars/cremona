<?php

namespace App\Models;

use App\Tenancy\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['reference_prefix', 'next_reference_number', 'currency', 'default_due_days', 'default_tax_rate', 'vat_regime', 'default_payment_terms', 'payment_instructions', 'late_payment_terms', 'regulatory_provider', 'super_pdp_enabled', 'super_pdp_connection_status', 'pennylane_copy_enabled', 'pennylane_sales_email', 'rental_billing_mode'])]
class OrganizationInvoiceSettings extends Model
{
    use BelongsToOrganization;

    protected function casts(): array
    {
        return [
            'next_reference_number' => 'integer',
            'default_due_days' => 'integer',
            'default_tax_rate' => 'decimal:2',
            'super_pdp_enabled' => 'boolean',
            'pennylane_copy_enabled' => 'boolean',
            'pennylane_sales_email' => 'encrypted',
        ];
    }
}
