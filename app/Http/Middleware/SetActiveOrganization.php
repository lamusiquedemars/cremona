<?php

namespace App\Http\Middleware;

use App\Models\Organization;
use App\Tenancy\OrganizationContext;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;

class SetActiveOrganization
{
    public function __construct(private readonly OrganizationContext $context) {}

    public function handle(Request $request, Closure $next): mixed
    {
        $tenant = Filament::getTenant();

        abort_unless($tenant instanceof Organization, 404);

        $this->context->set($tenant);
        $previousLocale = app()->getLocale();
        app()->setLocale($tenant->interfaceLocale());

        try {
            return $next($request);
        } finally {
            $this->context->forget();
            app()->setLocale($previousLocale);
        }
    }
}
