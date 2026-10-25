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
            // Sin barra lateral: la navegación va arriba, en una franja fina.
            // Acá se atiende gente todo el día, así que cada píxel de ancho es
            // nombre de cliente y cada píxel de alto es contexto de la charla.
            ->topNavigation()
            ->maxContentWidth('full')
            ->sidebarCollapsibleOnDesktop(false)
            // Sin descubrimiento automático: acá entra solo lo que se nombra.
            // "Conectar WhatsApp" está en la lista pero se esconde sola para los
            // colaboradores de solo chat: la enchufa el dueño, una vez.
            ->pages([
                Whatsapp::class,
                CrearGuia::class,
                \App\Filament\Pages\SeguimientoExpress::class,
                WhatsappConectar::class,
            ])
            // Las respuestas rápidas también viven acá: se crean desde el
            // teléfono, que es donde uno se da cuenta de que le falta una.
            ->resources([
                \App\Filament\Resources\RespuestaRapidaResource::class,
                \App\Filament\Resources\WaEtiquetaResource::class,
                \App\Filament\Resources\WaFotoResource::class,
            ])
            // Con esto Android y iPhone ofrecen "Agregar a pantalla de inicio",
            // y al abrirlo desde ahí arranca sin barras del navegador: ocupa
            // toda la pantalla, como una aplicación de verdad.
            // En el teléfono, las secciones van en el menú de la "A" (arriba a la
            // derecha) y el ☰ abre la lista de chats. Así, dentro de un chat, se
            // salta a otro sin volver atrás.
            ->userMenuItems([
                'nav-whatsapp' => \Filament\Navigation\MenuItem::make()->label('WhatsApp')
                    ->icon('heroicon-o-chat-bubble-left-right')->sort(1)
                    ->url(fn () => Whatsapp::getUrl()),
                'nav-guias' => \Filament\Navigation\MenuItem::make()->label('Guías')
                    ->icon('heroicon-o-bolt')->sort(2)
                    ->url(fn () => CrearGuia::getUrl()),
                'nav-seguimiento' => \Filament\Navigation\MenuItem::make()->label('Seguimiento Express')
                    ->icon('heroicon-o-truck')->sort(3)
                    ->url(fn () => \App\Filament\Pages\SeguimientoExpress::getUrl()),
                'nav-respuestas' => \Filament\Navigation\MenuItem::make()->label('Respuestas rápidas')
                    ->icon('heroicon-o-chat-bubble-bottom-center-text')->sort(4)
                    ->visible(fn () => \App\Filament\Resources\RespuestaRapidaResource::canAccess())
                    ->url(fn () => \App\Filament\Resources\RespuestaRapidaResource::getUrl('index')),
                'nav-etiquetas' => \Filament\Navigation\MenuItem::make()->label('Etiquetas')
                    ->icon('heroicon-o-tag')->sort(5)
                    ->visible(fn () => \App\Filament\Resources\WaEtiquetaResource::canAccess())
                    ->url(fn () => \App\Filament\Resources\WaEtiquetaResource::getUrl('index')),
                'nav-conectar' => \Filament\Navigation\MenuItem::make()->label('Conectar WhatsApp')
                    ->icon('heroicon-o-link')->sort(6)
                    ->visible(fn () => WhatsappConectar::shouldRegisterNavigation())
                    ->url(fn () => WhatsappConectar::getUrl()),
            ])
            // ☰ propio: abre los chats. El de Filament (que abría las secciones) se esconde.
            ->renderHook(
                \Filament\View\PanelsRenderHook::TOPBAR_START,
                fn (): string => '<style>.fi-topbar-open-sidebar-btn,.fi-topbar-close-sidebar-btn{display:none !important}'
                    . '.bc-chats-btn{display:inline-flex;align-items:center;justify-content:center;width:40px;height:40px;'
                    . 'margin-inline-start:-8px;border:none;background:transparent;color:inherit;border-radius:10px;cursor:pointer}'
                    . '.bc-chats-btn:active{background:rgba(120,140,170,.18)}'
                    . '@media(min-width:1024px){.bc-chats-btn{display:none}}</style>'
                    . '<button type="button" class="bc-chats-btn" aria-label="Ver los chats" onclick="bcAbrirChats()">'
                    . '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" width="26" height="26"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5"/></svg>'
                    . '</button>'
                    . '<script>function bcAbrirChats(){var h=document.documentElement,w=document.querySelector(".wa");'
                    . 'if(!w){location.href=' . json_encode(Whatsapp::getUrl()) . ';return;}'
                    . 'if(w.classList.contains("wa--abierta")&&matchMedia("(max-width:900px)").matches){h.classList.toggle("wa--cajon");return;}'
                    . 'var l=document.querySelector(".wa-lista");if(l)l.scrollTo({top:0,behavior:"smooth"});}</script>'
            )
            ->renderHook(
                'panels::head.end',
                // interactive-widget=resizes-content es la clave del teclado:
                // sin eso, Android dibuja el teclado ENCIMA de la página en vez
                // de achicarla, y tapa justo el cuadro donde se escribe.
                fn (): string => '<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover, interactive-widget=resizes-content">'
                    . '<link rel="manifest" href="/chat-manifest.json">'
                    . '<meta name="theme-color" content="#0f1828">'
                    . '<meta name="mobile-web-app-capable" content="yes">'
                    . '<meta name="apple-mobile-web-app-capable" content="yes">'
                    . '<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">'
                    . '<meta name="apple-mobile-web-app-title" content="Mensajes">'
                    . '<link rel="apple-touch-icon" href="/apple-touch-icon.png">'
            )
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
