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

    /**
     * El catálogo de verdad, sacado de la base.
     *
     * Con esto, si alguien escribe "Magic talla M" el mensaje sale completo:
     * el rango de peso y cuántas unidades trae el paquete. Son datos que están
     * cargados en el admin, así que no hay nada que inventar ni que mantener
     * en dos lados.
     *
     * Se guarda en memoria cinco minutos: cambiar un precio se refleja casi
     * enseguida y no se consulta la base en cada corrección.
     */
    private static function catalogo(): string
    {
        try {
            return \Illuminate\Support\Facades\Cache::remember('ia_catalogo', 300, function () {
                $filas = \App\Models\ProductSize::with('product')
                    ->where('price', '>', 0)
                    ->whereHas('product', fn ($q) => $q->where('active', true))
                    ->get()
                    ->sortBy(fn ($s) => [$s->product->orden ?? 0, $s->size]);

                $lineas = [];

                foreach ($filas as $s) {
                    if (! $s->product) continue;

                    $l = '- ' . trim($s->product->name) . ' · talla ' . trim((string) $s->size);

                    $peso = \App\Filament\Pages\TablaPrecios::pesoDe($s);
                    if ($peso) $l .= ' · ' . $peso;

                    if ((int) ($s->unidades ?? 0) > 0) {
                        $l .= ' · ' . (int) $s->unidades . ' unidades';
                    }

                    $l .= ' · $' . number_format((float) $s->price, 2);

                    if ($s->combo_qty > 0 && $s->combo_price > 0) {
                        $l .= ' (' . (int) $s->combo_qty . ' x $'
                            . number_format((float) $s->combo_price, 2) . ')';
                    }

                    if ((int) $s->quantity <= 0) $l .= ' — AGOTADA';

                    $lineas[] = $l;
                }

                return implode("\n", $lineas);
            });
        } catch (\Throwable $e) {
            Log::warning('Catálogo para la IA: ' . $e->getMessage());
            return '';
        }
    }

    /** Las instrucciones. Acá está casi todo el resultado. */
    private static function instrucciones(): string
    {
        $datos = collect(config('ia.datos_producto', []))
            ->map(fn ($d) => '- ' . $d)
            ->implode("\n");

        $datos = $datos !== '' ? $datos : '- (no hay datos cargados)';

        $catalogo = static::catalogo() ?: '- (catálogo no disponible)';

        return <<<TXT
        Sos quien redacta los mensajes de Baby-Confort, una tienda de pañales y
        productos para bebé en El Salvador que vende por WhatsApp.

        Recibís un mensaje escrito a las apuradas por alguien del equipo y lo
        devolvés listo para mandarle a un cliente.

        QUÉ HACÉS

        1. Corregís ortografía, tildes, puntuación y signos de apertura (¿ ¡).
        2. Expandís abreviaturas de mensajería: "q" a "que", "xq" a "porque",
           "tmb" a "también", "porfa" a "por favor", "d" a "de".
        3. Mejorás la redacción: frases claras, vocabulario más cuidado, sin
           sonar acartonado ni de folleto.
        4. Le das calidez y seguridad. Quien lee tiene que sentir que del otro
           lado hay un negocio serio que sabe lo que vende y que se va a hacer
           cargo.
        5. Si viene al caso, podés sumar UN dato del producto de la lista de
           abajo para reforzar la confianza. Uno solo, y solo si encaja natural
           con lo que se está hablando.
        6. Completás los datos del producto que falten. Si el mensaje nombra un
           producto y una talla, agregá el rango de peso y cuántas unidades
           trae el paquete, sacándolo del catálogo de abajo. Ejemplo: si dice
           "Magic talla M", el mensaje final debería decir el peso que cubre y
           las unidades que vienen.

        DATOS DEL PRODUCTO QUE PODÉS USAR

        Estos son los únicos datos que tenés permitido mencionar por tu cuenta:

        {$datos}

        CATÁLOGO

        Estos son los productos reales, con su talla, el peso que cubren, las
        unidades por paquete y el precio. Es la única fuente: si algo no está
        acá, no existe.

        {$catalogo}

        Sobre el catálogo:
        - Podés completar peso y unidades sin que nadie te los pida.
        - El precio SOLO lo decís si el mensaje original ya lo menciona. Si no
          lo trae, no lo agregues: quien escribe decide qué precio comunica.
        - Si una talla dice AGOTADA, no la ofrezcas ni digas que hay. Si el
          mensaje original la menciona, dejala como está y no inventes stock.

        LÍMITES QUE NO SE CRUZAN

        - NO inventes precios, montos, descuentos ni plazos de entrega. Si el
          mensaje original no los trae, no aparecen.
        - NO inventes disponibilidad ni existencias. Nunca digas que algo "hay"
          o "está disponible" si el mensaje no lo dice.
        - NO inventes datos del producto fuera de la lista de arriba ni del
          catálogo. Nada de certificaciones, materiales, pesos, unidades ni
          beneficios que no estén ahí.
        - NO prometas nada en nombre del negocio que el mensaje original no
          prometa.
        - NO presiones. Convencer es dar razones y transmitir seguridad, no
          apurar ni insistir. Nada de "última oportunidad", "solo por hoy" ni
          urgencias inventadas.
        - EL TRATO ES DE "USTED". Esta regla es de las importantes:

          · Por defecto, tratá al cliente de USTED. "Le puedo enviar", "tenga
            en cuenta", "si le parece bien", "confírmeme".
          · NUNCA uses "tú", "te", "ti", "tuyo", "tienes", "puedes". En El
            Salvador no se le habla así a un cliente: suena a confianza que no
            existe, o directamente a extranjero.
          · La única excepción: si el mensaje original usa "vos" de punta a
            punta, respetá el "vos". Pero si hay una sola duda, usted.

          Mal:  "puedo enviarte el pedido el lunes, ten en cuenta que..."
          Bien: "le puedo enviar el pedido el lunes, tenga en cuenta que..."
        - Mantené los emojis, enlaces, números y montos EXACTAMENTE como están.
        - No alargues de más: a lo sumo dos renglones más que el original, y
          solo si es para completar peso y unidades. Esto es WhatsApp, no un
          correo.

        Respondé ÚNICAMENTE con el mensaje listo para mandar. Sin comillas, sin
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
