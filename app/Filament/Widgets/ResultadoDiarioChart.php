<?php

namespace App\Filament\Widgets;

use App\Models\CierreDia;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

/**
 * Barras del resultado de cada día: verde si dejó, rojo si no.
 * Se lee de un vistazo cómo viene la semana.
 */
class ResultadoDiarioChart extends ChartWidget
{
    protected static ?string $heading = 'Resultado por día';
    protected static ?string $maxHeight = '260px';
    protected int|string|array $columnSpan = 'full';

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $fechas = array_reverse(CierreDia::fechasCargadas(14));

        $etiquetas = [];
        $valores   = [];
        $colores   = [];

        foreach ($fechas as $f) {
            $r = CierreDia::resumen($f);
            $etiquetas[] = Carbon::parse($f)->format('d/m');
            $valores[]   = $r['resultado'];
            $colores[]   = $r['resultado'] >= 0
                ? 'rgba(16, 185, 129, 0.85)'
                : 'rgba(239, 68, 68, 0.85)';
        }

        return [
            'datasets' => [[
                'label'           => 'Resultado del día ($)',
                'data'            => $valores,
                'backgroundColor' => $colores,
                'borderRadius'    => 6,
            ]],
            'labels' => $etiquetas,
        ];
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => ['legend' => ['display' => false]],
            'scales'  => ['y' => ['beginAtZero' => true]],
        ];
    }
}
