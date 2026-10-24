<?php

namespace App\Filament\Pages;

use App\Http\Controllers\ExpressPuenteController;
use App\Models\GuiaEstado;
use App\Models\GuiaFoto;
use App\Models\Setting;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;

/**
 * Seguimiento de paquetes con el sistema nuevo de Express (Facilito / T&T).
 *
 * Aquí está el botón "📦 Actualizar guías" para arrastrar a la barra de
 * marcadores, y el resumen de en qué va cada guía.
 */
class SeguimientoExpress extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-truck';
    protected static ?string $navigationLabel = 'Seguimiento Express';
    protected static ?string $title = '📦 Seguimiento de paquetes';
    protected static ?int $navigationSort = 3;

    protected static string $view = 'filament.pages.seguimiento-express';

    public function getViewData(): array
    {
        $ultima = Setting::get('express_puente_ultima');
        $filas = collect();
        $resumen = [1 => 0, 2 => 0, 3 => 0, 4 => 0];

        if (GuiaEstado::hayTabla()) {
            try {
                $estados = GuiaEstado::where('updated_at', '>=', now()->subDays(30))
                    ->whereNotNull('estado')
                    ->orderByDesc('cambio_at')->limit(200)->get();

                $nombres = GuiaFoto::whereIn('guia', $estados->pluck('guia')->all())
                    ->orderByDesc('id')->get(['guia', 'nombre', 'telefono'])->unique('guia')->keyBy('guia');

                foreach ($estados as $e) {
                    $resumen[max(1, min(4, (int) $e->etapa))]++;
                    $g = $nombres->get($e->guia);
                    $filas->push([
                        'guia'       => $e->guia,
                        'cliente'    => $g ? trim(($g->telefono ? $g->telefono . ' ' : '') . $g->nombre) : '',
                        'estado'     => GuiaEstado::traducir($e->estado)[1],
                        'etapa'      => (int) $e->etapa,
                        'repartidor' => $e->repartidor,
                        'cuando'     => $e->cambio_at?->setTimezone('America/El_Salvador')->format('d/m g:i A'),
                    ]);
                }
            } catch (\Throwable $e) {
                // sin datos todavía
            }
        }

        return [
            'clave'   => ExpressPuenteController::clave(),
            'base'    => request()->getSchemeAndHttpHost(),
            'ultima'  => $ultima ? Carbon::parse($ultima)->setTimezone('America/El_Salvador')->format('d/m/Y g:i A') : null,
            'filas'   => $filas,
            'resumen' => $resumen,
            'hayTabla'=> GuiaEstado::hayTabla(),
        ];
    }
}
