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

    /** Texto que se pega de Express */
    public string $pegado = '';

    /** Fecha que se está mirando */
    public string $fecha = '';

    /** Campos del día (se guardan al escribir) */
    public string $proveedor = '';
    public string $gastos = '';
    public string $nota = '';

    public function mount(): void
    {
        $fechas = CierreDia::fechasCargadas(1);
        $this->fecha = $fechas[0] ?? now()->toDateString();
        $this->cargarCampos();
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

        foreach ($filas as $f) {
            $huella = ExpressPegado::huella($f);

            if (ExpressEntrega::where('huella', $huella)->exists()) {
                $repetidas++;
                continue;
            }

            ExpressEntrega::create($f + ['huella' => $huella]);
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

    public function getEntregasProperty()
    {
        if (! ExpressEntrega::hayTabla()) return collect();

        return ExpressEntrega::whereDate('fecha', $this->fecha)
            ->orderByDesc('monto')->orderBy('nombre')->get();
    }
}
