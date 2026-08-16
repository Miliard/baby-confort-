<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

/**
 * Los datos que solo vos sabés de cada día: lo que te cobró el proveedor
 * y otros gastos. Lo demás sale de la liquidación de Express.
 */
class CierreDia extends Model
{
    protected $table = 'cierres_dia';

    protected $fillable = ['fecha', 'proveedor', 'gastos', 'costo_bulto', 'nota'];

    protected $casts = [
        'fecha'       => 'date',
        'proveedor'   => 'decimal:2',
        'gastos'      => 'decimal:2',
        'costo_bulto' => 'decimal:2',
    ];

    public const COSTO_BULTO = 2.80;

    public static function hayTabla(): bool
    {
        try {
            return Schema::hasTable('cierres_dia');
        } catch (\Throwable $e) {
            return false;
        }
    }

    public static function paraFecha(string $fecha): self
    {
        return static::firstOrNew(
            ['fecha' => $fecha],
            ['proveedor' => 0, 'gastos' => 0, 'costo_bulto' => self::COSTO_BULTO]
        );
    }

    /**
     * Arma el resumen de un día: lo que entró, lo que salió y lo que quedó.
     * Los bultos de AIWIBI se apartan porque esa plata no es del negocio.
     */
    public static function resumen(string $fecha): array
    {
        $vacio = [
            'fecha' => $fecha, 'bultos' => 0, 'guias' => 0, 'cobrado' => 0.0,
            'comision' => 0.0, 'depositado' => 0.0, 'transferido' => 0.0,
            'costoBultos' => 0.0, 'proveedor' => 0.0, 'gastos' => 0.0,
            'resultado' => 0.0, 'pendientes' => 0,
            'aiwibiBultos' => 0, 'aiwibiDepositado' => 0.0,
            'costoBulto' => self::COSTO_BULTO,
        ];

        if (! ExpressEntrega::hayTabla()) return $vacio;

        $todas = ExpressEntrega::whereDate('fecha', $fecha)->get();
        if ($todas->isEmpty()) return $vacio;

        $mias   = $todas->where('aiwibi', false);
        $aiwibi = $todas->where('aiwibi', true);

        $cierre = static::hayTabla() ? static::paraFecha($fecha) : null;
        $costoBulto = (float) ($cierre->costo_bulto ?? self::COSTO_BULTO);

        $cobrado     = (float) $mias->sum('monto');
        $comision    = (float) $mias->sum('comision');
        $depositado  = (float) $mias->sum('total');
        $transferido = (float) $mias->sum('transferido');
        $costoBultos = round($mias->count() * $costoBulto, 2);
        $proveedor   = (float) ($cierre->proveedor ?? 0);
        $gastos      = (float) ($cierre->gastos ?? 0);

        return [
            'fecha'            => $fecha,
            'bultos'           => $mias->count(),
            'guias'            => $mias->pluck('orden')->unique()->count(),
            'cobrado'          => round($cobrado, 2),
            'comision'         => round($comision, 2),
            'depositado'       => round($depositado, 2),
            'transferido'      => round($transferido, 2),
            'costoBultos'      => $costoBultos,
            'costoBulto'       => $costoBulto,
            'proveedor'        => round($proveedor, 2),
            'gastos'           => round($gastos, 2),
            'resultado'        => round($depositado + $transferido - $costoBultos - $proveedor - $gastos, 2),
            'pendientes'       => $mias->filter(fn ($e) => $e->estaPendiente())->count(),
            'aiwibiBultos'     => $aiwibi->count(),
            'aiwibiDepositado' => round((float) $aiwibi->sum('total'), 2),
        ];
    }

    /**
     * Remuneración de AIWIBI en un rango de fechas, con los mismos datos que
     * se pegan en el Cierre del día. Esa plata no es del negocio: se cobra al
     * entregar y hay que devolverla, menos la comisión y el flete.
     */
    public static function remuneracion(
        ?string $desde,
        ?string $hasta,
        float $comisionPct = 2.5,
        float $porEnvio = 3.40,
    ): array {
        $vacio = [
            'filas' => collect(), 'envios' => 0, 'devueltos' => 0, 'sinCobro' => 0,
            'cobrado' => 0.0, 'comision' => 0.0, 'subtotal' => 0.0,
            'descuento' => 0.0, 'aPagar' => 0.0,
            'comisionPct' => $comisionPct, 'porEnvio' => $porEnvio,
        ];

        if (! ExpressEntrega::hayTabla()) return $vacio;

        try {
            $q = ExpressEntrega::where('aiwibi', true);
            if ($desde) $q->whereDate('fecha', '>=', $desde);
            if ($hasta) $q->whereDate('fecha', '<=', $hasta);
            $todas = $q->orderBy('fecha')->orderBy('nombre')->get();
        } catch (\Throwable $e) {
            return $vacio;
        }

        if ($todas->isEmpty()) return $vacio;

        // Lo devuelto no se entregó: no se cobra flete por eso.
        $devueltos = $todas->where('caso', 'devolucion');
        $filas     = $todas->where('caso', '!=', 'devolucion')->values();

        $cobrado   = (float) $filas->sum('monto');
        $envios    = $filas->count();
        $comision  = round($cobrado * ($comisionPct / 100), 2);
        $subtotal  = round($cobrado - $comision, 2);
        $descuento = round($envios * $porEnvio, 2);

        return [
            'filas'       => $filas,
            'envios'      => $envios,
            'devueltos'   => $devueltos->count(),
            'sinCobro'    => $filas->where('monto', 0)->count(),
            'cobrado'     => round($cobrado, 2),
            'comision'    => $comision,
            'subtotal'    => $subtotal,
            'descuento'   => $descuento,
            'aPagar'      => round($subtotal - $descuento, 2),
            'comisionPct' => $comisionPct,
            'porEnvio'    => $porEnvio,
        ];
    }

    /** Fechas que ya tienen entregas cargadas, de la más nueva a la más vieja. */
    public static function fechasCargadas(int $limite = 30): array
    {
        if (! ExpressEntrega::hayTabla()) return [];

        try {
            return ExpressEntrega::selectRaw('DATE(fecha) as f')
                ->groupBy('f')->orderByDesc('f')->limit($limite)
                ->pluck('f')->all();
        } catch (\Throwable $e) {
            return [];
        }
    }
}
