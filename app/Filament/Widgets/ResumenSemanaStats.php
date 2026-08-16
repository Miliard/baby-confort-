<?php

namespace App\Filament\Widgets;

use App\Models\CierreDia;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Los números grandes de los últimos días: lo que entró, lo que quedó,
 * cuántos bultos salieron y qué días están incompletos.
 */
class ResumenSemanaStats extends StatsOverviewWidget
{
    /** Ya no se usa: se quitó de la pantalla. No debe aparecer sola en el panel. */
    protected static bool $isDiscovered = false;

    protected static ?string $pollingInterval = null;
    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $fechas = CierreDia::fechasCargadas(7);

        $entro = 0.0; $resultado = 0.0; $bultos = 0; $sinProveedor = 0;

        foreach ($fechas as $f) {
            $r = CierreDia::resumen($f);
            $entro     += $r['depositado'] + $r['transferido'];
            $resultado += $r['resultado'];
            $bultos    += $r['bultos'];
            if ($r['proveedor'] == 0 && $r['bultos'] > 0) $sinProveedor++;
        }

        $dias = count($fechas);

        return [
            Stat::make('Entró · últimos días', '$' . number_format($entro, 2))
                ->description($dias . ' ' . ($dias === 1 ? 'día cargado' : 'días cargados'))
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('info'),

            Stat::make('Resultado · últimos días', '$' . number_format($resultado, 2))
                ->description($sinProveedor > 0
                    ? $sinProveedor . ' ' . ($sinProveedor === 1 ? 'día sin cargar el proveedor' : 'días sin cargar el proveedor')
                    : 'Todos los días completos')
                ->descriptionIcon($sinProveedor > 0 ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-check-circle')
                ->color($sinProveedor > 0 ? 'warning' : ($resultado >= 0 ? 'success' : 'danger')),

            Stat::make('Bultos · últimos días', (string) $bultos)
                ->description('Costo: $' . number_format($bultos * CierreDia::COSTO_BULTO, 2))
                ->descriptionIcon('heroicon-m-cube')
                ->color('gray'),
        ];
    }
}
