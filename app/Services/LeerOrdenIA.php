<?php

namespace App\Services;

use App\Models\WaConversacion;
use App\Models\WaMensaje;

/**
 * Saca los datos de un pedido leyendo la conversación entera.
 *
 * Entra donde el lector de reglas se queda corto. Ese busca "Nombre:",
 * "Dirección:", "Municipio:" y se queda con lo que sigue a los dos puntos:
 * impecable cuando el cliente usa la plantilla, inútil cuando escribe suelto
 * —"soy María, mándemelo a la casa blanca frente a la cancha"— o cuando manda
 * los datos en cuatro mensajes seguidos, que es lo normal.
 *
 * Ahí está la diferencia de fondo: el de reglas mira UN mensaje; este mira la
 * conversación. El nombre que dio al principio y la dirección que mandó tres
 * mensajes después terminan en la misma orden.
 *
 * LO QUE NO HACE, y es lo que más importa:
 *
 *  · No inventa. Lo que no está escrito vuelve vacío. Una dirección inventada
 *    no se ve mal — se ve bien y el paquete llega a la casa equivocada.
 *  · No decide el departamento. Eso sale de la tabla de municipios, como
 *    siempre. Si adivinara "Guazapa, La Libertad", el paquete cruza el país.
 *  · No guarda nada. Llena el formulario y ahí se revisa.
 */
class LeerOrdenIA
{
    /** Los mismos campos que devuelve el lector de reglas. */
    public const CAMPOS = ['nombre', 'telefono', 'municipio', 'direccion', 'productos', 'total'];

    public static function disponible(): bool
    {
        return IA::disponible();
    }

    /**
     * Lee la conversación y devuelve lo que encuentre.
     *
     * Siempre devuelve las claves de CAMPOS, con cadena vacía las que no. Así
     * quien llama no tiene que preguntar si existen.
     */
    public static function de(WaConversacion $conv, int $cuantos = 25): array
    {
        $vacio = array_fill_keys(static::CAMPOS, '');

        if (! static::disponible()) return $vacio;

        $charla = static::transcripcion($conv, $cuantos);
        if (trim($charla) === '') return $vacio;

        // Temperatura en cero: esto es extraer, no redactar. Acá no queremos
        // ni una pizca de variación — el mismo texto tiene que dar siempre el
        // mismo resultado, si no no hay forma de confiar ni de probarlo.
        $datos = IA::json(IA::pedir(static::instrucciones(), $charla, 0.0));

        if (! is_array($datos)) return $vacio;

        $salida = $vacio;

        foreach (static::CAMPOS as $c) {
            $v = $datos[$c] ?? null;

            // Solo texto plano. Si contestó un objeto o una lista, se descarta:
            // preferimos un campo vacío que uno con basura adentro.
            if (is_string($v) || is_numeric($v)) {
                $salida[$c] = trim((string) $v);
            }
        }

        // "null", "no dice", "N/A" y parientes son formas de decir vacío que a
        // veces se escapan igual. Si pasaran, quedarían escritas en la guía.
        foreach ($salida as $c => $v) {
            if (in_array(mb_strtolower($v), ['null', 'n/a', 'na', 'no dice', 'no especifica', '-', '—'], true)) {
                $salida[$c] = '';
            }
        }

        return $salida;
    }

    /**
     * La conversación en texto, del más viejo al más nuevo.
     *
     * En orden cronológico a propósito: "mándemelo a la misma dirección" solo
     * se entiende sabiendo qué se dijo antes.
     */
    private static function transcripcion(WaConversacion $conv, int $cuantos): string
    {
        try {
            $mensajes = WaMensaje::where('conversacion_id', $conv->id)
                ->orderByDesc('id')
                ->limit($cuantos)
                ->get()
                ->reverse();
        } catch (\Throwable $e) {
            return '';
        }

        $lineas = [];

        foreach ($mensajes as $m) {
            $t = trim((string) $m->texto);
            if ($t === '') continue;

            $lineas[] = ($m->esDelCliente() ? 'CLIENTE' : 'NOSOTROS') . ': ' . $t;
        }

        // El teléfono de la conversación va aparte y marcado como tal. Es el
        // dato más confiable que hay —lo da WhatsApp, no lo escribió nadie—
        // pero no debe pisar a otro que el cliente haya dado para que reciba
        // una tercera persona.
        $cabecera = "TELÉFONO DESDE EL QUE ESCRIBE: {$conv->telefono}\n\n";

        return $cabecera . implode("\n", $lineas);
    }

    private static function instrucciones(): string
    {
        $municipios = implode(', ', array_slice(Municipios::todos(), 0, 400));

        return <<<TXT
        Sos un lector de pedidos de una tienda de pañales en El Salvador. Te
        paso una conversación de WhatsApp y sacás los datos del envío.

        NO redactás, NO corregís, NO completás. Extraés.

        Contestá ÚNICAMENTE con un objeto JSON con estas claves exactas:

        {
          "nombre": "",
          "telefono": "",
          "municipio": "",
          "direccion": "",
          "productos": "",
          "total": ""
        }

        LA REGLA QUE MANDA SOBRE TODAS

        Lo que no esté escrito en la conversación va como cadena vacía "".

        No adivines, no deduzcas, no rellenes con lo que suele ponerse. Un dato
        inventado no se nota: se ve bien y manda el paquete a la casa
        equivocada. Un campo vacío se nota enseguida y alguien lo escribe.

        Nunca pongas "null", "N/A", "no dice" ni nada parecido. Va "".

        CAMPO POR CAMPO

        - nombre: el de quien RECIBE el paquete. Solo el nombre, sin el
          teléfono pegado. Si el cliente nunca lo dijo, vacío. El nombre del
          perfil de WhatsApp no cuenta: no lo tenés y no lo inventes.

        - telefono: ocho dígitos. Si el cliente dio un número distinto para que
          llamen al entregar —porque el paquete va para otra persona— poné ESE.
          Si no dio ninguno, poné el de "TELÉFONO DESDE EL QUE ESCRIBE".

        - municipio: solo el municipio, sin el departamento. Tiene que ser uno
          de esta lista; si lo que dijo el cliente no calza con ninguno, vacío.
          NO pongas el departamento en ningún campo: ese lo resuelve el sistema
          a partir del municipio, y si lo adivinás mal el paquete cruza el país.

          {$municipios}

        - direccion: la dirección tal como la dio, sin el municipio ni el
          departamento al final. Referencias incluidas ("frente a la cancha",
          "casa blanca de portón negro"): es lo que usa el repartidor.

        - productos: qué lleva, con las cantidades, tal como quedó acordado.
          Si en la conversación cambió de opinión, vale lo ÚLTIMO que se
          acordó, no lo primero que preguntó.

        - total: cuánto se cobra al entregar. Solo el número, sin el signo de
          pesos. Si no se habló de un monto cerrado, vacío. No lo calcules vos
          sumando precios: si nadie lo dijo, no está acordado.

        CÓMO LEER LA CONVERSACIÓN

        Los datos vienen repartidos en varios mensajes, y eso es lo normal. El
        nombre en uno, la dirección tres mensajes después, el monto al final.
        Juntalos.

        Si un dato se dio dos veces con valores distintos, vale el MÁS NUEVO:
        corrigió.

        Lo que escribió NOSOTROS sirve de contexto —ahí van los precios y las
        confirmaciones— pero el pedido es lo que pidió el CLIENTE.

        Si la conversación es solo preguntas de precios y nunca hubo un pedido,
        devolvé todos los campos vacíos. Es una respuesta correcta.

        Contestá el JSON y nada más. Sin explicaciones, sin comentarios, sin
        envolverlo en bloques de código.
        TXT;
    }
}
