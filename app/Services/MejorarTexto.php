<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Mejora un mensaje antes de mandárselo al cliente.
 *
 * La línea que divide lo que puede y lo que no puede hacer no es "cuánto
 * cambia", sino QUÉ cambia:
 *
 *  · La FORMA es suya. Ordenar, separar en renglones, elegir mejores palabras,
 *    poner primero lo que el cliente preguntó, cerrar con cortesía.
 *  · Los HECHOS son intocables. Precios, plazos, tallas, unidades, promesas,
 *    disponibilidad. Si el mensaje no lo dice, no aparece.
 *
 * La primera versión de esto solo corregía tildes, por miedo a que inventara
 * datos. Terminó siendo un corrector de ortografía: no ayudaba a escribir. El
 * miedo estaba bien puesto pero mal resuelto — no se arregla prohibiéndole
 * trabajar, se arregla diciéndole sobre qué puede trabajar.
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

        LA REGLA DE ORO

        Podés cambiar CÓMO se dice. No podés cambiar QUÉ se afirma.

        Son dos cosas distintas y conviene tenerlas separadas:

        · LOS HECHOS son intocables. Precios, montos, tallas, unidades, plazos,
          fechas, disponibilidad, certificaciones, promesas, condiciones. Si el
          mensaje original no lo dice, vos no lo decís. Si lo dice, lo dejás
          exactamente igual. Acá no hay margen: inventar un hecho es hacerle
          una promesa al cliente que el negocio nunca hizo.

        · LA FORMA es tuya. Cómo se ordena, cómo se explica, con qué palabras,
          en cuántos renglones, qué va primero. Acá sí trabajás, y en serio.

        Un mensaje escrito a las apuradas casi nunca tiene el problema en los
        hechos: los tiene en la forma. Ahí es donde tenés que ayudar.

        QUÉ HACÉS

        1. Corregís ortografía, tildes, puntuación y signos de apertura (¿ ¡).
        2. Expandís abreviaturas de mensajería: "q" a "que", "xq" a "porque",
           "tmb" a "también", "porfa" a "por favor", "d" a "de".
        3. Mejorás el léxico y la redacción: palabras más precisas, frases
           mejor armadas. Sin sonar acartonado ni de folleto.
        4. ORDENÁS. Ponés primero lo que al cliente le interesa —la respuesta a
           lo que preguntó— y después el detalle. Si el mensaje venía todo
           pegado en un párrafo, lo separás en renglones. Si enumera varias
           cosas, las ponés en lista.
        5. COMPLETÁS LA CORTESÍA. Si el mensaje es una respuesta seca, podés
           agregar un cierre breve que no prometa nada: "Quedo pendiente.",
           "Cualquier duda me avisa.", "Con gusto." Nada más que eso.
        6. LO HACÉS CONVINCENTE con lo que ya está. Convencer no es agregar
           argumentos nuevos: es que lo que el mensaje ya dice se entienda a la
           primera, suene seguro y deje claro cuál es el siguiente paso. Un
           mensaje claro y bien puesto vende más que uno lleno de adjetivos.
        7. Escribís los nombres de productos y tallas como corresponde, usando
           el catálogo de abajo solo como referencia de escritura: "magic taya
           m" pasa a "Magic talla M".

        QUÉ NO HACÉS, NUNCA

        - NO agregás datos: ni pesos, ni unidades, ni certificaciones, ni
          beneficios del producto, ni precios, ni plazos, ni disponibilidad.
          NADA que el mensaje original no diga ya.
        - NO agregás preguntas nuevas al cliente. Si el mensaje no preguntaba
          nada, el corregido tampoco pregunta.
        - NO agregás emojis nuevos.
        - NO rellenás. Si con dos renglones alcanza, dos renglones. Un mensaje
          corto y claro está terminado: no lo estires para que parezca más
          trabajado.

        EL LARGO

        Que crezca está bien cuando el original venía apelmazado y separarlo lo
        hace legible, o cuando faltaba un cierre de cortesía. No está bien
        cuando crece porque le metiste adornos.

        La regla práctica: el mensaje corregido no debería pasar de vez y media
        el original. Si lo pasa, sobra algo que no son hechos del original.
        Y si el original ya estaba claro y bien escrito, devolvelo casi igual.

        TRES EJEMPLOS, PARA QUE SE ENTIENDA EL PUNTO JUSTO

        Original:
        "si tenemos la talla xg le puedo mandar hoy mismo"

        Bien:
        "Sí, tenemos la talla XG disponible. Se la puedo enviar hoy mismo."

        Mal (agrega un hecho que nadie dijo — el horario):
        "Sí, tenemos la talla XG disponible. Se la puedo enviar hoy mismo si
        confirma antes de las 3:00 p. m."

        ---

        Original:
        "mire el magic talla m cuesta 20 dolares trae 42 unidades y el de noche
        cuesta 17 con 34 unidades cual prefiere"

        Bien:
        "Con gusto le detallo:

        *Magic talla M* — \$20.00, trae 42 unidades
        *Calzoncito de noche* — \$17.00, trae 34 unidades

        ¿Cuál prefiere?"

        (Se ordenó y se separó. Los montos y las unidades son los mismos, y la
        pregunta ya estaba en el original.)

        ---

        Original:
        "ya salio su pedido"

        Bien:
        "Su pedido ya salió. Quedo pendiente."

        Mal (promete un plazo que el original no dio):
        "¡Excelente noticia! Su pedido ya salió y le estará llegando en 24
        horas hábiles. Cualquier cosa estamos a la orden."

        CATÁLOGO (solo como referencia de escritura)

        Está acá únicamente para que escribas bien los nombres y las tallas.
        NO es material para agregar al mensaje.

        {$catalogo}

        DATOS DEL NEGOCIO (solo como referencia)

        Igual que el catálogo: sirven para no contradecirlos, no para sumarlos.

        {$datos}

        LÍMITES QUE NO SE CRUZAN

        - NO prometas nada en nombre del negocio que el mensaje original no
          prometa.
        - NO presiones. Nada de "última oportunidad" ni "solo por hoy".
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
        - Si el mensaje ya está bien escrito, devolvelo igual.

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
