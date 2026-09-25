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
    /**
     * Deja los renglones en una forma de la que se pueda confiar.
     *
     * Lo que llega es JSON armado por un modelo: puede venir con claves de
     * más, con el precio escrito "$17.00" o "diecisiete", con cantidades en
     * texto. Acá se recorta a lo que se va a usar y se convierte a números.
     *
     * El precio en 0 NO es lo mismo que el precio 0: quiere decir que en la
     * conversación no se habló de precio para ese renglón, y más adelante se
     * completa con el del catálogo.
     */
    private static function limpiarLineas($crudo): array
    {
        if (! is_array($crudo)) return [];

        $lineas = [];

        foreach ($crudo as $l) {
            if (! is_array($l)) continue;

            $producto = trim((string) ($l['producto'] ?? ''));
            if ($producto === '') continue;

            $cantidad = (int) ($l['cantidad'] ?? 1);

            // Un precio con signo de pesos, coma de miles o espacios sigue
            // siendo un número: se le saca todo lo que no sea dígito o punto.
            $precio = (float) preg_replace('/[^\d.]/', '', (string) ($l['precio'] ?? ''));

            $lineas[] = [
                'producto' => $producto,
                'talla'    => trim((string) ($l['talla'] ?? '')),
                'cantidad' => max(1, $cantidad),
                'precio'   => $precio > 0 ? round($precio, 2) : 0.0,
            ];
        }

        // Tope de seguridad: un pedido real no tiene veinte renglones, y si el
        // modelo se desbocó no conviene llenar el formulario con basura.
        return array_slice($lineas, 0, 20);
    }

    public static function de(WaConversacion $conv, int $cuantos = 25): array
    {
        $vacio = array_fill_keys(static::CAMPOS, '');
        $vacio['lineas'] = [];

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

        // Los renglones con su precio, que es lo que se revisa uno por uno.
        $salida['lineas'] = static::limpiarLineas($datos['lineas'] ?? null);

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

    /**
     * El catálogo con los nombres tal como están cargados.
     *
     * Va SIN precios a propósito. Si viera los precios, sumaría — y esa suma
     * es justo lo que no queremos que haga: los precios cambian, los combos
     * tienen su regla, y un total mal calculado se cobra mal al entregar.
     *
     * Lo que sí necesita es escribir los nombres y las tallas exactamente como
     * están cargados, porque después otro los busca en la base para ponerles
     * precio. Un nombre aproximado no se encuentra.
     */
    private static function catalogo(): string
    {
        try {
            $filas = \App\Models\ProductSize::with('product')
                ->where('price', '>', 0)
                ->whereHas('product', fn ($q) => $q->where('active', true))
                ->get()
                ->groupBy(fn ($s) => trim((string) $s->product->name));

            $lineas = [];

            foreach ($filas as $nombre => $tallas) {
                if (trim((string) $nombre) === '') continue;

                $lineas[] = '- ' . $nombre . ' — tallas: '
                    . $tallas->pluck('size')->filter()->unique()->implode(', ');
            }

            return implode("\n", $lineas);
        } catch (\Throwable $e) {
            return '';
        }
    }

    private static function instrucciones(): string
    {
        $municipios = implode(', ', array_slice(Municipios::todos(), 0, 400));
        $catalogo   = static::catalogo();

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
          "total": "",
          "lineas": [
            { "producto": "", "talla": "", "cantidad": 1, "precio": 0 }
          ]
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

        - productos: lo más importante de todo, y tiene un formato obligatorio.

          UN RENGLÓN POR PRODUCTO, escrito exactamente así:

          CANTIDAD x NOMBRE DEL CATÁLOGO talla TALLA

          Por ejemplo:

          2 x Calzoncito Magic talla M
          1 x Pañales para recién nacidos talla RN

          El NOMBRE tiene que ser uno de esta lista, escrito igual — con las
          mismas palabras y las mismas tildes. No lo abrevies, no lo cambies,
          no inventes uno que no esté. Después estos renglones se buscan en la
          base de datos para ponerles precio, y un nombre aproximado no se
          encuentra.

          {$catalogo}

          Si el cliente pidió algo que no está en esa lista, escribí ese
          renglón tal como lo dijo él. Va a quedar sin precio y alguien lo va a
          ver — que es lo correcto; peor sería hacerlo desaparecer.

          Si en la conversación cambió de opinión, vale lo ÚLTIMO que se
          acordó, no lo primero que preguntó.

        - lineas: lo MISMO que "productos", pero partido en pedazos y con el
          precio de cada uno. Es lo que se revisa renglón por renglón, así que
          es el campo donde más cuidado hay que tener.

          Un objeto por renglón:

            producto — el nombre del catálogo, igual que arriba
            talla    — la talla, o "" si no aplica
            cantidad — cuántos, en número
            precio   — el precio UNITARIO acordado EN LA CONVERSACIÓN

          Sobre el precio, que es lo delicado:

          · Es el precio de UNO, no el del renglón. Si dijimos "el paquete
            \$17" y el cliente lleva 2, va 17 y cantidad 2. No pongas 34.
          · Sale de lo que se HABLÓ. Buscalo en lo que escribió NOSOTROS: ahí
            es donde se cotiza.
          · Si por ese producto no se dijo ningún precio, poné 0. El 0 quiere
            decir "no se habló de precio", y más adelante se completa con el
            del catálogo. NO es un producto regalado.
          · NO lo deduzcas dividiendo el total entre las cantidades. Eso da un
            número creíble y falso, y se le termina cobrando al cliente algo
            que nadie le dijo.
          · Si hubo promoción —"3 por \$25"— poné el precio unitario que sale
            de ella (25 ÷ 3 = 8.33) solo si el cliente se lleva justo esa
            cantidad. Si no, poné 0 y que lo resuelva el catálogo.

        - total: el monto que quedó ACORDADO en la conversación, tal como se
          dijo. Solo el número, sin el signo de pesos.

          Este campo es importante: es lo que el cliente espera pagar cuando
          le entreguen, así que tiene que salir de lo que se habló y de nada
          más.

          Buscalo en lo que escribió NOSOTROS — ahí es donde se cierra el
          precio— y quedate con el ÚLTIMO monto acordado, no con el primero
          que se mencionó al cotizar. Si el cliente agregó algo después y se
          rehízo la cuenta, vale la nueva.

          NO lo calcules vos. No sumes, no multipliques, no apliques
          promociones ni combos. Si el monto no está dicho en algún mensaje,
          va vacío — aunque puedas deducirlo. Un total deducido se ve igual
          que uno acordado, y se le cobra al cliente algo que nadie le dijo.

          Si nadie cerró un monto, vacío. Eso no es un dato faltante: es que
          todavía no se acordó.

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
