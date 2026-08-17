<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

/**
 * Un renglón de la liquidación de Express: un bulto entregado.
 * Varios renglones pueden compartir el mismo número de orden (carga grande
 * que se mandó en varios bultos); cada uno se cobra aparte.
 */
class ExpressEntrega extends Model
{
    protected $table = 'express_entregas';

    protected $fillable = [
        'fecha', 'fecha_deposito', 'orden', 'nombre', 'zona', 'monto', 'comision', 'total',
        'nota', 'duplicado', 'aiwibi', 'caso', 'transferido', 'huella',
    ];

    protected $casts = [
        'fecha'          => 'date',
        'fecha_deposito' => 'date',
        'monto'       => 'decimal:2',
        'comision'    => 'decimal:2',
        'total'       => 'decimal:2',
        'transferido' => 'decimal:2',
        'aiwibi'      => 'boolean',
        'duplicado'   => 'boolean',
    ];

    public const CASOS = [
        'transferencia' => 'Me transfirieron',
        'bulto_extra'   => 'Bulto extra de otra guía',
        'devolucion'    => 'Devuelto / no entregado',
    ];

    public static function hayTabla(): bool
    {
        try {
            return Schema::hasTable('express_entregas');
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** Bultos en $0 que no son AIWIBI y todavía no se han explicado. */
    public function estaPendiente(): bool
    {
        return ! $this->aiwibi
            && (float) $this->monto == 0.0
            && empty($this->caso);
    }
}
