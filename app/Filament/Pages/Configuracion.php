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
            'fb_pixel' => Setting::get('fb_pixel', ''),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('envio')->label('Costo de envío')->numeric()->prefix('$')->required()
                ->helperText('Se suma al total de cada pedido.'),
            TextInput::make('envio_tiempo')->label('Tiempo de entrega')->required()
                ->helperText('Texto que se muestra en la página del producto. Ej: 24 horas hábiles'),
            TextInput::make('fb_pixel')->label('ID del Píxel de Facebook')
                ->helperText('Pega el ID (número) de tu Píxel de Meta para medir tu campaña. Déjalo vacío para desactivarlo.')
                ->placeholder('Ej: 1234567890123456'),
        ])->statePath('data');
    }

    public function guardar(): void
    {
        $data = $this->form->getState();
        Setting::put('envio', $data['envio']);
        Setting::put('envio_tiempo', $data['envio_tiempo']);
        Setting::put('fb_pixel', trim($data['fb_pixel'] ?? ''));
        Notification::make()->title('Configuración guardada')->success()->send();
    }
}
