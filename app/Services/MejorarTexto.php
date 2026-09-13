<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Corrige un mensaje antes de mandárselo al cliente.
 *
 * Arregla ortografía, tildes y puntuación, y le da un tono cordial de atención
 * al cliente. Lo que NO hace, a propósito:
 *
 *  · No inventa datos. Si el mensaje no dice un precio, no aparece un precio.
 *  · No agrega saludos ni despedidas que no estaban.
 *  · No alarga. Un mensaje corto tiene que seguir siendo corto.
 *
 * Eso último importa más de lo que parece: un corrector que "mejora" agregando
 * cosas termina prometiéndole al cliente algo que el negocio no dijo.
 */
class MejorarTexto
{
    /** ¿Está configurado como para poder usarse? */
    public static function disponible(): bool
    {
        return filled(static::clave());
    }

    private static function proveedor(): string
    {
        return config('ia.proveedor', 'anthropic');
    }

    private static function clave(): ?string
    {
        return config('ia.' . static::proveedor() . '.clave') ?: null;
    }

    private static function modelo(): string
    {
        return (string) config('ia.' . static::proveedor() . '.modelo');
    }

    /**
     * Devuelve ['ok' => true, 'texto' => '...'] o ['ok' => false, 'error' => '...'].
     *
     * Nunca lanza excepciones: quien lo llama está atendiendo a un cliente y no
     * puede quedarse con una pantalla rota por un problema de red.
     */
    public static function mejorar(string $texto): array
    {
        $texto = trim($texto);

        if ($texto === '') {
            return ['ok' => false, 'error' => 'No hay nada escrito.'];
        }

        if (! static::disponible()) {
            return ['ok' => false, 'error' => 'Falta cargar la clave de la inteligencia artificial en Railway.'];
        }

        $maximo = (int) config('ia.maximo', 1200);
        if (mb_strlen($texto) > $maximo) {
            return ['ok' => false, 'error' => "El mensaje pasa de {$maximo} caracteres. Cortalo en dos."];
        }

        try {
            return static::proveedor() === 'openai'
                ? static::conOpenAI($texto)
                : static::conAnthropic($texto);
        } catch (\Throwable $e) {
            Log::warning('Mejorar texto: ' . $e->getMessage());

            return ['ok' => false, 'error' => 'No se pudo conectar. Probá de nuevo en un momento.'];
        }
    }

    /** Las instrucciones. Acá está casi todo el resultado. */
    private static function instrucciones(): string
    {
        return <<<'TXT'
        Sos el corrector de estilo de Baby-Confort, una tienda de pañales y
        productos para bebé en El Salvador que atiende a sus clientes por
        WhatsApp.

        Recibís un mensaje escrito a las apuradas por alguien del equipo y lo
        devolvés corregido.

        Qué corregís:
        - Ortografía, tildes y puntuación.
        - Signos de apertura: ¿ y ¡ donde correspondan.
        - Mayúsculas al inicio de cada oración y en los nombres propios.
        - Abreviaturas de mensajería: "q" a "que", "xq" a "porque", "tmb" a
          "también", "porfa" a "por favor", "d" a "de".
        - Frases mal armadas, dejándolas claras y naturales.

        Reglas que no se rompen:
        - NO inventes ni agregues información que el mensaje no tenga. Nada de
          precios, plazos, tallas, direcciones ni promesas de entrega.
        - NO agregues saludos, despedidas ni emojis que no estuvieran.
        - NO alargues: si el original tiene una línea, la corrección tiene una
          línea. Mantené el mensaje igual de corto.
        - Usá "vos" si el original usa "vos", y "usted" si usa "usted". Respetá
          el trato que ya eligió quien escribió.
        - Mantené los emojis, los enlaces, los números y los montos EXACTAMENTE
          como están.
        - Si el mensaje ya está bien escrito, devolvelo igual.

        Respondé ÚNICAMENTE con el mensaje corregido. Sin comillas, sin
        explicaciones, sin comentarios, sin decir qué cambiaste.
        TXT;
    }

    private static function conAnthropic(string $texto): array
    {
        $r = Http::withHeaders([
                'x-api-key'         => static::clave(),
                'anthropic-version' => '2023-06-01',
            ])
            ->timeout(25)
            ->post('https://api.anthropic.com/v1/messages', [
                'model'      => static::modelo(),
                'max_tokens' => 1500,
                'system'     => static::instrucciones(),
                'messages'   => [
                    ['role' => 'user', 'content' => $texto],
                ],
            ]);

        if (! $r->successful()) {
            return ['ok' => false, 'error' => static::porQue($r)];
        }

        $salida = trim((string) $r->json('content.0.text'));

        return $salida !== ''
            ? ['ok' => true, 'texto' => $salida]
            : ['ok' => false, 'error' => 'Devolvió una respuesta vacía.'];
    }

    private static function conOpenAI(string $texto): array
    {
        $r = Http::withToken(static::clave())
            ->timeout(25)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model'    => static::modelo(),
                'messages' => [
                    ['role' => 'system', 'content' => static::instrucciones()],
                    ['role' => 'user',   'content' => $texto],
                ],
            ]);

        if (! $r->successful()) {
            return ['ok' => false, 'error' => static::porQue($r)];
        }

        $salida = trim((string) $r->json('choices.0.message.content'));

        return $salida !== ''
            ? ['ok' => true, 'texto' => $salida]
            : ['ok' => false, 'error' => 'Devolvió una respuesta vacía.'];
    }

    /** El motivo real, sin adornos, para que se pueda arreglar. */
    private static function porQue($r): string
    {
        $m = $r->json('error.message') ?: $r->body();

        if ($r->status() === 401) {
            return 'La clave no es válida. Revisala en Railway.';
        }

        if ($r->status() === 429) {
            return 'Se pasó del límite del proveedor, o la cuenta no tiene saldo.';
        }

        return mb_substr((string) $m, 0, 200);
    }
}
