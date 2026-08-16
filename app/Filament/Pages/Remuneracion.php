<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use App\Services\RemuneracionSheet;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

/**
 * Cuánto hay que remunerar por los paquetes de AIWIBI.
 * Lee la hoja de entregas publicada en Google Sheets, así se actualiza sola.
 */
class Remuneracion extends Page
{
    /**
     * Pantalla vieja: leía de una hoja de Google aparte, así que nunca cuadraba
     * con lo que se pega en el Cierre del día. La remuneración ahora vive ahí,
     * con los mismos datos. Esta se deja oculta por si hiciera falta el archivo.
     */
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationIcon  = 'heroicon-o-banknotes';
    protected static ?string $navigationLabel = 'Remuneración (vieja)';
    protected static ?string $title           = 'Remuneración AIWIBI (versión vieja)';
    protected static ?int    $navigationSort  = 90;

    protected static string $view = 'filament.pages.remuneracion';

    public const CLAVE_URL      = 'sheet_remuneracion_url';
    public const CLAVE_COMISION = 'remun_comision';
    public const CLAVE_ENVIO    = 'remun_por_envio';

    public string $desde = '';
    public string $hasta = '';
    public string $url   = '';

    /** Se pueden cambiar aquí mismo y quedan guardados. */
    public float $comision = RemuneracionSheet::COMISION;
    public float $porEnvio = RemuneracionSheet::POR_ENVIO;

    public function mount(): void
    {
        $this->url      = (string) Setting::get(self::CLAVE_URL, '');
        $this->comision = (float) Setting::get(self::CLAVE_COMISION, RemuneracionSheet::COMISION);
        $this->porEnvio = (float) Setting::get(self::CLAVE_ENVIO, RemuneracionSheet::POR_ENVIO);
        $this->desde    = now()->startOfMonth()->toDateString();
        $this->hasta    = now()->toDateString();
    }

    /** Guarda los valores en cuanto se cambian, para no perderlos. */
    public function updatedComision($v): void
    {
        Setting::put(self::CLAVE_COMISION, (float) $v);
    }

    public function updatedPorEnvio($v): void
    {
        Setting::put(self::CLAVE_ENVIO, (float) $v);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('conectar')
                ->label('Conectar la hoja')
                ->icon('heroicon-o-link')
                ->color('gray')
                ->modalHeading('Conectar tu hoja de Google Sheets')
                ->modalDescription('En Google Sheets: Archivo → Compartir → Publicar en la Web → elegí la hoja → formato CSV → Publicar. Pegá aquí el enlace que te da.')
                ->form([
                    \Filament\Forms\Components\TextInput::make('url')
                        ->label('Enlace CSV publicado')
                        ->placeholder('https://docs.google.com/spreadsheets/d/e/.../pub?gid=0&single=true&output=csv')
                        ->default(fn () => Setting::get(self::CLAVE_URL, ''))
                        ->url()
                        ->required(),
                ])
                ->action(function (array $data) {
                    Setting::put(self::CLAVE_URL, trim($data['url']));
                    $this->url = trim($data['url']);
                    RemuneracionSheet::filas($this->url, true);
                    Notification::make()->title('Hoja conectada')->success()->send();
                }),

            Action::make('subir')
                ->label('Subir archivo')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('gray')
                ->modalHeading('Subir tu hoja de entregas')
                ->modalDescription('Sirve el Excel tal cual (.xlsx), el .ods o un CSV. Si subís un CSV desde Excel, elegí "CSV UTF-8" para que no se dañen las tildes.')
                ->form([
                    \Filament\Forms\Components\FileUpload::make('archivo')
                        ->label('Archivo (Excel, ODS o CSV)')
                        ->acceptedFileTypes([
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            'application/vnd.ms-excel',
                            'application/vnd.oasis.opendocument.spreadsheet',
                            'text/csv', 'text/plain', 'application/csv',
                        ])
                        ->storeFiles(false)
                        ->maxSize(20480)
                        ->required(),
                ])
                ->action(function (array $data) {
                    $subido = $data['archivo'];
                    try {
                        $ext = strtolower($subido->getClientOriginalExtension() ?: 'csv');
                        RemuneracionSheet::guardarArchivo($subido->get(), $ext);

                        $filas  = RemuneracionSheet::filasDeArchivo();
                        $n      = count($filas);
                        $aiwibi = count(array_filter($filas, fn ($f) => $f['aiwibi']));

                        if ($n > 0) {
                            Notification::make()
                                ->title($n . ' entregas leídas')
                                ->body($aiwibi . ' son de AIWIBI.')
                                ->success()->send();
                        } else {
                            Notification::make()
                                ->title('No se encontraron entregas')
                                ->body('El archivo debe tener una fila de encabezados con "NOMBRE DE CLIENTE" y una columna "MONTO". Si tu Excel es .xlsb, guardalo como .xlsx primero.')
                                ->warning()->persistent()->send();
                        }
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title('No se pudo leer el archivo')
                            ->body($e->getMessage())
                            ->danger()->persistent()->send();
                    }
                }),

            Action::make('actualizar')
                ->label('Actualizar ahora')
                ->icon('heroicon-o-arrow-path')
                ->action(function () {
                    if ($this->url === '') {
                        Notification::make()->title('Primero conectá la hoja')->warning()->send();
                        return;
                    }
                    $filas = RemuneracionSheet::filas($this->url, true);
                    Notification::make()
                        ->title(count($filas) . ' entregas leídas')
                        ->success()->send();
                }),
        ];
    }

    /** Atajos de periodo. */
    public function periodo(string $cual): void
    {
        $hoy = now();
        [$this->desde, $this->hasta] = match ($cual) {
            'mes'     => [$hoy->copy()->startOfMonth()->toDateString(), $hoy->toDateString()],
            'anterior'=> [$hoy->copy()->subMonthNoOverflow()->startOfMonth()->toDateString(),
                          $hoy->copy()->subMonthNoOverflow()->endOfMonth()->toDateString()],
            'semana'  => [$hoy->copy()->startOfWeek()->toDateString(), $hoy->toDateString()],
            default   => ['', ''],
        };
    }

    /** ¿Hay alguna fuente de datos configurada? */
    public function getHayDatosProperty(): bool
    {
        return $this->url !== '' || RemuneracionSheet::rutaArchivo() !== null;
    }

    /** De dónde salen los números y desde cuándo. */
    public function getLeidoProperty(): ?string
    {
        if ($this->url !== '') {
            $at = RemuneracionSheet::leidoEn($this->url);
            return $at ? 'Leído de tu hoja de Google ' . $at->diffForHumans() . ' · se actualiza solo' : null;
        }

        $at = RemuneracionSheet::archivoSubidoEn();
        return $at ? 'Del archivo que subiste ' . $at->diffForHumans() . ' · hay que volver a subirlo para verlo al día' : null;
    }

    /**
     * El corte completo en texto, listo para pegarle a la persona a la que
     * hay que pagarle: cada entrega con su fecha, guía, cliente y monto,
     * y al final el cálculo.
     */
    public function getDetalleTextoProperty(): string
    {
        $r = $this->resumen;

        $periodo = $this->desde || $this->hasta
            ? 'Del ' . ($this->desde ? \Illuminate\Support\Carbon::parse($this->desde)->format('d/m/Y') : 'inicio')
              . ' al ' . ($this->hasta ? \Illuminate\Support\Carbon::parse($this->hasta)->format('d/m/Y') : 'hoy')
            : 'Todo el historial';

        $t  = "\u{1F4E6} *REMUNERACI\u{D3}N AIWIBI*\n";
        $t .= $periodo . "\n";
        $t .= str_repeat('-', 28) . "\n\n";

        foreach ($r['filas'] as $f) {
            $fecha = $f['fecha'] ? \Illuminate\Support\Carbon::parse($f['fecha'])->format('d/m') : '--/--';
            $t .= $fecha . '  ' . trim($f['nombre']);
            if (! empty($f['orden']))  $t .= "\n     Gu\u{ED}a " . $f['orden'];
            if (! empty($f['zona']))   $t .= ' \u{B7} ' . $f['zona'];
            $t .= "\n     $" . number_format($f['monto'], 2) . "\n\n";
        }

        $pct = rtrim(rtrim(number_format($r['comisionPct'], 2), '0'), '.');

        $t .= str_repeat('-', 28) . "\n";
        $t .= "Env\u{ED}os: {$r['envios']}";
        if ($r['sinCobro'] > 0) $t .= " ({$r['sinCobro']} sin cobro)";
        $t .= "\n";
        $t .= 'Cobrado: $' . number_format($r['efectivo'], 2) . "\n";
        $t .= "Comisi\u{F3}n {$pct}%: -$" . number_format($r['comision'], 2) . "\n";
        $t .= 'Subtotal: $' . number_format($r['subtotal'], 2) . "\n";
        $t .= "Env\u{ED}os {$r['envios']} x $" . number_format($r['porEnvio'], 2)
            . ': -$' . number_format($r['descuento'], 2) . "\n";
        $t .= str_repeat('-', 28) . "\n";
        $t .= '*TOTAL A PAGAR: $' . number_format($r['aPagar'], 2) . '*';

        return $t;
    }

    public function getResumenProperty(): array
    {
        // La hoja conectada manda; si no hay, se usa el archivo subido a mano.
        $filas = $this->url !== ''
            ? RemuneracionSheet::filas($this->url)
            : RemuneracionSheet::filasDeArchivo();

        return RemuneracionSheet::calcular(
            $filas,
            $this->desde !== '' ? $this->desde : null,
            $this->hasta !== '' ? $this->hasta : null,
            $this->comision,
            $this->porEnvio,
        );
    }
}
