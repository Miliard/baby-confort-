<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Pasa a texto las notas de voz que mandan los clientes.
 *
 * Mucha gente prefiere hablar antes que escribir, sobre todo dictando una
 * dirección larga. Tener eso en texto sirve para dos cosas: leerlo de un
 * vistazo sin ponerse los audífonos, y poder copiar la dirección a la guía
 * sin escuchar el audio tres veces.
 *
 * El audio no se borra: queda el reproductor en el chat por si hay que
 * confirmar algo que se entendió mal.
 */
class Transcribir
{
    /** Solo funciona con OpenAI, que es quien tiene el servicio de audio. */
    public static function disponible(): bool
    {
        return filled(config('ia.openai.clave'));
    }

    /**
     * Devuelve el texto del audio, o null si no se pudo.
     *
     * $ruta es la ruta dentro del disco público, tal como la guardó bajarMedia.
     */
    public static function deArchivo(?string $ruta): ?string
    {
        if (blank($ruta) || ! static::disponible()) return null;

        try {
            $disco = Storage::disk('public');
            if (! $disco->exists($ruta)) return null;

            // Whisper aguanta hasta 25 MB. Una nota de voz jamás llega a eso,
            // pero si alguien manda un archivo largo, mejor no intentarlo.
            if ((int) $disco->size($ruta) > 24 * 1024 * 1024) return null;

            $r = Http::withToken(config('ia.openai.clave'))
                ->timeout(90)
                ->attach('file', $disco->get($ruta), basename($ruta))
                ->post('https://api.openai.com/v1/audio/transcriptions', [
                    'model' => config('ia.modelo_audio', 'whisper-1'),
                    // Decirle el idioma mejora bastante el resultado y lo hace
                    // más rápido: no tiene que adivinar.
                    'language' => 'es',
                    'prompt'   => 'Conversación de WhatsApp de una tienda de pañales '
                                . 'en El Salvador. Pueden aparecer tallas (S, M, L, XL, XXL), '
                                . 'municipios y direcciones.',
                ]);

            if (! $r->successful()) {
                Log::warning('Transcribir: ' . mb_substr($r->body(), 0, 200));
                return null;
            }

            $texto = trim((string) $r->json('text'));

            return $texto !== '' ? $texto : null;
        } catch (\Throwable $e) {
            Log::warning('Transcribir: ' . $e->getMessage());
            return null;
        }
    }
}
