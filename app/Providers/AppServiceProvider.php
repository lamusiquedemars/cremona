<?php

namespace App\Providers;

use App\Tenancy\OrganizationContext;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(OrganizationContext::class, fn (): OrganizationContext => new OrganizationContext);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (! $this->app->environment('testing')) {
            return;
        }

        $connection = (string) config('database.default');
        $database = (string) config("database.connections.{$connection}.database");

        if (str_ends_with($database, '_testing')) {
            return;
        }

        throw new RuntimeException(
            "Tests blocked: database [{$database}] is not a dedicated MySQL testing database."
        );
    }
}
