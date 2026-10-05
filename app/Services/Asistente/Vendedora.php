<?php

namespace App\Services\Asistente;

use App\Services\IA;

/**
 * La vendedora: la IA conversa con la clienta mirando cómo contestás VOS.
 *
 * En cada turno recibe el catálogo de hoy, los datos de la tienda, cómo va el
 * pedido, la conversación y los ejemplos más parecidos de tus chats reales.
 * Contesta con un JSON: el mensaje para la clienta y lo que entendió
 * (carrito, municipio, datos de envío, fotos a mandar, si hay que pasarte el
 * chat).
 *
 * El sistema revisa el mensaje antes de mandarlo: cada precio tiene que ser
 * uno de verdad (del catálogo, del envío o de un total bien sumado), nunca
 * dice "gratis", y los productos que elige tienen que existir. Si algo no
 * cuadra, se le pide que lo corrija una vez; si tampoco, se te pasa el chat.
 */
class Vendedora
{
    public const ACCIONES = ['conversar', 'mostrar_orden', 'pasar_a_wil'];

    /**
     * @param array $ctx catalogo, datos, tallas, estado, charla, nuevo, ejemplos,
     *                   permitidos (callable: carrito => montos válidos), max_paquetes
     * @return array|null  null si la IA no contestó; con 'error' si contestó mal dos veces
     */
    public static function preguntar(array $ctx): ?array
    {
        if (! IA::disponible()) return null;

        $modelo  = (string) config('asistente.modelo_ia', 'gpt-5-mini');
        $entrada = static::entrada($ctx);
        $ids     = array_map(fn ($c) => (string) $c['id'], $ctx['catalogo']);

        $error = null;

        for ($intento = 0; $intento < 2; $intento++) {
            $extra = $error ? "\n\nOJO: TU RESPUESTA ANTERIOR NO SE PUDO MANDAR porque {$error}. Escribila de nuevo corrigiendo eso." : '';

            $d = IA::json(IA::pedirConModelo($modelo, static::instrucciones(), $entrada . $extra));

            if (! is_array($d)) { $error = 'no era un JSON válido'; continue; }

            $r = static::limpiar($d, $ids, (int) ($ctx['max_paquetes'] ?? 10));

            $permitidos = is_callable($ctx['permitidos'] ?? null)
                ? ($ctx['permitidos'])($r['carrito'] ?? null)
                : [];

            $error = static::revisar($r['mensaje'], $permitidos);
            if ($error === null) return $r;
        }

        return ['error' => $error];
    }

    /** Lo que se le manda a la IA en cada turno. */
    public static function entrada(array $ctx): string
    {
        $cat = [];
        foreach ($ctx['catalogo'] as $c) {
            $cat[] = "id={$c['id']} | talla {$c['talla']}" . (! empty($c['peso']) ? " ({$c['peso']})" : '')
                . " | {$c['tipo']} | {$c['nombre']} | " . ($c['unidades'] ? $c['unidades'] . ' unidades | ' : '')
                . "\${$c['precio']}" . ($c['oferta'] ? " | oferta {$c['oferta']}" : '');
        }

        $ej = [];
        foreach ((array) ($ctx['ejemplos'] ?? []) as $e) {
            $ej[] = '—' . (! empty($e['antes']) ? "\n(antes la tienda había dicho: " . mb_substr(str_replace("\n", ' / ', $e['antes']), -160) . ')' : '')
                . "\nClienta: " . str_replace("\n", ' / ', $e['cliente'])
                . "\nWil: " . str_replace("\n", ' / ', $e['respuesta']);
        }

        $charla = [];
        foreach ((array) ($ctx['charla'] ?? []) as $m) {
            $charla[] = $m['quien'] . ': ' . mb_substr(str_replace("\n", ' / ', (string) $m['texto']), 0, 400);
        }

        return "DATOS DE LA TIENDA (lo único que podés afirmar):\n" . implode("\n", (array) $ctx['datos'])
            . "\n\nCATÁLOGO DE HOY (solo esto hay, con existencia; precios en dólares):\n" . ($cat ? implode("\n", $cat) : '(no hay nada disponible)')
            . "\n\nTALLAS POR PESO Y EQUIVALENCIAS:\n" . implode("\n", (array) ($ctx['tallas'] ?? []))
            . "\n\nCÓMO VA EL PEDIDO:\n" . json_encode($ctx['estado'] ?? [], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
            . "\n\nEJEMPLOS DE CÓMO CONTESTA WIL EN CHATS PARECIDOS (copiá su manera de vender y su tono; los precios, existencias y fechas de los ejemplos pueden ser VIEJOS: nunca los uses):\n"
            . ($ej ? implode("\n", $ej) : '(sin ejemplos)')
            . "\n\nCONVERSACIÓN DE AHORA (lo más reciente al final):\n" . ($charla ? implode("\n", $charla) : '(recién empieza)')
            . "\n\nLO QUE ACABA DE ESCRIBIR LA CLIENTA:\n" . $ctx['nuevo'];
    }

    /** Solo se queda lo que tiene sentido. */
    public static function limpiar(array $d, array $ids, int $maxPaquetes = 10): array
    {
        $str = fn ($v, $max = 200) => is_scalar($v) && trim((string) $v) !== '' ? mb_substr(trim((string) $v), 0, $max) : null;

        $carrito = null;
        if (array_key_exists('carrito', $d) && is_array($d['carrito'])) {
            $carrito = [];
            foreach ($d['carrito'] as $it) {
                if (! is_array($it)) continue;
                $id = (string) ($it['id'] ?? '');
                $n  = (int) ($it['cantidad'] ?? 0);
                if (! in_array($id, $ids, true) || $n < 1) continue;
                $carrito[$id] = min($maxPaquetes, ($carrito[$id] ?? 0) + $n);
            }
            $carrito = array_map(fn ($id, $n) => ['id' => (string) $id, 'cantidad' => $n], array_keys($carrito), $carrito);
        }

        $fotos = [];
        foreach ((array) ($d['fotos'] ?? []) as $id) {
            $id = (string) $id;
            if (in_array($id, $ids, true) && ! in_array($id, $fotos, true)) $fotos[] = $id;
        }

        $accion = in_array($d['accion'] ?? null, static::ACCIONES, true) ? $d['accion'] : 'conversar';

        return [
            'mensaje'      => trim((string) ($d['mensaje'] ?? '')),
            'fotos'        => array_slice($fotos, 0, max(1, (int) config('asistente.max_fotos', 4))),
            'carrito'      => $carrito,
            'municipio'    => $str($d['municipio'] ?? null, 80),
            'departamento' => $str($d['departamento'] ?? null, 40),
            'colonia'      => $str($d['colonia'] ?? null),
            'nombre'       => $str($d['nombre'] ?? null, 120),
            'direccion'    => $str($d['direccion'] ?? null, 250),
            'telefono'     => $str($d['telefono'] ?? null, 30),
            'accion'       => $accion,
            'motivo'       => $str($d['motivo'] ?? null, 150) ?? '',
        ];
    }

    /**
     * ¿Se puede mandar este mensaje? null si sí; si no, qué tiene mal (se le
     * dice a la IA para que lo corrija).
     */
    public static function revisar(string $mensaje, array $permitidos): ?string
    {
        if (mb_strlen($mensaje) > 1400) return 'el mensaje es demasiado largo (máximo unas 8 líneas)';

        $n = Entender::normalizar($mensaje);

        if (preg_match('/\b(gratis|gratuito|gratuita|sin costo|sin cargo|free)\b/', $n)) {
            return 'dijiste "gratis" o "sin costo", y eso nunca se dice';
        }

        if (preg_match('~https?://|www\.~i', $mensaje)) return 'pusiste un enlace, y no se mandan enlaces';

        if (preg_match('/\[(nombre|tel[eé]fono|correo|enlace|tapado|foto|tarjeta)/iu', $mensaje)) {
            return 'copiaste una marca de los ejemplos ([nombre], [foto]…) en vez de escribir el mensaje';
        }

        foreach (static::montos($mensaje) as $m) {
            $ok = false;
            foreach ($permitidos as $p) {
                if (abs((float) $p - $m) < 0.011) { $ok = true; break; }
            }
            if (! $ok) {
                return 'pusiste el monto $' . number_format($m, 2) . ', que no es ningún precio del catálogo, del envío ni un total bien sumado';
            }
        }

        return null;
    }

    /** Los montos de dinero que aparecen en un texto: "$20", "$ 2.50", "20 dólares". */
    public static function montos(string $t): array
    {
        $out = [];

        preg_match_all('/\$\s*(\d{1,4}(?:[.,]\d{1,2})?)/u', $t, $a);
        preg_match_all('/(\d{1,4}(?:[.,]\d{1,2})?)\s*(?:\$|d[oó]lares?\b|usd\b)/iu', $t, $b);

        foreach (array_merge($a[1], $b[1]) as $v) {
            $out[] = (float) str_replace(',', '.', $v);
        }

        return array_values(array_unique($out, SORT_REGULAR));
    }

    public static function instrucciones(): string
    {
        return <<<'TXT'
Atendés el WhatsApp de Baby-Confort, una tienda de pañales Aiwibi en El Salvador. Escribís como Wil, el dueño: en los EJEMPLOS ves cómo contesta él en chats parecidos. Copiá su manera: frases cortas, cálidas, trato de usted, "con gusto", pocos emojis, una pregunta a la vez. No suenes a robot ni a folleto. Escribí sin faltas: los ejemplos tienen errores de tipeo ("entnedido") que no se copian.

CÓMO CONTESTAR
- Un solo mensaje de WhatsApp, corto (1 a 5 líneas). Si ya se saludó en la conversación, no vuelvas a saludar.
- Primero contestá lo que preguntó; después, si hace falta, una sola pregunta para avanzar la venta.
- Solo afirmás lo que está en DATOS DE LA TIENDA, en el CATÁLOGO o en CÓMO VA EL PEDIDO. Los ejemplos son para el estilo: sus precios, existencias ("agotado", "solo calzoncito"), fechas y promociones pueden ser viejos y NO se usan.
- Precios: solo los del catálogo (o la oferta tal cual está escrita). Nunca inventes descuentos ni rebajas. El envío, solo el de DATOS. Nunca digas "gratis" ni "sin costo".
- Si te preguntan algo que no está en los datos, no lo inventes: pasale el chat a Wil.

TALLAS
- Recomendá por el peso con TALLAS POR PESO. Si el peso cae en dos tallas, ofrecé las dos y decí que la más grande le dura más. Si está en el tope de una talla, sugerí la siguiente. Si no sabe el peso, preguntalo (o qué talla usa ahora).
- Si la clienta usa otro nombre de talla (G, XG, talla 4, grande…), decile a cuál de las nuestras equivale.
- Solo ofrecé lo que está en el CATÁLOGO. Si pide algo que no hay, decile que en este momento no hay y ofrecé lo más parecido que sí hay. Si quiere que le avisen, "apenas entren le avisamos".

FOTOS
- Para mostrar productos, poné sus ids en "fotos" (máximo 4). El sistema manda cada foto con la talla, el tipo, las unidades y el precio, y DESPUÉS tu mensaje: no repitas esos datos, solo preguntá cuál le gusta y cuántos paquetes.
- No vuelvas a mandar fotos que ya están en "fotos_enviadas", salvo que la clienta lo pida.

EL PEDIDO
- "carrito" es el pedido COMPLETO después de este mensaje (ids del catálogo y cantidad de paquetes). Ponelo solo cuando la clienta elige, suma, cambia o quita algo; si no cambió nada, null.
- Antes de pasar a los datos de envío, preguntá una vez si le agregamos algo más.
- Envío: si pregunta cuánto cuesta y no se sabe el municipio, contestá corto y preguntale para qué municipio es. Cuando lo diga, ponelo en "municipio" (y "departamento" si lo dijo).
- SAN MIGUEL (municipio San Miguel): la entrega la hace la tienda y el costo depende de la colonia. Preguntá en qué colonia o lugar es la entrega; cuando lo diga, ponelo en "colonia" y la acción "pasar_a_wil". Nunca digas un precio de envío para San Miguel.
- Para cerrar: con el pedido decidido y el municipio, pedí nombre y apellido, la dirección exacta (colonia, calle o pasaje, número de casa y un punto de referencia) y el teléfono ("¿le llamamos a este mismo número?"; si dice que sí, poné "telefono": "este"). Podés pedir varios datos juntos, como hace Wil.
- Cuando ya estén el carrito, el municipio, el nombre, la dirección y el teléfono, poné "accion": "mostrar_orden" y un mensaje cortito ("¡Perfecto! Le comparto su orden para que la revise 😊"). El sistema arma la orden con el total y los botones para confirmar.
- Si la orden ya se mostró (orden_mostrada) y quiere cambiar algo, actualizá lo que cambió y otra vez "mostrar_orden".

PASARLE EL CHAT A WIL ("accion": "pasar_a_wil", con el motivo en pocas palabras)
- Reclamos o problemas con un pedido, preguntas por un pedido ya enviado, comprobantes de pago, pedidos por mayor, rebajas, otros productos (pañales de adulto, toallitas…), si está molesta, si pide hablar con una persona, o si no sabés la respuesta.
- En ese caso el mensaje puede ir vacío (el sistema le avisa que la atiende un asesor).

Nunca pidas datos de tarjeta ni contraseñas. Nunca prometas fechas ni cosas que no estén en los datos.

Devolvé SOLO este JSON, sin texto alrededor:
{
  "mensaje": "lo que se le escribe a la clienta",
  "fotos": [],
  "carrito": null,
  "municipio": null,
  "departamento": null,
  "colonia": null,
  "nombre": null,
  "direccion": null,
  "telefono": null,
  "accion": "conversar",
  "motivo": ""
}
En municipio, colonia, nombre, dirección y teléfono poné solo lo que la clienta dijo en esta conversación (null si no lo dijo o si ya está en CÓMO VA EL PEDIDO y no cambió).
TXT;
    }
}
