<?php

namespace App\Services;

use App\Models\GuiaBorrador;

/**
 * Manda UNA guía de la cola a Express por la API y la deja amarrada a su
 * cliente, su rastreo y su pedido. Lo usan "Enviar a Express" de Crear guías
 * (en tanda) y "Guardar y enviar a Express" del formulario de WhatsApp (una).
 */
class ExpressEnvio
{
    /**
     * @param array $yaEstan órdenes que ya están en Express con nuestro número
     *                       (BC-123 => orden), para no crearlas dos veces.
     * @return array{ok:bool, guia:?string, error:?string, cobroMal:?string}
     */
    public static function enviarUna(GuiaBorrador $g, int $lote, array $yaEstan = []): array
    {
        if (filled($g->guia)) {
            return ['ok' => true, 'guia' => (string) $g->guia, 'error' => null, 'cobroMal' => null];
        }

        $fila = $g->aFila();
        $previa = $yaEstan['BC-' . $g->id] ?? null;

        if ($previa) {
            $r = ['ok' => true, 'guia' => preg_replace('/\D/', '', (string) $previa['tracking_code']), 'id' => $previa['id'] ?? null,
                  'url' => $previa['tracking_url'] ?? null, 'cod' => isset($previa['cod_amount']) ? (float) $previa['cod_amount'] : null,
                  'orden' => $previa, 'error' => null];
        } else {
            $r = ExpressApi::crearOrden($fila, $g->id);
        }

        if (! $r['ok']) {
            $g->express_error = mb_substr((string) $r['error'], 0, 1000);
            // Aceptó sin devolver número: puede haberse creado. No se reintenta sola.
            if (str_starts_with((string) $r['error'], 'Express aceptó')) $g->express_at = now();
            $g->save();

            return ['ok' => false, 'guia' => null, 'error' => (string) $r['error'], 'cobroMal' => null];
        }

        $g->guia = $r['guia'];
        $g->express_error = null;
        $g->express_at = now();
        $g->lote = $g->lote ?: $lote;
        $g->descargado_at = $g->descargado_at ?: now();
        $g->save();

        static::amarrar($fila, $r);

        // Que Express haya guardado bien el cobro.
        $cobrar = round((float) $g->cobrar, 2);
        $cobroMal = null;
        if ($r['cod'] !== null && abs($r['cod'] - $cobrar) > 0.009) {
            $cobroMal = 'Express guardó cobro $' . number_format($r['cod'], 2)
                . ' y debía ser $' . number_format($cobrar, 2) . '. Corríjalo en Express.';
        }

        return ['ok' => true, 'guia' => $r['guia'], 'error' => null, 'cobroMal' => $cobroMal];
    }

    /**
     * Lote para las guías que se mandan sueltas desde WhatsApp: todas las del
     * mismo día van al mismo lote, así no se llena la lista de lotes de uno.
     */
    public static function loteDelDia(): int
    {
        $hoy = now('America/El_Salvador')->toDateString();

        try {
            $guardado = json_decode((string) \App\Models\Setting::get('express_lote_dia', ''), true);
            if (is_array($guardado) && ($guardado['fecha'] ?? '') === $hoy && ! empty($guardado['lote'])) {
                return (int) $guardado['lote'];
            }

            $lote = GuiaBorrador::proximoNumero();
            \App\Models\Setting::put('express_lote_dia', json_encode(['fecha' => $hoy, 'lote' => $lote]));

            return $lote;
        } catch (\Throwable $e) {
            return GuiaBorrador::proximoNumero();
        }
    }

    /**
     * Amarra la guía que devolvió Express al cliente (para el rastreo y las
     * fotos) y al pedido de la tienda, igual que al importar el PDF.
     */
    public static function amarrar(array $fila, array $r): void
    {
        $guia = $r['guia'];

        try {
            $reg = \App\Models\GuiaFoto::where('guia', $guia)->first()
                ?? \App\Models\GuiaFoto::pendientePorTelefono($fila['telefono'] ?? '')
                ?? new \App\Models\GuiaFoto();
            $reg->guia      = $guia;
            $reg->nombre    = $fila['nombre'] ?: $reg->nombre;
            $reg->telefono  = $fila['telefono'] ?: $reg->telefono;
            $reg->contenido = $fila['descripcion'] ?: $reg->contenido;
            $reg->cobrar    = (float) ($fila['cobrar'] ?? 0);
            $reg->lote      = $reg->lote ?: now()->format('Y-m-d H:i:s');
            $reg->save();
        } catch (\Throwable $e) {
        }

        try {
            if (is_array($r['orden'] ?? null)) {
                $o = $r['orden'];
                \App\Models\GuiaEstado::registrar([
                    'guia' => $guia, 'express_id' => $o['id'] ?? null, 'estado' => $o['status'] ?? 'CREATED',
                    'entregado' => $o['delivered_at'] ?? null, 'cambio' => $o['updated_at'] ?? null,
                    'repartidor' => $o['driver']['name'] ?? null, 'pago' => $o['payment_status'] ?? null,
                    'url' => $o['tracking_url'] ?? null,
                ]);
            }
        } catch (\Throwable $e) {
        }

        // Pedido de la tienda con ese teléfono que aún no tenga guía.
        try {
            $corto = \App\Models\GuiaFoto::telefonoCorto($fila['telefono'] ?? '');
            if ($corto && strlen($corto) === 8) {
                $limpio = "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(phone,''),' ',''),'-',''),'(',''),')',''),'+','')";
                $pedido = \App\Models\Order::whereRaw("$limpio LIKE ?", ['%' . $corto])
                    ->where(fn ($q) => $q->whereNull('guia')->orWhere('guia', ''))
                    ->orderByDesc('id')->first();
                if ($pedido) {
                    $pedido->guia = $guia;
                    $pedido->save();
                }
            }
        } catch (\Throwable $e) {
        }
    }
}
