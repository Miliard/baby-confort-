<?php

namespace App\Filament\Pages;

use App\Models\GuiaEstado;
use App\Models\GuiaFoto;
use App\Models\Setting;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;

/**
 * Seguimiento de paquetes con el sistema nuevo de Express (Facilito / T&T).
 *
 * Consulta la API oficial de Express y muestra en qué va cada guía.
 */
class SeguimientoExpress extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-truck';
    protected static ?string $navigationLabel = 'Seguimiento Express';

    /** Grupo del menú solo en el panel admin (en Mensajes la barra va sin grupos). */
    public static function getNavigationGroup(): ?string
    {
        return \Filament\Facades\Filament::getCurrentPanel()?->getId() === 'admin' ? 'Envíos' : null;
    }

    protected static ?string $title = '📦 Seguimiento de paquetes';
    protected static ?int $navigationSort = 3;

    protected static string $view = 'filament.pages.seguimiento-express';

    /** Botón "🔄 Actualizar ahora": consulta la API oficial de Express. */
    public function actualizarAhora(): void
    {
        $r = \App\Services\ExpressApi::sincronizar(10);

        if ($r['ok']) {
            \Filament\Notifications\Notification::make()
                ->title('✅ ' . $r['revisadas'] . ' guías actualizadas desde Express')
                ->body('Entregadas: ' . $r['entregadas'] . ' · En ruta: ' . $r['en_ruta'] . ' · En bodega: ' . $r['bodega'])
                ->success()->send();
        } else {
            \Filament\Notifications\Notification::make()
                ->title('No se pudo actualizar')
                ->body($r['error'] ?? 'Error desconocido')
                ->danger()->send();
        }
    }

    /**
     * Tarjetas iguales a las del Panel de Control de Express: cada estado en
     * su tarjeta (no juntados) y "Entregadas" solo las de HOY.
     */
    public const TARJETAS = [
        'CREATED'     => ['📝', 'Creadas'],
        'IN_WAREHOUSE'=> ['🏬', 'En bodega'],
        'ASSIGNED'    => ['👤', 'Asignadas'],
        'PICKED_UP'   => ['📦', 'Recolectadas'],
        'IN_TRANSIT'  => ['🚚', 'En ruta'],
        'INCIDENT'    => ['⚠️', 'Incidencias'],
        'HOY'         => ['✅', 'Entregadas hoy'],
    ];

    /** Estado de Express → tarjeta. */
    protected static function tarjetaDe(?string $estado): ?string
    {
        $e = strtoupper((string) $estado);
        return match (true) {
            in_array($e, ['CREATED', 'PENDING', 'READY_FOR_PICKUP', 'PENDING_PICKUP']) => 'CREATED',
            in_array($e, ['IN_WAREHOUSE', 'RECEIVED'])                              => 'IN_WAREHOUSE',
            $e === 'ASSIGNED'                                                       => 'ASSIGNED',
            in_array($e, ['PICKED_UP', 'COLLECTED'])                                => 'PICKED_UP',
            in_array($e, ['IN_TRANSIT', 'OUT_FOR_DELIVERY', 'ON_ROUTE'])            => 'IN_TRANSIT',
            in_array($e, ['INCIDENT', 'FAILED'])                                    => 'INCIDENT',
            default                                                                 => null,
        };
    }

    public function getViewData(): array
    {
        $filas = collect();
        $resumen = array_fill_keys(array_keys(self::TARJETAS), 0);
        $entregadas30 = 0;
        $tz = 'America/El_Salvador';
        $hoy = now($tz)->toDateString();

        if (GuiaEstado::hayTabla()) {
            try {
                // Las borradas/anuladas en Express no se cuentan ni se listan.
                $estados = GuiaEstado::where('updated_at', '>=', now()->subDays(30))
                    ->whereNotNull('estado')
                    ->whereNotIn('estado', ['CANCELLED', 'CANCELED', 'DELETED'])
                    ->orderByDesc('cambio_at')->limit(300)->get();

                $nombres = GuiaFoto::whereIn('guia', $estados->pluck('guia')->all())
                    ->orderByDesc('id')->get(['guia', 'nombre', 'telefono'])->unique('guia')->keyBy('guia');

                foreach ($estados as $e) {
                    if ((int) $e->etapa >= 4) {
                        $entregadas30++;
                        if ($e->entregado_at && $e->entregado_at->copy()->setTimezone($tz)->toDateString() === $hoy) {
                            $resumen['HOY']++;
                        }
                    } elseif ($t = self::tarjetaDe($e->estado)) {
                        $resumen[$t]++;
                    }

                    $g = $nombres->get($e->guia);
                    $filas->push([
                        'guia'       => $e->guia,
                        'cliente'    => $g ? trim(($g->telefono ? $g->telefono . ' ' : '') . $g->nombre) : '',
                        'estado'     => GuiaEstado::traducir($e->estado)[1],
                        'etapa'      => (int) $e->etapa,
                        'repartidor' => $e->repartidor,
                        'cuando'     => $e->cambio_at?->copy()->setTimezone($tz)->format('d/m g:i A'),
                    ]);
                }

                // Primero lo que está en camino; las entregadas, al final.
                $filas = $filas->sortBy(fn ($f) => $f['etapa'] >= 4 ? 1 : 0)->values();
            } catch (\Throwable $e) {
                // sin datos todavía
            }
        }

        $ultimaApi = Setting::get('express_api_ultima');

        return [
            'ultimaApi'    => $ultimaApi ? Carbon::parse($ultimaApi)->setTimezone($tz)->format('d/m/Y g:i A') : null,
            'apiLista'     => \App\Services\ExpressApi::configurada(),
            'filas'        => $filas,
            'resumen'      => $resumen,
            'tarjetas'     => self::TARJETAS,
            'activas'      => array_sum($resumen) - $resumen['HOY'],
            'entregadas30' => $entregadas30,
            'hayTabla'     => GuiaEstado::hayTabla(),
        ];
    }
}
