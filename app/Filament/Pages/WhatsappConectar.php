<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use App\Services\WhatsappApi;
use Filament\Pages\Page;

/**
 * La pantalla del enchufe: se usa una vez y no se vuelve a tocar.
 *
 * Solo la ve el dueño. Los colaboradores no tienen por qué entrar acá, y de
 * hecho no deberían: lo que se conecta es el número del negocio.
 */
class WhatsappConectar extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-link';
    protected static ?string $navigationLabel = 'Conectar WhatsApp';
    protected static ?string $title = 'Conectar WhatsApp';
    protected static ?int $navigationSort = 9;

    protected static string $view = 'filament.pages.whatsapp-conectar';

    /** Los colaboradores de solo chat no ven esta opción. */
    public static function shouldRegisterNavigation(): bool
    {
        return ! (bool) (auth()->user()?->solo_chat ?? false);
    }

    public function mount(): void
    {
        abort_if((bool) (auth()->user()?->solo_chat ?? false), 403);
    }

    /** ¿Ya se conectó alguna vez? */
    public function conectado(): bool
    {
        return filled(WhatsappApi::token()) && filled(WhatsappApi::phoneId());
    }

    public function numero(): ?string
    {
        return WhatsappApi::numeroLegible();
    }

    public function phoneId(): ?string
    {
        return WhatsappApi::phoneId();
    }

    public function wabaId(): ?string
    {
        return WhatsappApi::wabaId();
    }

    public function conectadoEl(): ?string
    {
        $f = Setting::get('whatsapp_conectado_at');
        return $f ? \Illuminate\Support\Carbon::parse($f)->format('d/m/Y H:i') : null;
    }

    public function appId(): string
    {
        return (string) config('whatsapp.app_id');
    }

    public function configId(): string
    {
        return (string) config('whatsapp.config_id');
    }

    /** Sin esto el diálogo de Meta abre y falla sin explicar por qué. */
    public function listoParaConectar(): bool
    {
        return filled($this->appId())
            && filled($this->configId())
            && filled(config('whatsapp.app_secret'));
    }
}
