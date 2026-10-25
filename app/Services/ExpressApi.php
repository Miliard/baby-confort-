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
}
