<?php

namespace App\Filament\Pages;

use App\Models\CierreDia;
use App\Models\ExpressEntrega;
use App\Services\ExpressPegado;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

/**
 * Pegás la liquidación de Express, contestás por los bultos en $0,
 * metés lo que te cobró el proveedor y ves si el día te dejó o no.
 */
class CierreDelDia extends Page
{
    protected static ?string $navigationIcon  = 'heroicon-o-calculator';
    protected static ?string $navigationLabel = 'Cierre del día';
    protected static ?string $title           = 'Cierre del día';
    protected static ?int    $navigationSort  = 3;

    protected static string $view = 'filament.pages.cierre-del-dia';

    /** Ancho completo: si no, las dos columnas no caben y todo se apila. */
    protected ?string $maxContentWidth = 'full';

    /** Texto que se pega de Express */
    public string $pegado = '';

    /** Qué día entró la plata de ese pegado (el depósito cubre varias entregas) */
    public string $fechaDeposito = '';

    /** Fecha que se está mirando */
    public string $fecha = '';

    /** Campos del día (se guardan al escribir) */
    public string $proveedor = '';
    public string $gastos = '';
    public string $nota = '';

    /** Alta de un bloque de guías comprado a Express */
    public string $bloqueCantidad = '';
    public string $bloqueCosto = '';

    /** Remuneración AIWIBI: rango y condiciones */
    public string $remDesde = '';
    public string $remHasta = '';
    public float  $remComision = 2.5;
    public float  $remPorEnvio = 3.40;

    public function mount(): void
    {
        $fechas = CierreDia::fechasCargadas(1);
        $this->fecha = $fechas[0] ?? now()->toDateString();
        $this->cargarCampos();

        $this->remDesde    = now()->startOfMonth()->toDateString();
        $this->remHasta    = now()->toDateString();
        $this->remComision = (float) \App\Models\Setting::get('remun_comision', 2.5);
        $this->remPorEnvio = (float) \App\Models\Setting::get('remun_por_envio', 3.40);
    }

    public function updatedRemComision($v): void
    {
        \App\Models\Setting::put('remun_comision', (float) $v);
    }

    public function updatedRemPorEnvio($v): void
    {
        \App\Models\Setting::put('remun_por_envio', (float) $v);
    }

    /** Atajos de período para la remuneración. */
    public function remPeriodo(string $cual): void
    {
        $hoy = now();
        [$this->remDesde, $this->remHasta] = match ($cual) {
            'dia'      => [$this->fecha, $this->fecha],
            'semana'   => [$hoy->copy()->startOfWeek()->toDateString(), $hoy->toDateString()],
            'mes'      => [$hoy->copy()->startOfMonth()->toDateString(), $hoy->toDateString()],
            'anterior' => [$hoy->copy()->subMonthNoOverflow()->startOfMonth()->toDateString(),
                           $hoy->copy()->subMonthNoOverflow()->endOfMonth()->toDateString()],
            default    => ['', ''],
        };
    }

    /** Qué producto sale más, por cantidad de paquetes. Usa el mismo rango. */
    public function getVendidosProperty(): array
    {
        return \App\Services\ProductosVendidos::ranking(
            $this->remDesde ?: null,
            $this->remHasta ?: null,
        );
    }

    public function getRemuneracionProperty(): array
    {
        return CierreDia::remuneracion(
            $this->remDesde ?: null,
            $this->remHasta ?: null,
            $this->remComision,
            $this->remPorEnvio,
        );
    }

    /** El corte en texto, listo para pegárselo a quien hay que pagarle. */
    public function getRemTextoProperty(): string
    {
        $m = $this->remuneracion;

        $periodo = ($this->remDesde || $this->remHasta)
            ? 'Del ' . ($this->remDesde ? \Illuminate\Support\Carbon::parse($this->remDesde)->format('d/m/Y') : 'inicio')
              . ' al ' . ($this->remHasta ? \Illuminate\Support\Carbon::parse($this->remHasta)->format('d/m/Y') : 'hoy')
            : 'Todo el historial';

        $t  = "\u{1F4E6} *REMUNERACI\u{D3}N AIWIBI*\n" . $periodo . "\n";
        $t .= str_repeat('-', 28) . "\n\n";

        foreach ($m['filas'] as $f) {
            $t .= $f->fecha->format('d/m') . '  ' . trim($f->nombre) . "\n";
            $t .= '     Gu' . "\u{ED}" . 'a ' . $f->orden;
            if ($f->zona) $t .= ' ' . "\u{B7}" . ' ' . $f->zona;
            $t .= "\n     $" . number_format($f->monto, 2) . "\n\n";
        }

        $pct = rtrim(rtrim(number_format($m['comisionPct'], 2), '0'), '.');

        $t .= str_repeat('-', 28) . "\n";
        $t .= "Env\u{ED}os: {$m['envios']}";
        if ($m['sinCobro'] > 0)  $t .= " ({$m['sinCobro']} sin cobro)";
        if ($m['devueltos'] > 0) $t .= " \u{B7} {$m['devueltos']} devueltos no cuentan";
        $t .= "\n";
        $t .= 'Cobrado: $' . number_format($m['cobrado'], 2) . "\n";
        $t .= "Comisi\u{F3}n {$pct}%: -$" . number_format($m['comision'], 2) . "\n";
        $t .= 'Subtotal: $' . number_format($m['subtotal'], 2) . "\n";
        $t .= "Env\u{ED}os {$m['envios']} x $" . number_format($m['porEnvio'], 2)
            . ': -$' . number_format($m['descuento'], 2) . "\n";
        $t .= str_repeat('-', 28) . "\n";
        $t .= '*TOTAL A PAGAR: $' . number_format($m['aPagar'], 2) . '*';

        return $t;
    }

    private function cargarCampos(): void
    {
        if (! CierreDia::hayTabla()) return;

        $c = CierreDia::paraFecha($this->fecha);
        $this->proveedor = $c->proveedor > 0 ? (string) (float) $c->proveedor : '';
        $this->gastos    = $c->gastos > 0 ? (string) (float) $c->gastos : '';
        $this->nota      = (string) ($c->nota ?? '');
    }

    // ---------------- Pegar la liquidación ----------------

    public function procesar(): void
    {
        $texto = trim($this->pegado);
        if ($texto === '') {
            Notification::make()->title('Pegá primero el bloque de Express')->warning()->send();
            return;
        }

        if (! ExpressEntrega::hayTabla()) {
            Notification::make()->title('Falta correr las migraciones')->danger()->send();
            return;
        }

        $filas = ExpressPegado::leer($texto);

        if (! $filas) {
            Notification::make()
                ->title('No encontré renglones')
                ->body('Copiá las celdas desde Excel, con la fecha tipo "13-ago" incluida.')
                ->warning()->persistent()->send();
            return;
        }

        $nuevas = 0; $repetidas = 0;
        $deposito = trim($this->fechaDeposito) ?: null;

        foreach ($filas as $f) {
            $huella = ExpressPegado::huella($f);

            $existente = ExpressEntrega::where('huella', $huella)->first();
            if ($existente) {
                // Si antes no se sabía cuándo entró la plata, ahora se anota.
                if ($deposito && ! $existente->fecha_deposito) {
                    $existente->fecha_deposito = $deposito;
                    $existente->save();
                }
                $repetidas++;
                continue;
            }

            ExpressEntrega::create($f + ['huella' => $huella, 'fecha_deposito' => $deposito]);
            $nuevas++;
        }

        $fechas = collect($filas)->pluck('fecha')->unique()->sort()->values();
        $this->fecha = $fechas->last();
        $this->cargarCampos();
        $this->pegado = '';

        Notification::make()
            ->title($nuevas . ' bultos cargados')
            ->body(trim(
                $fechas->count() . ' ' . ($fechas->count() === 1 ? 'fecha' : 'fechas') . ': ' . $fechas->implode(', ')
                . ($repetidas > 0 ? ' · ' . $repetidas . ' ya estaban cargados' : '')
            ))
            ->success()->send();
    }

    // ---------------- Datos del día ----------------

    public function guardarDia(): void
    {
        if (! CierreDia::hayTabla()) return;

        $c = CierreDia::paraFecha($this->fecha);
        $c->proveedor = (float) str_replace(',', '.', $this->proveedor ?: '0');
        $c->gastos    = (float) str_replace(',', '.', $this->gastos ?: '0');
        $c->nota      = $this->nota ?: null;
        $c->save();

        Notification::make()->title('Guardado')->success()->send();
    }

    /** Registra un paquete de guías comprado (ej: 500 por $1,400). */
    public function agregarBloque(): void
    {
        $cant  = (int) $this->bloqueCantidad;
        $costo = (float) str_replace(',', '.', $this->bloqueCosto ?: '0');

        if ($cant < 1 || $costo <= 0) {
            Notification::make()->title('Poné la cantidad y el costo')->warning()->send();
            return;
        }

        if (! \App\Models\BloqueGuia::hayTabla()) {
            Notification::make()->title('Falta correr las migraciones')->danger()->send();
            return;
        }

        \App\Models\BloqueGuia::create([
            'fecha'    => now()->toDateString(),
            'cantidad' => $cant,
            'costo'    => $costo,
        ]);

        $this->bloqueCantidad = '';
        $this->bloqueCosto = '';

        Notification::make()
            ->title($cant . ' guías cargadas')
            ->body('Cada bulto sale a $' . number_format($costo / $cant, 2))
            ->success()->send();
    }

    public function getSaldoGuiasProperty(): array
    {
        return \App\Models\BloqueGuia::saldo();
    }

    /** Los bloques cargados, para poder revisarlos y borrar el que esté mal. */
    public function getBloquesProperty()
    {
        if (! \App\Models\BloqueGuia::hayTabla()) return collect();

        return \App\Models\BloqueGuia::orderByDesc('fecha')->orderByDesc('id')->get();
    }

    public function borrarBloque(int $id): void
    {
        \App\Models\BloqueGuia::find($id)?->delete();
        Notification::make()->title('Bloque borrado')->success()->send();
    }

    /** Cambia (o quita) el día del depósito de todo el día que se está viendo. */
    public function fijarDeposito(): void
    {
        $nueva = trim($this->fechaDeposito) ?: null;

        ExpressEntrega::whereDate('fecha', $this->fecha)->update(['fecha_deposito' => $nueva]);

        Notification::make()
            ->title($nueva ? 'Depósito puesto al ' . \Illuminate\Support\Carbon::parse($nueva)->format('d/m/Y') : 'Fecha de depósito quitada')
            ->success()->send();
    }

    public function verFecha(string $fecha): void
    {
        $this->fecha = $fecha;
        $this->cargarCampos();
    }

    /** Explica qué pasó con un bulto que vino en $0. */
    public function marcarCaso(int $id, string $caso, $monto = null): void
    {
        $e = ExpressEntrega::find($id);
        if (! $e) return;

        $e->caso = $caso;
        $e->transferido = $caso === 'transferencia'
            ? (float) str_replace(',', '.', (string) ($monto ?: 0))
            : null;
        $e->save();
    }

    /** Deshace la respuesta de un bulto en $0 para volver a contestarla. */
    public function desmarcarCaso(int $id): void
    {
        $e = ExpressEntrega::find($id);
        if (! $e) return;

        $e->caso = null;
        $e->transferido = null;
        $e->save();

        Notification::make()->title('Listo, volvé a contestarlo')->success()->send();
    }

    /** Borra un renglón suelto (si se pegó uno que no iba). */
    public function borrarEntrega(int $id): void
    {
        ExpressEntrega::find($id)?->delete();
        Notification::make()->title('Bulto borrado')->success()->send();
    }

    public function borrarFecha(): void
    {
        ExpressEntrega::whereDate('fecha', $this->fecha)->delete();
        Notification::make()->title('Entregas de ese día borradas')->success()->send();
    }

    // ---------------- Datos para la vista ----------------

    public function getResumenProperty(): array
    {
        return CierreDia::resumen($this->fecha);
    }

    public function getFechasProperty(): array
    {
        return CierreDia::fechasCargadas(14);
    }

    public function getPendientesProperty()
    {
        if (! ExpressEntrega::hayTabla()) return collect();

        return ExpressEntrega::whereDate('fecha', $this->fecha)
            ->where('aiwibi', false)->where('monto', 0)->whereNull('caso')
            ->orderBy('id')->get();
    }

    /**
     * Los depósitos: qué día entró plata, cuánto, y qué fechas de entrega cubrió.
     * Sirve para cuadrar la caja, que es otra pregunta distinta a "¿gané hoy?".
     */
    public function getDepositosProperty(): array
    {
        if (! ExpressEntrega::hayTabla()) return [];

        $filas = ExpressEntrega::whereNotNull('fecha_deposito')
            ->where('aiwibi', false)
            ->orderByDesc('fecha_deposito')
            ->get()
            ->groupBy(fn ($e) => $e->fecha_deposito->toDateString());

        $out = [];
        foreach ($filas as $fecha => $grupo) {
            $out[] = [
                'fecha'   => $fecha,
                'monto'   => round((float) $grupo->sum('total'), 2),
                'bultos'  => $grupo->count(),
                'entregas'=> $grupo->pluck('fecha')->map(fn ($f) => $f->format('d/m'))->unique()->sort()->values()->all(),
            ];
        }

        return array_slice($out, 0, 12);
    }

    public function getEntregasProperty()
    {
        if (! ExpressEntrega::hayTabla()) return collect();

        return ExpressEntrega::whereDate('fecha', $this->fecha)
            ->orderByDesc('monto')->orderBy('nombre')->get();
    }
}
