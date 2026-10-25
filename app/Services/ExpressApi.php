<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

/**
 * API del sistema nuevo de Express (Facilito / T&T).
 *
 * La clave va en la variable de Railway TNT_API_KEY (empieza con fk_live_).
 * Nunca se guarda en la base de datos ni se muestra en pantalla.
 */
class ExpressApi
{
    public static function base(): string
    {
        return rtrim((string) env('TNT_API_URL', 'https://xpress.facilitoerp.com/api/v1/tnt'), '/');
    }

    public static function configurada(): bool
    {
        return strlen(self::key()) > 10;
    }

    /** La key de Railway, sin espacios ni comillas que se cuelan al pegar. */
    public static function key(): string
    {
        return trim((string) env('TNT_API_KEY', ''), " \t\n\r\0\x0B\"'");
    }

    /** Cómo se manda la key. Se prueba cuál acepta Express y se recuerda. */
    public const MODOS = ['bearer', 'x-api-key', 'raw'];

    public static function cabeceras(string $modo): array
    {
        $key = self::key();

        return match ($modo) {
            'x-api-key' => ['X-API-Key' => $key],
            'raw'       => ['Authorization' => $key],
            default     => ['Authorization' => 'Bearer ' . $key],
        };
    }

    /** Datos de la key que se pueden mostrar sin riesgo (no la key). */
    public static function diagnosticoKey(): array
    {
        $k = self::key();
        $crudo = (string) env('TNT_API_KEY', '');

        return [
            'largo'              => strlen($k),
            'empieza_con'        => substr($k, 0, 16),
            'tiene_puntos_…'     => str_contains($k, '…') || str_contains($k, '...'),
            'tenia_espacios_o_comillas' => $crudo !== $k,
        ];
    }

    /** @return array{status:int, json:mixed, texto:string} */
    public static function llamar(string $metodo, string $ruta, array $datos = [], ?string $modo = null): array
    {
        $modo = $modo ?: (string) \App\Models\Setting::get('tnt_api_modo', 'bearer');

        try {
            $req = Http::timeout(20)->acceptJson()->withHeaders(self::cabeceras($modo));

            $url = self::base() . '/' . ltrim($ruta, '/');
            $res = strtoupper($metodo) === 'GET' ? $req->get($url, $datos) : $req->send(strtoupper($metodo), $url, ['json' => $datos]);

            return ['status' => $res->status(), 'json' => $res->json(), 'texto' => mb_substr($res->body(), 0, 2000)];
        } catch (\Throwable $e) {
            return ['status' => 0, 'json' => null, 'texto' => 'Error de conexión: ' . $e->getMessage()];
        }
    }

    /**
     * Tapa los datos personales y recorta listas largas, para poder ver la
     * FORMA de la respuesta sin exponer clientes.
     */
    public static function tapar($v, int $nivel = 0)
    {
        if ($nivel > 8) return '…';

        if (is_array($v)) {
            $lista = array_is_list($v);
            if ($lista) $v = array_slice($v, 0, 2);
            $out = [];
            foreach ($v as $k => $x) {
                if (! $lista && is_string($k) && preg_match('/name|nombre|phone|tel|address|direcc|email|correo|reference|referencia|lat|lng|token|key|secret|password|dui|nit/i', $k)) {
                    $out[$k] = is_null($x) ? null : (is_array($x) ? self::tapar($x, $nivel + 1) : '***');
                } else {
                    $out[$k] = self::tapar($x, $nivel + 1);
                }
            }
            return $out;
        }

        return is_string($v) ? mb_substr($v, 0, 80) : $v;
    }

    /**
     * Trae de Express las órdenes recientes y guarda el estado de cada guía.
     *
     * La API no filtra por número de guía (orders/{id} es el id interno), así
     * que se recorre la lista, que viene de la más nueva a la más vieja, hasta
     * llegar a órdenes de hace más de $dias días.
     *
     * @return array{ok:bool, revisadas:int, entregadas:int, en_ruta:int, bodega:int, error:?string}
     */
    public static function sincronizar(int $paginas = 10, int $dias = 35): array
    {
        $cuenta = ['ok' => false, 'revisadas' => 0, 'entregadas' => 0, 'en_ruta' => 0, 'bodega' => 0, 'error' => null];

        if (! self::configurada()) {
            $cuenta['error'] = 'Falta la variable TNT_API_KEY en Railway';
            return $cuenta;
        }
        if (! \App\Models\GuiaEstado::hayTabla()) {
            $cuenta['error'] = 'Falta la tabla guia_estados';
            return $cuenta;
        }

        $lock = \Illuminate\Support\Facades\Cache::lock('express_api_sync', 90);
        if (! $lock->get()) {
            $cuenta['error'] = 'Ya se está actualizando, intente en un minuto';
            return $cuenta;
        }

        try {
            $limite = now()->subDays($dias);

            for ($pagina = 1; $pagina <= $paginas; $pagina++) {
                $r = self::llamar('GET', 'orders', ['page' => $pagina, 'limit' => 50]);
                if ($r['status'] < 200 || $r['status'] >= 300 || ! is_array($r['json'])) {
                    $cuenta['error'] = 'Express respondió ' . $r['status'] . ': ' . (is_array($r['json']) ? ($r['json']['message'] ?? '') : mb_substr($r['texto'], 0, 120));
                    break;
                }

                $data  = $r['json']['data'] ?? [];
                $items = $data['items'] ?? [];
                if (! $items) break;

                $viejas = false;
                foreach ($items as $o) {
                    if (! is_array($o) || empty($o['tracking_code'])) continue;

                    $reg = \App\Models\GuiaEstado::registrar([
                        'guia'       => $o['tracking_code'],
                        'express_id' => $o['id'] ?? null,
                        'estado'     => $o['status'] ?? '',
                        'entregado'  => $o['delivered_at'] ?? null,
                        'cambio'     => $o['updated_at'] ?? null,
                        'repartidor' => $o['driver']['name'] ?? null,
                        'pago'       => $o['payment_status'] ?? null,
                        'url'        => $o['tracking_url'] ?? null,
                    ]);

                    if ($reg) {
                        $cuenta['revisadas']++;
                        match ((int) $reg->etapa) {
                            4       => $cuenta['entregadas']++,
                            3       => $cuenta['en_ruta']++,
                            2       => $cuenta['bodega']++,
                            default => null,
                        };
                    }

                    try {
                        if (! empty($o['created_at']) && \Illuminate\Support\Carbon::parse($o['created_at'])->lt($limite)) {
                            $viejas = true;
                        }
                    } catch (\Throwable $e) {
                    }
                }

                $total = (int) ($data['total'] ?? 0);
                if ($viejas || ($total && $pagina * 50 >= $total)) break;
            }

            $cuenta['ok'] = $cuenta['error'] === null;
            \App\Models\Setting::put('express_api_ultima', now()->toIso8601String());
        } catch (\Throwable $e) {
            $cuenta['error'] = $e->getMessage();
        } finally {
            $lock->release();
        }

        return $cuenta;
    }

    /**
     * Sincroniza solo si pasaron más de $minutos desde la última vez. Rápido y
     * sin errores hacia afuera: se usa al abrir el rastreo del cliente.
     */
    public static function sincronizarSiHaceFalta(int $minutos = 10): void
    {
        static $hecho = false;
        if ($hecho || ! self::configurada()) return;
        $hecho = true;

        try {
            $ultima = \App\Models\Setting::get('express_api_ultima');
            if ($ultima && \Illuminate\Support\Carbon::parse($ultima)->gt(now()->subMinutes($minutos))) return;

            self::sincronizar(3);
        } catch (\Throwable $e) {
            // el rastreo se muestra igual con lo último guardado
        }
    }

    /**
     * Arma la orden para Express con los datos de una guía de "Crear guías".
     * Mismos nombres de campos que devuelve la API al consultar órdenes.
     */
    public static function datosOrden(array $g, int $id): array
    {
        [$depto, $muni] = SistrackExcel::ubicacionTnt($g['departamento'] ?? '', $g['municipio'] ?? '');

        $telContacto = trim((string) ($g['telefono_recibe'] ?? '')) !== '' ? $g['telefono_recibe'] : ($g['telefono'] ?? '');
        $tel = preg_replace('/\D/', '', (string) $telContacto);
        if (strlen($tel) === 11 && str_starts_with($tel, '503')) $tel = substr($tel, 3);

        $cobrar = round((float) ($g['cobrar'] ?? 0), 2);

        return [
            'merchant_order_id' => 'BC-' . $id,
            'delivery_type'     => 'DOOR_TO_DOOR',
            'package_count'     => 1,
            'contents'          => SistrackExcel::limpiar($g['descripcion'] ?? '') ?: 'Productos para bebe',
            'cod_amount'        => $cobrar,
            'notes'             => $cobrar > 0 ? 'COBRAR AL ENTREGAR: $' . number_format($cobrar, 2) : 'PAGADO - no cobrar',
            'fragile'           => false,
            'recipient'         => [
                'name'         => SistrackExcel::nombreConTelefono($g['telefono'] ?? '', $g['nombre'] ?? ''),
                'phone'        => $tel,
                'address'      => SistrackExcel::limpiar($g['direccion'] ?? ''),
                'department'   => $depto,
                'municipality' => $muni,
            ],
        ];
    }

    /**
     * Crea UNA orden en Express.
     *
     * @return array{ok:bool, guia:?string, id:?int, url:?string, cod:?float, orden:?array, error:?string}
     */
    public static function crearOrden(array $g, int $id): array
    {
        $r = self::llamar('POST', 'orders', self::datosOrden($g, $id));
        $json = is_array($r['json']) ? $r['json'] : [];
        $orden = $json['data']['order'] ?? $json['data'] ?? $json['order'] ?? null;
        if (is_array($orden) && isset($orden['items'][0])) $orden = $orden['items'][0];

        $guia = is_array($orden) ? ($orden['tracking_code'] ?? $orden['trackingCode'] ?? null) : null;

        if ($r['status'] >= 200 && $r['status'] < 300 && $guia) {
            return [
                'ok'    => true,
                'guia'  => preg_replace('/\D/', '', (string) $guia),
                'id'    => isset($orden['id']) ? (int) $orden['id'] : null,
                'url'   => $orden['tracking_url'] ?? null,
                'cod'   => isset($orden['cod_amount']) ? (float) $orden['cod_amount'] : null,
                'orden' => $orden,
                'error' => null,
            ];
        }

        // Mensaje de Express tal cual, con el detalle de campos si lo manda.
        $msg = $json['message'] ?? ('Express respondió ' . $r['status']);
        if (! empty($json['errors'])) {
            $msg .= ' — ' . mb_substr(json_encode($json['errors'], JSON_UNESCAPED_UNICODE), 0, 400);
        } elseif ($r['json'] === null && $r['texto'] !== '') {
            $msg .= ' — ' . mb_substr($r['texto'], 0, 200);
        } elseif ($r['status'] >= 200 && $r['status'] < 300) {
            $msg = 'Express aceptó, pero no devolvió número de guía. Revise en Express antes de reintentar.';
        }

        return ['ok' => false, 'guia' => null, 'id' => null, 'url' => null, 'cod' => null, 'orden' => null, 'error' => $msg];
    }

    /**
     * Órdenes que ya están en Express con nuestro número interno (BC-123).
     * Sirve para no crear dos veces la misma si un envío anterior se cortó.
     *
     * @return array<string, array> merchant_order_id => orden
     */
    public static function ordenesNuestras(int $paginas = 2): array
    {
        $map = [];
        for ($p = 1; $p <= $paginas; $p++) {
            $r = self::llamar('GET', 'orders', ['page' => $p, 'limit' => 50]);
            $items = is_array($r['json']) ? ($r['json']['data']['items'] ?? []) : [];
            foreach ($items as $o) {
                $m = (string) ($o['merchant_order_id'] ?? '');
                if (str_starts_with($m, 'BC-')) $map[$m] = $o;
            }
            if (count($items) < 50) break;
        }

        return $map;
    }
}
