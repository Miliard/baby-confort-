<?php

namespace App\Filament\Pages;

use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use App\Services\OrdenWhatsappParser;
use App\Services\SistrackExcel;

/**
 * Prepara guías para Sistrack, pensada para usarse desde el teléfono.
 *
 * Flujo: pegar la "Orden de envío" de WhatsApp → se llenan los campos →
 * "Agregar a la lista". Se repite con varias órdenes y al final se descarga
 * UN Excel con todas para subirlo a Importación masiva de Sistrack.
 *
 * La lista vive en la sesión (no toca la base de datos ni crea pedidos).
 */
class CrearGuia extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-bolt';
    protected static ?string $navigationLabel = 'Crear guías';
    protected static ?string $title = 'Crear guías';
    protected static ?int $navigationSort = 1;

    protected static string $view = 'filament.pages.crear-guia';

    public ?array $data = [];
    public array $lista = [];

    public function mount(): void
    {
        $this->lista = session('guias_lista', []);
        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Textarea::make('pegar')->label('1. Pega aquí la orden de WhatsApp')
                ->rows(4)->dehydrated(false)->live(debounce: 700)
                ->placeholder("Orden de envío:🚚\n✅Nombre completo:\n...\n✅Dirección:\n...\n✅producto:\n...")
                ->afterStateUpdated(function ($state, Forms\Set $set) {
                    $r = OrdenWhatsappParser::parsear((string) $state);
                    if ($r['nombre'])    $set('nombre', $r['nombre']);
                    if ($r['direccion']) $set('direccion', $r['direccion']);
                    // Municipio y departamento detectados del catálogo (se pueden corregir a mano).
                    if (! empty($r['departamento'])) {
                        $set('departamento', $r['departamento']);
                        $set('municipio', $r['municipio_nombre'] ?: null);
                    }
                    if (! empty($r['items'])) {
                        $set('descripcion', collect($r['items'])
                            ->map(fn ($i) => ((int) ($i['cantidad'] ?? 1)) . ' ' . trim((string) ($i['producto'] ?? '')))
                            ->implode(', '));
                        $set('cobrar', number_format(collect($r['items'])
                            ->sum(fn ($i) => ((int) ($i['cantidad'] ?? 1)) * (float) ($i['precio'] ?? 0))
                            + (float) ($r['envio'] ?? 0), 2, '.', ''));
                    }
                }),

            Forms\Components\TextInput::make('nombre')->label('2. Nombre del cliente')->required(),
            Forms\Components\TextInput::make('telefono')->label('3. Teléfono')->tel()->required()->placeholder('7777-7777'),

            Forms\Components\Select::make('departamento')->label('4. Departamento')
                ->options(fn () => array_combine(array_keys(config('municipios_sv', [])), array_keys(config('municipios_sv', []))))
                ->searchable()->live()->required()
                ->afterStateUpdated(fn (Forms\Set $set) => $set('municipio', null)),

            Forms\Components\Select::make('municipio')->label('5. Municipio')
                ->options(function (Forms\Get $get) {
                    $m = config('municipios_sv', [])[$get('departamento')] ?? [];
                    return $m ? array_combine($m, $m) : [];
                })
                ->searchable()->required()->placeholder('Elige primero el departamento'),

            Forms\Components\Textarea::make('direccion')->label('6. Dirección')->rows(2)->required(),
            Forms\Components\Textarea::make('descripcion')->label('7. Qué lleva el paquete')->rows(2)->required()
                ->placeholder('Ej: 2 Calzoncito Magic M'),

            Forms\Components\TextInput::make('cobrar')->label('8. Cobrar al entregar ($)')
                ->numeric()->prefix('$')->default('0')
                ->helperText('Pon 0 si ya está pagado.'),
        ])->columns(1)->statePath('data');
    }

    public function agregar(): void
    {
        // Si falta algo, avisa y salta al primer campo vacío (no solo un mensaje).
        $faltantes = [
            'nombre'      => 'el nombre del cliente',
            'telefono'    => 'el teléfono',
            'departamento'=> 'el departamento',
            'municipio'   => 'el municipio',
            'direccion'   => 'la dirección',
            'descripcion' => 'qué lleva el paquete',
        ];
        foreach ($faltantes as $campo => $etiqueta) {
            if (trim((string) ($this->data[$campo] ?? '')) === '') {
                Notification::make()->title('Falta ' . $etiqueta)->warning()->send();
                $this->dispatch('enfocar-campo', campo: $campo);
                return;
            }
        }

        $d = $this->form->getState();

        $this->lista[] = [
            'nombre'      => $d['nombre'],
            'telefono'    => $d['telefono'],
            'direccion'   => $d['direccion'],
            'municipio'   => $d['municipio'],
            'departamento'=> $d['departamento'],
            'descripcion' => $d['descripcion'],
            'cobrar'      => (float) ($d['cobrar'] ?? 0),
        ];

        session(['guias_lista' => $this->lista]);
        $this->form->fill();

        Notification::make()->title('✅ Agregada (' . count($this->lista) . ' en la lista)')->success()->send();
    }

    public function quitar(int $i): void
    {
        unset($this->lista[$i]);
        $this->lista = array_values($this->lista);
        session(['guias_lista' => $this->lista]);
    }

    public function vaciar(): void
    {
        $this->lista = [];
        session()->forget('guias_lista');
        Notification::make()->title('Lista vaciada')->success()->send();
    }

    public function descargar()
    {
        if (empty($this->lista)) {
            Notification::make()->title('No hay guías en la lista')->warning()->send();
            return null;
        }

        $nombre = 'sistrack_' . now()->format('Y-m-d_Hi') . '.xlsx';
        $path   = storage_path('app/' . $nombre);

        SistrackExcel::generarDesdeLista($this->lista, $path);

        return response()->download($path, $nombre)->deleteFileAfterSend();
    }
}
