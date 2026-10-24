<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

/**
 * Estado de una guía en el sistema nuevo de Express (Facilito / T&T).
 *
 * Express ya no tiene una página pública de rastreo, así que el estado llega
 * desde el botón "📦 Actualizar guías" que Wil toca con Express abierto.
 */
class GuiaEstado extends Model
{
    protected $table = 'guia_estados';

    protected $fillable = [
        'guia', 'estado', 'etapa', 'repartidor', 'pago',
        'entregado_at', 'cambio_at', 'historial', 'faltas',
    ];

    protected $casts = [
        'entregado_at' => 'datetime',
        'cambio_at'    => 'datetime',
        'historial'    => 'array',
    ];

    /**
     * Código de Express => [etapa de la barra (1-4) o null = no mover, texto para el cliente].
     * Etapas: 1 confirmado · 2 recolectado · 3 en camino · 4 entregado.
     */
    public const ESTADOS = [
        'CREATED'          => [1, 'CREADO'],
        'PENDING'          => [1, 'CREADO'],
        'READY_FOR_PICKUP' => [1, 'LISTO PARA RECOLECTAR'],
        'PENDING_PICKUP'   => [1, 'POR RECOLECTAR'],
        'ASSIGNED'         => [2, 'ASIGNADO'],
        'PICKED_UP'        => [2, 'RECOLECTADO'],
        'COLLECTED'        => [2, 'RECOLECTADO'],
        'IN_WAREHOUSE'     => [2, 'EN BODEGA'],
        'RECEIVED'         => [2, 'EN BODEGA'],
        'IN_TRANSIT'       => [3, 'EN RUTA'],
        'OUT_FOR_DELIVERY' => [3, 'EN RUTA'],
        'ON_ROUTE'         => [3, 'EN RUTA'],
        'DELIVERED'        => [4, 'ENTREGADO'],
        'INCIDENT'         => [null, 'INCIDENCIA'],
        'FAILED'           => [null, 'INTENTO FALLIDO'],
        'RETURNED'         => [null, 'DEVUELTO'],
        'CANCELLED'        => [null, 'CANCELADO'],
        'CANCELED'         => [null, 'CANCELADO'],
    ];

    public static function hayTabla(): bool
    {
        try {
            return Schema::hasTable('guia_estados');
        } catch (\Throwable $e) {
            return false;
        }
    }

    public static function de(?string $guia): ?self
    {
        $guia = preg_replace('/\D/', '', (string) $guia);
        if ($guia === '' || ! static::hayTabla()) return null;

        try {
            return static::where('guia', $guia)->first();
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** [etapa|null, texto] de un código de Express, aunque sea uno que no conocemos. */
    public static function traducir(?string $codigo): array
    {
        $c = strtoupper(trim((string) $codigo));
        if (isset(self::ESTADOS[$c])) return self::ESTADOS[$c];

        // Códigos nuevos que no estén en la lista: se adivina por la palabra.
        if (str_contains($c, 'DELIVER') && ! str_contains($c, 'OUT')) return [4, 'ENTREGADO'];
        if (str_contains($c, 'TRANSIT') || str_contains($c, 'ROUTE') || str_contains($c, 'OUT')) return [3, 'EN RUTA'];
        if (str_contains($c, 'WAREHOUSE') || str_contains($c, 'PICK') || str_contains($c, 'ASSIGN')) return [2, 'EN BODEGA'];
        if (str_contains($c, 'RETURN') || str_contains($c, 'CANCEL') || str_contains($c, 'INCIDEN') || str_contains($c, 'FAIL')) return [null, str_replace('_', ' ', $c)];

        return [1, $c !== '' ? str_replace('_', ' ', $c) : 'CREADO'];
    }

    /**
     * Guarda lo que dijo Express de una guía. Devuelve la etapa que quedó.
     *
     * $fila: guia, estado, entregado, cambio, repartidor, pago (como los manda el botón).
     */
    public static function registrar(array $fila): ?self
    {
        $guia = preg_replace('/\D/', '', (string) ($fila['guia'] ?? ''));
        if ($guia === '') return null;

        $codigo = strtoupper(trim((string) ($fila['estado'] ?? '')));
        [$etapa, $texto] = static::traducir($codigo);

        $reg = static::firstOrNew(['guia' => $guia]);
        $antes = $reg->estado;

        $cambio = static::fecha($fila['cambio'] ?? null) ?? now();
        $entregado = static::fecha($fila['entregado'] ?? null);
        $repartidor = trim((string) ($fila['repartidor'] ?? '')) ?: null;

        // Incidencias, devoluciones: no se mueve la barra, se deja donde iba.
        $reg->etapa = $etapa ?? ($reg->etapa ?: 1);
        $reg->estado = $codigo ?: $reg->estado;
        $reg->repartidor = $repartidor ? mb_substr($repartidor, 0, 120) : $reg->repartidor;
        $reg->pago = mb_substr((string) ($fila['pago'] ?? ''), 0, 40) ?: $reg->pago;
        $reg->entregado_at = $entregado;
        $reg->cambio_at = $cambio;
        $reg->faltas = 0;

        // Cada cambio de estado queda en el historial que ve el cliente.
        if ($codigo !== '' && $codigo !== $antes) {
            $hist = $reg->historial ?: [];
            $cuando = $etapa === 4 && $entregado ? $entregado : $cambio;
            array_unshift($hist, [
                'estado' => $texto,
                'desc'   => static::descripcion($etapa, $repartidor),
                'iso'    => $cuando->toIso8601String(),
            ]);
            $reg->historial = array_slice($hist, 0, 20);
        }

        $reg->save();

        return $reg;
    }

    /** Express no encontró la guía (p. ej. una vieja de Sistrack). */
    public static function noEncontrada(string $guia): void
    {
        $guia = preg_replace('/\D/', '', $guia);
        if ($guia === '') return;

        $reg = static::firstOrNew(['guia' => $guia]);
        if ($reg->exists && $reg->estado) {
            // Ya tenía estado: no se borra por un tropiezo.
            $reg->faltas = min(9, (int) $reg->faltas + 1);
        } else {
            $reg->faltas = min(9, (int) $reg->faltas + 1);
            $reg->etapa = $reg->etapa ?: 1;
        }
        $reg->save();
    }

    /** Historial listo para la vista del rastreo: estado, desc, fecha. */
    public function historialParaCliente(): array
    {
        $tz = config('app.timezone') === 'UTC' ? 'America/El_Salvador' : config('app.timezone');

        return collect($this->historial ?: [])->map(function ($ev) use ($tz) {
            try {
                $fecha = Carbon::parse($ev['iso'] ?? null)->setTimezone($tz)->format('d/m/Y g:i A');
            } catch (\Throwable $e) {
                $fecha = '';
            }

            return ['estado' => $ev['estado'] ?? '', 'desc' => $ev['desc'] ?? '', 'fecha' => $fecha];
        })->all();
    }

    protected static function descripcion(?int $etapa, ?string $repartidor): string
    {
        return match ($etapa) {
            4       => 'Paquete entregado',
            3       => $repartidor ? 'Salió a ruta de entrega' : 'En camino',
            2       => 'En Express El Salvador',
            1       => 'Orden registrada en Express',
            default => 'Express lo está revisando',
        };
    }

    protected static function fecha($v): ?Carbon
    {
        if (! $v) return null;
        try {
            return Carbon::parse($v);
        } catch (\Throwable $e) {
            return null;
        }
    }
}
