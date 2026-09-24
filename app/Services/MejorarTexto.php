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
 *    poner primero lo que el cliente preguntó.
 *
 *    Lo que NO es forma: agregar despedidas. Decía acá "cerrar con cortesía" y
 *    eso le daba permiso para pegarle un "quedo atento" al final de todo. Una
 *    afirmación cerrada con "quedo atento" deja de ser afirmación: suena a que
 *    estás esperando que el cliente confirme algo que ya confirmaste vos.
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
            return ['ok' => false, 'error' => 'Falta cargar ' . static::variable() . ' en Railway.'];
        }

        // Se revisa antes de salir a internet: si la clave es del otro
        // proveedor, el viaje sobra y la respuesta sería un 401 que no explica
        // nada.
        if ($mal = static::claveDesalineada()) {
            return ['ok' => false, 'error' => $mal];
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
        - NO agregás DESPEDIDAS ni coletillas de cortesía que el original no
          tenga. Nada de "quedo atento", "quedo pendiente", "cualquier cosa me
          avisa", "estamos a la orden", "quedo al pendiente de su respuesta".
          Si el original no se despide, el corregido tampoco.
        - NO le bajás la firmeza a una afirmación. Esto es de lo más
          importante que hay acá.

          Cuando el mensaje AFIRMA algo —una fecha, una entrega, un plazo— el
          corregido lo afirma con la misma seguridad. Una coletilla de espera
          al final convierte una confirmación en una consulta, y el cliente
          entiende que todavía falta que él conteste algo.

          Original:
          "yo se los mando hoy para que le llegue mañana"

          Bien:
          "Se los mando hoy para que le llegue mañana."

          Mal (era una confirmación y quedó como si esperara respuesta):
          "Hoy se los mando para que le llegue mañana, quedo atento."

        - NO agregás emojis nuevos.
        - NO rellenás. Si con dos renglones alcanza, dos renglones. Un mensaje
          corto y claro está terminado: no lo estires para que parezca más
          trabajado.

        EL LARGO

        Que crezca está bien cuando el original venía apelmazado y separarlo lo
        hace legible. No está bien cuando crece porque le metiste adornos o una
        despedida que nadie pidió.

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
        "Su pedido ya salió."

        Mal (promete un plazo que el original no dio, y encima se despide):
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
                'model'       => static::modelo(),
                'max_tokens'  => 1500,
                // Mismo motivo que del otro lado: corregir no es inventar.
                'temperature' => static::temperatura(),
                'system'      => static::instrucciones(),
                'messages'    => [
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

    /**
     * Qué tan suelto escribe.
     *
     * Acá estaba la razón de que el corrector se pusiera incoherente: este
     * valor no se mandaba, y sin mandarlo OpenAI usa 1, que es el máximo
     * razonable. Eso está bien para escribir algo creativo y está MAL para
     * esto: el mismo mensaje, corregido dos veces, salía distinto las dos.
     *
     * Para corregir un mensaje a un cliente queremos lo contrario de
     * creatividad. Queremos que si el mensaje ya estaba bien, lo devuelva
     * igual; y que si hay que arreglarlo, lo arregle siempre de la misma
     * manera. 0.2 deja apenas el juego necesario para elegir una palabra
     * mejor, sin ponerse a inventar.
     */
    private static function temperatura(): float
    {
        return (float) config('ia.temperatura', 0.2);
    }

    /**
     * ¿Este modelo acepta que le fijen la temperatura?
     *
     * Los modelos que razonan (la familia gpt-5 y los "o") no la aceptan: si
     * se les manda, contestan un error y no corrigen nada. Así que se les
     * manda la instrucción sin ese campo. No hace falta — esos ya son
     * bastante estables por su cuenta.
     */
    private static function aceptaTemperatura(): bool
    {
        $m = strtolower(static::modelo());

        foreach (['gpt-5', 'o1', 'o3', 'o4', 'o5'] as $familia) {
            if (str_starts_with($m, $familia)) return false;
        }

        return true;
    }

    private static function conOpenAI(string $texto): array
    {
        $cuerpo = [
            'model'    => static::modelo(),
            'messages' => [
                ['role' => 'system', 'content' => static::instrucciones()],
                ['role' => 'user',   'content' => $texto],
            ],
        ];

        if (static::aceptaTemperatura()) {
            $cuerpo['temperature'] = static::temperatura();
        }

        $r = Http::withToken(static::clave())
            ->timeout(25)
            ->post('https://api.openai.com/v1/chat/completions', $cuerpo);

        if (! $r->successful()) {
            return ['ok' => false, 'error' => static::porQue($r)];
        }

        $salida = trim((string) $r->json('choices.0.message.content'));

        return $salida !== ''
            ? ['ok' => true, 'texto' => $salida]
            : ['ok' => false, 'error' => 'Devolvió una respuesta vacía.'];
    }

    /** En qué variable de Railway vive la clave del proveedor elegido. */
    private static function variable(): string
    {
        return static::proveedor() === 'openai' ? 'OPENAI_API_KEY' : 'ANTHROPIC_API_KEY';
    }

    /**
     * ¿La clave cargada es del proveedor que está configurado?
     *
     * Esto se pregunta ANTES de salir a internet, y es la causa más común del
     * "clave no válida": se carga una clave de un proveedor mientras el panel
     * está apuntando al otro. Desde afuera se ve idéntico a una clave vencida
     * —los dos casos contestan 401— pero se arreglan de maneras distintas, y
     * el mensaje de antes no los sabía distinguir.
     *
     * Se mira la forma, no el contenido: las de Anthropic empiezan con
     * "sk-ant-", las de OpenAI con "sk-" a secas. Acá no se imprime ni se
     * registra la clave, solo si arranca de una manera o de la otra.
     */
    private static function claveDesalineada(): ?string
    {
        $c = trim((string) static::clave());
        if ($c === '') return null;

        $deAnthropic = str_starts_with($c, 'sk-ant-');
        $p = static::proveedor();

        if ($p === 'openai' && $deAnthropic) {
            return 'La clave cargada es de Anthropic, pero el panel está configurado para OpenAI. '
                 . 'En Railway: o poné IA_PROVEEDOR en "anthropic", o cargá una clave de OpenAI '
                 . 'en OPENAI_API_KEY.';
        }

        if ($p === 'anthropic' && ! $deAnthropic) {
            return 'El panel está configurado para Anthropic, pero la clave cargada no tiene esa '
                 . 'forma (las de Anthropic empiezan con sk-ant-). En Railway: o poné '
                 . 'IA_PROVEEDOR en "openai", o cargá la clave de Anthropic en ANTHROPIC_API_KEY.';
        }

        return null;
    }

    /** El motivo real, sin adornos, para que se pueda arreglar. */
    private static function porQue($r): string
    {
        // Algunos proveedores devuelven la clave (enmascarada a medias) dentro
        // del propio mensaje de error. Acá no sale nada que se le parezca:
        // esto termina en una notificación en pantalla y en el registro.
        $m = trim((string) ($r->json('error.message') ?: $r->body()));
        $m = (string) preg_replace('/sk-[A-Za-z0-9_\-]{6,}/', 'sk-…', $m);

        if ($r->status() === 401) {
            return static::proveedor() . ' rechazó la clave. Revisá ' . static::variable()
                 . ' en Railway — casi siempre es que la borraron o la regeneraron del lado '
                 . 'de ellos, y hay que pegar una nueva. Contestaron: '
                 . mb_substr($m, 0, 120);
        }

        if ($r->status() === 404) {
            return 'El modelo "' . static::modelo() . '" no existe o esa cuenta no lo tiene '
                 . 'habilitado. Se cambia en Railway con '
                 . (static::proveedor() === 'openai' ? 'OPENAI_MODELO' : 'ANTHROPIC_MODELO') . '.';
        }

        if ($r->status() === 429) {
            return 'Se pasó del límite del proveedor, o la cuenta no tiene saldo.';
        }

        return mb_substr($m, 0, 200);
    }
}
