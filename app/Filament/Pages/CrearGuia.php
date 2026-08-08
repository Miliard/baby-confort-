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
        $this->recargarLista();
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
                    if ($r['nombre'])      $set('nombre', $r['nombre']);
                    // El de junto al nombre identifica al cliente; el de la línea "Teléfono:"
                    // es al que llama el repartidor.
                    if ($r['telefono_id']) $set('telefono', $r['telefono_id']);
                    if ($r['telefono'])    $set('telefono_recibe', $r['telefono']);
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
            Forms\Components\TextInput::make('telefono')->label('3. Teléfono junto al nombre (ID del cliente)')
                ->tel()->required()->placeholder('7777-7777')
                ->helperText('El de quien pide. Va pegado al nombre en Sistrack para buscarlo por número.'),

            Forms\Components\TextInput::make('telefono_recibe')->label('4. Teléfono para el repartidor')
                ->tel()->placeholder('Al que debe llamar para entregar')
                ->helperText('El de la línea "Teléfono:" de la orden. Si va vacío, se usa el del cliente.'),

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

    // Solo los dígitos del teléfono, para comparar sin importar cómo se escribió.
    private static function soloDigitos(?string $tel): string
    {
        $d = preg_replace('/\D/', '', (string) $tel);
        if (strlen($d) === 11 && str_starts_with($d, '503')) $d = substr($d, 3);
        return $d;
    }

    #[\Livewire\Attributes\On('agregar-forzado')]
    public function agregarForzado(): void
    {
        $this->agregar(true);
    }

    public function agregar(bool $forzar = false): void
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

        // Evita meter dos veces la misma guía en el lote. Se compara por el ID del
        // cliente (su teléfono). Si es el mismo cliente pero otro pedido, se puede forzar.
        $idNuevo = self::soloDigitos($d['telefono'] ?? '');
        if (! $forzar && $idNuevo !== '') {
            foreach ($this->lista as $g) {
                if (self::soloDigitos($g['telefono'] ?? '') !== $idNuevo) continue;

                $mismaDir  = mb_strtolower(trim((string) ($g['direccion'] ?? ''))) === mb_strtolower(trim((string) ($d['direccion'] ?? '')));
                $mismoProd = mb_strtolower(trim((string) ($g['descripcion'] ?? ''))) === mb_strtolower(trim((string) ($d['descripcion'] ?? '')));

                if ($mismaDir && $mismoProd) {
                    Notification::make()
                        ->title('⚠️ Guía repetida')
                        ->body('Ya tienes esta misma guía en el lote (' . ($g['nombre'] ?? '') . ' · ' . $idNuevo . '). No se agregó.')
                        ->danger()->persistent()->send();
                    return;
                }

                Notification::make()
                    ->title('⚠️ Ese cliente ya está en el lote')
                    ->body(($g['nombre'] ?? '') . ' · ' . $idNuevo . '. Si es otro pedido distinto, confirma para agregarlo.')
                    ->warning()->persistent()
                    ->actions([
                        \Filament\Notifications\Actions\Action::make('forzar')
                            ->label('Sí, agregar de todos modos')
                            ->button()->close()->dispatch('agregar-forzado'),
                    ])
                    ->send();
                return;
            }
        }

        // Se guarda en la base: así no se pierde aunque se caiga el internet,
        // se reinicie la página o se cambie de aplicación.
        \App\Models\GuiaBorrador::create([
            'nombre'          => $d['nombre'],
            'telefono'        => $d['telefono'],
            'telefono_recibe' => $d['telefono_recibe'] ?? null,
            'direccion'       => $d['direccion'],
            'municipio'       => $d['municipio'],
            'departamento'    => $d['departamento'],
            'descripcion'     => $d['descripcion'],
            'cobrar'          => (float) ($d['cobrar'] ?? 0),
        ]);

        $this->recargarLista();
        $this->form->fill();

        Notification::make()->title('✅ Guardada (' . count($this->lista) . ' en la lista)')->success()->send();
    }

    /** Vuelve a leer la lista desde la base. */
    public function recargarLista(): void
    {
        $this->lista = \App\Models\GuiaBorrador::lista()
            ->map(fn ($g) => $g->aFila() + ['id' => $g->id])
            ->all();
    }

    public function quitar(int $id): void
    {
        \App\Models\GuiaBorrador::find($id)?->delete();
        $this->recargarLista();
    }

    public function vaciar(): void
    {
        try { \App\Models\GuiaBorrador::query()->delete(); } catch (\Throwable $e) {}
        $this->recargarLista();
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

        // En pantalla el último va arriba; en el Excel se exporta en el orden en que
        // se fueron agregando.
        SistrackExcel::generarDesdeLista(array_reverse($this->lista), $path);

        // La lista queda guardada por si hay que volver a bajarla; se limpia con "Vaciar".
        return response()->download($path, $nombre)->deleteFileAfterSend();
    }
}
