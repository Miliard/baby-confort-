<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

/**
 * Los paquetes de guías que se le compran a Express (por ejemplo 500 por $1,400).
 * Express cobra POR BULTO, así que cada bulto entregado descuenta una.
 */
class BloqueGuia extends Model
{
    protected $table = 'bloques_guias';

    protected $fillable = ['fecha', 'cantidad', 'costo', 'usadas_antes', 'nota'];

    protected $casts = [
        'fecha'    => 'date',
        'costo'    => 'decimal:2',
        'cantidad' => 'integer',
        'usadas_antes' => 'integer',
    ];

    public static function hayTabla(): bool
    {
        try {
            return Schema::hasTable('bloques_guias');
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** Cuánto sale cada bulto según el último bloque comprado. */
    public static function costoBulto(): float
    {
        if (! static::hayTabla()) return CierreDia::COSTO_BULTO;

        $b = static::orderByDesc('fecha')->orderByDesc('id')->first();
        if (! $b || $b->cantidad < 1) return CierreDia::COSTO_BULTO;

        return round((float) $b->costo / (int) $b->cantidad, 2);
    }

    /**
     * Saldo de guías: todo lo comprado menos los bultos ya entregados.
     * Se cuentan TODOS los bultos, incluidos los de AIWIBI: esos también se pagan.
     */
    public static function saldo(): array
    {
        // Los bultos registrados en total, haya bloque o no: eso es lo que ya
        // se consumió y siempre se puede mostrar.
        $usadasTotal = 0;
        if (ExpressEntrega::hayTabla()) {
            try {
                $usadasTotal = ExpressEntrega::where('duplicado', false)->count();
            } catch (\Throwable $e) {
            }
        }

        $vacio = [
            'hay' => false, 'compradas' => 0, 'usadas' => 0, 'restantes' => 0,
            'porcentaje' => 0, 'costoBulto' => CierreDia::COSTO_BULTO, 'desde' => null,
            'usadasTotal' => $usadasTotal,
        ];

        if (! static::hayTabla()) return $vacio;

        $bloques = static::orderBy('fecha')->get();
        if ($bloques->isEmpty()) return $vacio;

        $compradas = (int) $bloques->sum('cantidad');
        $usadas    = (int) $bloques->sum('usadas_antes');
        $desde     = $bloques->first()->fecha;

        if (ExpressEntrega::hayTabla()) {
            try {
                // Los renglones que Express repitió por error (TYP) no son bultos
                // de verdad: no consumen guía ni se pagan.
                $usadas += ExpressEntrega::whereDate('fecha', '>=', $desde->toDateString())
                    ->where('duplicado', false)->count();
            } catch (\Throwable $e) {
            }
        }

        $restantes = max(0, $compradas - $usadas);

        return [
            'hay'         => true,
            'compradas'   => $compradas,
            'usadas'      => $usadas,
            'restantes'   => $restantes,
            'porcentaje'  => $compradas > 0 ? (int) round($restantes / $compradas * 100) : 0,
            'costoBulto'  => static::costoBulto(),
            'desde'       => $desde->toDateString(),
            'usadasTotal' => $usadasTotal,
        ];
    }
}
