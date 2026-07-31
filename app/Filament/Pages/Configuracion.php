<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class Configuracion extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';
    protected static ?string $navigationLabel = 'Configuración';
    protected static ?string $title = 'Configuración';
    protected static string $view = 'filament.pages.configuracion';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'envio' => Setting::get('envio', '2.50'),
            'envio_tiempo' => Setting::get('envio_tiempo', '24 horas hábiles'),
            'envio_gratis_desde' => Setting::get('envio_gratis_desde', '0'),
            'fb_pixel' => Setting::get('fb_pixel', ''),
            'telegram_token' => Setting::get('telegram_token', ''),
            'telegram_chat' => Setting::get('telegram_chat', ''),
            'sistrack_token' => Setting::get('sistrack_token', ''),
            'sistrack_sender_id' => Setting::get('sistrack_sender_id', ''),
            'sistrack_dominio' => Setting::get('sistrack_dominio', 'expresselsalvador'),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('envio')->label('Costo de envío')->numeric()->prefix('$')->required()
                ->helperText('Se suma al total de cada pedido.'),
            TextInput::make('envio_tiempo')->label('Tiempo de entrega')->required()
                ->helperText('Texto que se muestra en la página del producto. Ej: 24 horas hábiles'),
            TextInput::make('envio_gratis_desde')->label('Envío gratis desde ($)')->numeric()->prefix('$')
                ->helperText('Si el cliente compra este monto o más (en productos), el envío es gratis y se muestra una barra "Te faltan $X para envío gratis". Pon 0 para desactivarlo.')
                ->placeholder('Ej: 25'),
            TextInput::make('fb_pixel')->label('ID del Píxel de Facebook')
                ->helperText('Pega el ID (número) de tu Píxel de Meta para medir tu campaña. Déjalo vacío para desactivarlo.')
                ->placeholder('Ej: 1234567890123456'),

            \Filament\Forms\Components\Section::make('🔔 Alerta de pedidos por Telegram')
                ->description('Recibe un aviso instantáneo en tu Telegram cada vez que entra un pedido. Deja los campos vacíos para desactivarlo.')
                ->schema([
                    TextInput::make('telegram_token')->label('Token del bot')
                        ->helperText('Te lo da @BotFather al crear tu bot. Ej: 123456789:AAG...')
                        ->placeholder('123456789:AAG...'),
                    TextInput::make('telegram_chat')->label('Chat ID')
                        ->helperText('Tu ID de chat (te lo da @userinfobot). Ahí llegarán los avisos.')
                        ->placeholder('Ej: 987654321'),
                ])->columns(2),

            \Filament\Forms\Components\Section::make('🚚 Guías Express El Salvador (Sistrack)')
                ->description('Con esto el botón "⚡ Crear guía" de los pedidos crea la guía automáticamente en Sistrack. Pídele a Express El Salvador tu token de API y tu ID de remitente. Deja los campos vacíos para desactivarlo.')
                ->schema([
                    TextInput::make('sistrack_token')->label('Token de API')
                        ->helperText('Token Bearer de tu cuenta de Sistrack.')
                        ->placeholder('Pega aquí tu token'),
                    TextInput::make('sistrack_sender_id')->label('ID de remitente (tu negocio)')
                        ->helperText('El ID de Baby-Confort como remitente dentro de Sistrack.')
                        ->placeholder('Ej: 5'),
                    TextInput::make('sistrack_dominio')->label('Subdominio del courier')
                        ->helperText('Normalmente no se cambia.')
                        ->placeholder('expresselsalvador'),
                ])->columns(3),
        ])->statePath('data');
    }

    public function guardar(): void
    {
        $data = $this->form->getState();
        Setting::put('envio', $data['envio']);
        Setting::put('envio_tiempo', $data['envio_tiempo']);
        Setting::put('envio_gratis_desde', $data['envio_gratis_desde'] ?? '0');
        Setting::put('fb_pixel', trim($data['fb_pixel'] ?? ''));
        Setting::put('telegram_token', trim($data['telegram_token'] ?? ''));
        Setting::put('telegram_chat', trim($data['telegram_chat'] ?? ''));
        Setting::put('sistrack_token', trim($data['sistrack_token'] ?? ''));
        Setting::put('sistrack_sender_id', trim($data['sistrack_sender_id'] ?? ''));
        Setting::put('sistrack_dominio', trim($data['sistrack_dominio'] ?? '') ?: 'expresselsalvador');
        Notification::make()->title('Configuración guardada')->success()->send();
    }
}
