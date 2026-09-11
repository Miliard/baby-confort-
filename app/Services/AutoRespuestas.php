<?php

namespace App\Services;

use App\Models\WaConversacion;
use App\Models\WaMensaje;

/**
 * Respuestas que salen solas en cuanto llega el mensaje, sin esperar a nadie.
 *
 * Hoy hay una sola: la tabla de tallas. La estructura queda lista para agregar
 * más (precios, horarios, zonas de envío) editando config/auto-respuestas.php,
 * sin tocar este archivo.
 */
class AutoRespuestas
{
    /** Deja el texto comparable: sin tildes, sin signos, en minúsculas. */
    public static function normalizar(?string $texto): string
    {
        $t = mb_strtolower(trim((string) $texto));
        $t = strtr($t, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
        ]);
        $t = preg_replace('/[^a-z0-9\s]/u', ' ', $t);

        return trim(preg_replace('/\s+/', ' ', $t));
    }

    /**
     * Mira si el mensaje dispara alguna respuesta y, si sí, la manda.
     * Devuelve el mensaje enviado, o null si no había nada que responder.
     */
    public static function quizasResponder(WaConversacion $conv, string $texto): ?WaMensaje
    {
        $config = config('auto-respuestas', []);
        $limpio = static::normalizar($texto);
        if ($limpio === '') return null;

        foreach ($config['disparadores'] ?? [] as $d) {
            if (! ($d['activo'] ?? false)) continue;

            if (! static::coincide($limpio, $d['palabras'] ?? [])) continue;

            // Para no mandar la misma tabla tres veces si el cliente escribe
            // tres mensajes seguidos.
            if (static::yaRespondimosHacePoco($conv, $d['nombre'] ?? '', $config['espera_minutos'] ?? 180)) {
                return null;
            }

            $cuerpo = trim((string) ($d['texto'] ?? ''));
            if ($cuerpo === '' && ($d['nombre'] ?? '') === 'tallas') {
                $cuerpo = static::tablaDeTallas($d);
            }

            if ($cuerpo === '') continue;

            return WhatsappApi::enviarTexto($conv, $cuerpo, null, true);
        }

        return null;
    }

    /** ¿Alguna palabra clave aparece en el mensaje, como palabra suelta? */
    private static function coincide(string $texto, array $palabras): bool
    {
        foreach ($palabras as $p) {
            $buscar = static::normalizar($p);
            if ($buscar === '') continue;

            // Palabra completa: así "taya" no salta dentro de "atrayado".
            if (preg_match('/\b' . preg_quote($buscar, '/') . '\b/u', $texto)) return true;
        }

        return false;
    }

    /** ¿Ya se mandó esta misma respuesta hace poco en esta conversación? */
    private static function yaRespondimosHacePoco(WaConversacion $conv, string $nombre, int $minutos): bool
    {
        if ($minutos <= 0) return false;

        try {
            return WaMensaje::where('conversacion_id', $conv->id)
                ->where('automatico', true)
                ->where('created_at', '>=', now()->subMinutes($minutos))
                ->exists();
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Arma la tabla de tallas leyendo config/tallas_peso.php, que es el mismo
     * cuadro que se muestra en la tienda. Una sola fuente: si se corrige ahí,
     * se corrige también acá.
     */
    public static function tablaDeTallas(array $d = []): string
    {
        $pesos = config('tallas_peso', []);
        if (! $pesos) return '';

        $lineas = [];
        foreach ($pesos as $talla => $rango) {
            $lineas[] = '• *' . $talla . '* — ' . $rango;
        }

        $encabezado = trim((string) ($d['encabezado'] ?? '¡Hola! 💙 Estas son nuestras tallas:'));
        $cierre     = trim((string) ($d['cierre'] ?? ''));

        $texto = $encabezado . "\n\n" . implode("\n", $lineas);
        if ($cierre !== '') $texto .= "\n\n" . $cierre;

        return $texto;
    }
}
