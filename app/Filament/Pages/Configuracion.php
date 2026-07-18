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
            'cat_bebe' => Setting::get('cat_bebe', 'Para bebé'),
            'cat_accesorios' => Setting::get('cat_accesorios', 'Accesorios para bebé'),
            'cat_mujer' => Setting::get('cat_mujer', 'Para mujer'),
            'cat_adulto' => Setting::get('cat_adulto', 'Para adulto'),
            'telegram_token' => Setting::get('telegram_token', ''),
            'telegram_chat' => Setting::get('telegram_chat', ''),
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

            \Filament\Forms\Components\Section::make('Nombres de las categorías')
                ->description('Son los nombres que se muestran en el menú ☰ y en los botones de la portada. Cambiar el texto no afecta a los productos ya asignados.')
                ->schema([
                    TextInput::make('cat_bebe')->label('Categoría 1 (bebé)')->placeholder('Para bebé'),
                    TextInput::make('cat_accesorios')->label('Categoría 2 (accesorios)')->placeholder('Accesorios para bebé'),
                    TextInput::make('cat_mujer')->label('Categoría 3 (mujer)')->placeholder('Para mujer'),
                    TextInput::make('cat_adulto')->label('Categoría 4 (adulto)')->placeholder('Para adulto'),
                ])->columns(2),

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
        ])->statePath('data');
    }

    public function guardar(): void
    {
        $data = $this->form->getState();
        Setting::put('envio', $data['envio']);
        Setting::put('envio_tiempo', $data['envio_tiempo']);
        Setting::put('envio_gratis_desde', $data['envio_gratis_desde'] ?? '0');
        Setting::put('fb_pixel', trim($data['fb_pixel'] ?? ''));
        Setting::put('cat_bebe', trim($data['cat_bebe'] ?? '') ?: 'Para bebé');
        Setting::put('cat_accesorios', trim($data['cat_accesorios'] ?? '') ?: 'Accesorios para bebé');
        Setting::put('cat_mujer', trim($data['cat_mujer'] ?? '') ?: 'Para mujer');
        Setting::put('cat_adulto', trim($data['cat_adulto'] ?? '') ?: 'Para adulto');
        Setting::put('telegram_token', trim($data['telegram_token'] ?? ''));
        Setting::put('telegram_chat', trim($data['telegram_chat'] ?? ''));
        Notification::make()->title('Configuración guardada')->success()->send();
    }
}
