<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;

/**
 * Lo que el asistente de ventas sabe de una conversación.
 *
 * estado:
 *   listo     · puede arrancar cuando el cliente escriba
 *   activo    · está atendiendo
 *   wil       · se lo pasó a Wil (y no vuelve solo)
 *   apagado   · Wil escribió en el chat o lo apagó a mano
 *   terminado · cerró un pedido
 */
class AsistenteFicha extends Model
{
    protected $table = 'asistente_fichas';

    protected $fillable = [
        'conversacion_id', 'estado', 'paso', 'datos', 'motivo', 'ultimo_mensaje_id', 'desde',
    ];

    protected $casts = [
        'datos' => 'array',
        'desde' => 'datetime',
    ];

    public function conversacion(): BelongsTo
    {
        return $this->belongsTo(WaConversacion::class, 'conversacion_id');
    }

    public static function hayTabla(): bool
    {
        static $hay = null;

        if ($hay !== null) return $hay;

        try {
            return $hay = Schema::hasTable('asistente_fichas');
        } catch (\Throwable $e) {
            return $hay = false;
        }
    }

    public static function de(WaConversacion $conv): ?self
    {
        if (! static::hayTabla()) return null;

        return static::where('conversacion_id', $conv->id)->first();
    }

    public function dato(string $clave, $defecto = null)
    {
        return data_get($this->datos ?? [], $clave, $defecto);
    }

    public function poner(string $clave, $valor): void
    {
        $d = $this->datos ?? [];
        data_set($d, $clave, $valor);
        $this->datos = $d;
    }

    public function quitar(string ...$claves): void
    {
        $d = $this->datos ?? [];
        foreach ($claves as $c) unset($d[$c]);
        $this->datos = $d;
    }

    public function activa(): bool
    {
        return $this->estado === 'activo';
    }

    /** Para el panel: "Paso 3 · opciones". */
    public function pasoLegible(): string
    {
        return [
            'talla'     => '1 · talla o peso',
            'unidad'    => '1 · libras o kilos',
            'elegir_talla' => '1 · elegir entre dos tallas',
            'tipo'      => '2 · cinta o calzoncito',
            'opciones'  => '3 · opciones y precio',
            'elegir'    => '3 · eligiendo cuál comprar',
            'cantidad'  => '3 · cuántos paquetes',
            'carrito'   => '4 · carrito',
            'municipio' => '5 · municipio',
            'confirmar_muni' => '5 · confirmar municipio',
            'depto'     => '5 · departamento',
            'colonia'   => '5 · colonia (San Miguel)',
            'total'     => '6 · total',
            'cambiar'   => '6 · qué cambiar',
            'nombre'    => '7 · nombre',
            'direccion' => '7 · dirección',
            'telefono'  => '7 · teléfono',
            'confirmar' => '8 · confirmación',
            'corregir'  => '8 · qué corregir',
        ][$this->paso] ?? ($this->paso ?: '—');
    }

    public function estadoLegible(): string
    {
        return match ($this->estado) {
            'activo'    => 'Atendiendo',
            'wil'       => 'Te lo pasó',
            'apagado'   => 'Apagado',
            'terminado' => 'Pedido cerrado',
            default     => 'Esperando',
        };
    }
}
