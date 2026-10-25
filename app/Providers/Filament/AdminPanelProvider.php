<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->favicon(asset('favicon-32.png'))
            ->brandName('Baby-Confort')
            ->colors([
                'primary' => Color::Amber,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            // Sin el escritorio de bienvenida (estaba vacío): al entrar abre Pedidos.
            ->homeUrl(fn () => \App\Filament\Resources\OrderResource::getUrl('index'))
            // El menú en 4 grupos (más los de WhatsApp). Tienda y Ajustes se tocan poco: plegados.
            ->navigationGroups([
                \Filament\Navigation\NavigationGroup::make('Ventas'),
                \Filament\Navigation\NavigationGroup::make('Envíos'),
                \Filament\Navigation\NavigationGroup::make('WhatsApp'),
                \Filament\Navigation\NavigationGroup::make('Tienda')->collapsed(),
                \Filament\Navigation\NavigationGroup::make('Ajustes')->collapsed(),
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            // Script de la subida manual de fotos. Va en el panel entero para
            // que esté listo cuando se abre la ventana (dentro de una ventana
            // de Filament los <script> no siempre llegan a ejecutarse).
            ->renderHook(
                \Filament\View\PanelsRenderHook::BODY_END,
                fn (): string => view('filament.partials.js-subir-foto')->render(),
            );
    }
}
