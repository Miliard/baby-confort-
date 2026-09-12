<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use App\Services\WhatsappApi;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Http;

/**
 * La pantalla del enchufe: se usa una vez y no se vuelve a tocar.
 *
 * Dos botones, nada más:
 *
 *  · Probar        → le pregunta a Meta quién es el número. Si responde, el
 *                    identificador de acceso sirve y apunta al número correcto.
 *  · Suscribir     → le dice a Meta que esta aplicación atiende la cuenta. Sin
 *                    este paso se puede enviar, pero no llega nada.
 *
 * Solo la ve el dueño. Los colaboradores no tienen por qué entrar acá.
 */
class WhatsappConectar extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-link';
    protected static ?string $navigationLabel = 'Conectar WhatsApp';
    protected static ?string $title = 'Conectar WhatsApp';
    protected static ?int $navigationSort = 9;

    protected static string $view = 'filament.pages.whatsapp-conectar';

    /** Lo último que respondió Meta, para mostrarlo tal cual. */
    public ?string $mensaje = null;
    public ?string $tono = null;   // ok | mal

    public static function shouldRegisterNavigation(): bool
    {
        return ! (bool) (auth()->user()?->solo_chat ?? false);
    }

    public function mount(): void
    {
        abort_if((bool) (auth()->user()?->solo_chat ?? false), 403);
    }

    // ── Lo que se muestra en pantalla ────────────────────────────────────────

    public function hayCredenciales(): bool
    {
        return filled(WhatsappApi::token()) && filled(WhatsappApi::phoneId());
    }

    public function phoneId(): ?string
    {
        return WhatsappApi::phoneId();
    }

    public function wabaId(): ?string
    {
        return WhatsappApi::wabaId() ?: (config('whatsapp.waba_id') ?: null);
    }

    public function numero(): ?string
    {
        return WhatsappApi::numeroLegible();
    }

    public function suscrita(): bool
    {
        return (bool) Setting::get('whatsapp_suscrita');
    }

    // ── Botón 1: probar ──────────────────────────────────────────────────────

    public function probar(): void
    {
        if (! $this->hayCredenciales()) {
            $this->aviso('mal', 'Faltan WHATSAPP_TOKEN o WHATSAPP_PHONE_ID en Railway.');
            return;
        }

        try {
            $r = Http::withToken(WhatsappApi::token())->timeout(20)->get(
                'https://graph.facebook.com/' . config('whatsapp.version', 'v21.0') . '/' . WhatsappApi::phoneId(),
                ['fields' => 'display_phone_number,verified_name,platform_type,is_on_biz_app']
            );

            if (! $r->successful()) {
                $this->aviso('mal', 'Meta contestó: ' . $this->porQue($r));
                return;
            }

            // Se guarda para que el resto del panel lo muestre.
            Setting::put('whatsapp_numero', (string) $r->json('display_phone_number'));
            WhatsappApi::olvidarGuardadas();

            $enElTelefono = $r->json('is_on_biz_app') ? 'sí' : 'no';

            $this->aviso('ok', 'Todo bien. Número ' . $r->json('display_phone_number')
                . ' · nombre "' . $r->json('verified_name') . '"'
                . ' · sigue en el teléfono: ' . $enElTelefono . '.');
        } catch (\Throwable $e) {
            $this->aviso('mal', 'No se pudo hablar con Meta: ' . $e->getMessage());
        }
    }

    // ── Botón 2: suscribir ───────────────────────────────────────────────────

    public function suscribir(): void
    {
        if (! $this->hayCredenciales()) {
            $this->aviso('mal', 'Faltan las credenciales en Railway.');
            return;
        }

        if (! $this->wabaId()) {
            $this->aviso('mal', 'No sé a qué cuenta suscribir. Falta WHATSAPP_WABA_ID.');
            return;
        }

        try {
            $r = Http::withToken(WhatsappApi::token())->timeout(20)->post(
                'https://graph.facebook.com/' . config('whatsapp.version', 'v21.0')
                . '/' . $this->wabaId() . '/subscribed_apps'
            );

            if (! $r->successful()) {
                $this->aviso('mal', 'Meta rechazó la suscripción: ' . $this->porQue($r));
                return;
            }

            Setting::put('whatsapp_suscrita', '1');
            Setting::put('whatsapp_waba_id', (string) $this->wabaId());
            Setting::put('whatsapp_conectado_at', now()->toDateTimeString());
            WhatsappApi::olvidarGuardadas();

            $this->aviso('ok', 'Listo. La aplicación ya está suscrita a tu cuenta. '
                . 'Pedile a alguien que le escriba al número y fijate en la pantalla de WhatsApp.');
        } catch (\Throwable $e) {
            $this->aviso('mal', 'No se pudo suscribir: ' . $e->getMessage());
        }
    }

    public function conectadoEl(): ?string
    {
        $f = Setting::get('whatsapp_conectado_at');

        return $f
            ? \Illuminate\Support\Carbon::parse($f)->timezone(config('app.zona_local'))->format('d/m/Y H:i')
            : null;
    }

    // ── Auxiliares ───────────────────────────────────────────────────────────

    /** El motivo real que manda Meta, sin adornos. */
    private function porQue($r): string
    {
        return mb_substr((string) ($r->json('error.message') ?: $r->body()), 0, 300);
    }

    private function aviso(string $tono, string $texto): void
    {
        $this->tono = $tono;
        $this->mensaje = $texto;
    }
}
