<?php

namespace App\Models;

use App\Tenancy\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['label', 'description', 'default_duration_minutes', 'brevo_meeting_name', 'quote_line_template_id', 'is_active'])]
class AppointmentType extends Model
{
    use BelongsToOrganization;

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function quoteLineTemplate(): BelongsTo
    {
        return $this->belongsTo(QuoteLineTemplate::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }
}
