<?php

namespace App\Providers\Filament;

use App\Filament\Pages\CrearGuia;
use App\Filament\Pages\Whatsapp;
use App\Filament\Pages\WhatsappConectar;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * Panel aparte solo para atender WhatsApp.
 *
 * Vive en /chat, y se entra por baby-confort.shop/whatsapp. Tiene únicamente
 * dos pantallas: el inbox y el armador de guías, que hace falta para el botón
 * "Procesar orden".
 *
 * La razón de que exista: los colaboradores contestan mensajes, pero NO tienen
 * por qué ver el Cierre del día, las remuneraciones ni los números del negocio.
 * Acá no hay forma de llegar a eso.
 */
class ChatPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('chat')
            ->path('chat')
            ->login()
            ->favicon(asset('favicon-32.png'))
            ->brandName('Baby-Confort · Mensajes')
            ->colors([
                'primary' => Color::Emerald,
            ])
            // Sin descubrimiento automático: acá entra solo lo que se nombra.
            // "Conectar WhatsApp" está en la lista pero se esconde sola para los
            // colaboradores de solo chat: la enchufa el dueño, una vez.
            ->pages([
                Whatsapp::class,
                CrearGuia::class,
                WhatsappConectar::class,
            ])
            // Las respuestas rápidas también viven acá: se crean desde el
            // teléfono, que es donde uno se da cuenta de que le falta una.
            ->resources([
                \App\Filament\Resources\RespuestaRapidaResource::class,
                \App\Filament\Resources\WaEtiquetaResource::class,
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
            ]);
    }
}
