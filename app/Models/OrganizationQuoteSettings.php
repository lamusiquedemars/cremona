<?php

namespace App\Models;

use App\Tenancy\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['default_validity_days', 'default_payment_terms', 'default_tax_note', 'terms_url'])]
class OrganizationQuoteSettings extends Model
{
    use BelongsToOrganization;
}
