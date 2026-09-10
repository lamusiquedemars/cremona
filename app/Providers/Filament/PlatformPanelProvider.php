<?php

namespace App\Providers\Filament;

use App\Filament\Auth\CremonaLogin;
use App\Filament\Platform\Pages\GoogleAdsInfrastructure;
use App\Filament\Resources\Organizations\OrganizationResource;
use App\Filament\Resources\Users\UserResource;
use App\Filament\Widgets\PlatformOverview;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class PlatformPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('platform')
            ->path('platform')
            ->login(CremonaLogin::class)
            ->brandName(fn (): string => config('cremona.pwa.name').' — Administration')
            ->favicon(fn (): string => asset(config('cremona.pwa.icon_192')))
            ->renderHook(PanelsRenderHook::HEAD_END, fn (): string => view('pwa.head')->render())
            ->renderHook(PanelsRenderHook::BODY_END, fn (): string => view('pwa.service-worker')->render())
            ->resources([OrganizationResource::class, UserResource::class])
            ->pages([Dashboard::class, GoogleAdsInfrastructure::class])
            ->widgets([PlatformOverview::class, AccountWidget::class])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([Authenticate::class]);
    }
}
