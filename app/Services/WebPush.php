<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\WaSuscripcion;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Avisos que llegan al teléfono aunque la aplicación esté cerrada.
 *
 * Cómo funciona, en corto:
 *
 *  1. El navegador de cada persona genera una dirección propia donde Google
 *     (o Apple) le entrega los avisos. Esa dirección se guarda en la base.
 *  2. Para poder usarla hay que identificarse con un par de claves nuestras,
 *     que se generan una sola vez y viven en la tabla de ajustes.
 *  3. Se le manda un aviso VACÍO a esa dirección. El teléfono despierta, y el
 *     trabajador en segundo plano le pregunta al servidor cuántos mensajes
 *     hay sin leer y muestra la notificación.
 *
 * Lo de mandarlo vacío es a propósito: meter texto adentro obliga a cifrarlo
 * con un procedimiento bastante delicado. Sin contenido no hace falta, y de
 * paso ningún dato del cliente viaja por los servidores de Google.
 */
class WebPush
{
    /** ¿Se puede usar? Necesita la extensión de criptografía de PHP. */
    public static function disponible(): bool
    {
        return function_exists('openssl_pkey_new') && function_exists('openssl_sign');
    }

    // ── Las claves ───────────────────────────────────────────────────────────

    /**
     * La clave pública, en el formato que espera el navegador.
     *
     * Si todavía no existen, se generan acá mismo. No hay nada que configurar
     * a mano ni que pegar en Railway.
     */
    public static function clavePublica(): ?string
    {
        static::asegurarClaves();
        return Setting::get('push_clave_publica') ?: null;
    }

    private static function asegurarClaves(): void
    {
        if (Setting::get('push_clave_privada')) return;
        if (! static::disponible()) return;

        try {
            $par = openssl_pkey_new([
                'curve_name'       => 'prime256v1',
                'private_key_type' => OPENSSL_KEYTYPE_EC,
            ]);

            if (! $par) return;

            openssl_pkey_export($par, $privadaPem);
            $detalles = openssl_pkey_get_details($par);

            // La pública va como 0x04 + X + Y, cada uno de 32 bytes.
            $publica = "\x04"
                . str_pad($detalles['ec']['x'], 32, "\0", STR_PAD_LEFT)
                . str_pad($detalles['ec']['y'], 32, "\0", STR_PAD_LEFT);

            Setting::put('push_clave_privada', $privadaPem);
            Setting::put('push_clave_publica', static::base64Url($publica));
        } catch (\Throwable $e) {
            Log::warning('Push: no se pudieron generar las claves. ' . $e->getMessage());
        }
    }

    // ── Mandar ───────────────────────────────────────────────────────────────

    /**
     * Le avisa a todos los dispositivos registrados.
     *
     * Se llama desde el webhook, así que tiene que ser rápido y no puede
     * lanzar excepciones: si falla un aviso, Meta no tiene la culpa y no hay
     * que hacerle reintentar el mensaje entero.
     */
    public static function avisarATodos(?int $exceptoUsuario = null): void
    {
        if (! static::disponible()) return;
        if (! Setting::get('push_clave_privada')) static::asegurarClaves();

        $privada = Setting::get('push_clave_privada');
        if (! $privada) return;

        foreach (WaSuscripcion::activas() as $s) {
            if ($exceptoUsuario && (int) $s->user_id === $exceptoUsuario) continue;

            static::mandarA($s, $privada);
        }
    }

    private static function mandarA(WaSuscripcion $s, string $privadaPem): void
    {
        try {
            $partes = parse_url($s->endpoint);
            if (! $partes || empty($partes['host'])) return;

            $origen = $partes['scheme'] . '://' . $partes['host'];
            $jwt = static::firmar($origen, $privadaPem);
            if (! $jwt) return;

            $r = Http::withHeaders([
                    'Authorization' => 'vapid t=' . $jwt . ', k=' . Setting::get('push_clave_publica'),
                    'TTL'           => '600',        // diez minutos y se descarta
                    'Urgency'       => 'high',
                    'Content-Length' => '0',
                ])
                ->timeout(8)
                ->withBody('', 'application/octet-stream')
                ->post($s->endpoint);

            // 404 y 410 significan que ese navegador ya no existe: se borra
            // para no seguir intentando con un teléfono que se formateó.
            if (in_array($r->status(), [404, 410], true)) {
                $s->delete();
                return;
            }

            if (! $r->successful()) {
                $s->update(['ultimo_error_at' => now()]);
                Log::warning('Push ' . $r->status() . ': ' . mb_substr($r->body(), 0, 150));
            }
        } catch (\Throwable $e) {
            Log::warning('Push: ' . $e->getMessage());
        }
    }

    /** El pase de entrada que exige el servidor de avisos. */
    private static function firmar(string $origen, string $privadaPem): ?string
    {
        try {
            $cabecera = ['typ' => 'JWT', 'alg' => 'ES256'];

            $cuerpo = [
                'aud' => $origen,
                'exp' => time() + 12 * 3600,
                'sub' => 'mailto:' . (config('mail.from.address') ?: 'soporte@baby-confort.shop'),
            ];

            $base = static::base64Url(json_encode($cabecera))
                . '.' . static::base64Url(json_encode($cuerpo));

            $clave = openssl_pkey_get_private($privadaPem);
            if (! $clave) return null;

            $firmaDer = '';
            if (! openssl_sign($base, $firmaDer, $clave, OPENSSL_ALGO_SHA256)) return null;

            $firma = static::derACrudo($firmaDer);
            if (! $firma) return null;

            return $base . '.' . static::base64Url($firma);
        } catch (\Throwable $e) {
            Log::warning('Push (firma): ' . $e->getMessage());
            return null;
        }
    }

    /**
     * openssl firma en un formato con envoltorio; el aviso lo quiere pelado.
     *
     * Son dos números de 32 bytes, uno detrás del otro. Hay que sacarlos del
     * envoltorio y rellenarlos si vinieron cortos.
     */
    private static function derACrudo(string $der): ?string
    {
        $i = 0;

        if (($der[$i++] ?? '') !== "\x30") return null;

        // Largo total: puede venir en formato corto o largo.
        $largo = ord($der[$i++]);
        if ($largo > 0x80) $i += $largo - 0x80;

        $numeros = [];

        for ($n = 0; $n < 2; $n++) {
            if (($der[$i++] ?? '') !== "\x02") return null;

            $largoNum = ord($der[$i++]);
            $valor = substr($der, $i, $largoNum);
            $i += $largoNum;

            // Se le saca el cero de relleno que pone el formato y se ajusta a 32.
            $valor = ltrim($valor, "\x00");
            $numeros[] = str_pad($valor, 32, "\x00", STR_PAD_LEFT);
        }

        return $numeros[0] . $numeros[1];
    }

    private static function base64Url(string $datos): string
    {
        return rtrim(strtr(base64_encode($datos), '+/', '-_'), '=');
    }
}
