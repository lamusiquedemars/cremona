<?php

namespace App\Models;

use App\Tenancy\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['display_name', 'legal_name', 'contact_name', 'email', 'phone', 'website', 'address_line_1', 'address_line_2', 'postal_code', 'city', 'country_code', 'registration_number', 'vat_number', 'legal_notice'])]
class OrganizationLegalProfile extends Model
{
    use BelongsToOrganization;

    /** @return array<string, string|null> */
    public function snapshot(): array
    {
        return $this->only(['display_name', 'legal_name', 'contact_name', 'email', 'phone', 'website', 'address_line_1', 'address_line_2', 'postal_code', 'city', 'country_code', 'registration_number', 'vat_number', 'legal_notice']);
    }
}
