<?php

namespace App\Services\Asistente;

use App\Services\IA;

/**
 * La IA lee lo que escribió la clienta y lo traduce a un pedido ordenado.
 *
 * Lo que NO hace: poner precios, inventar productos ni contestarle a la
 * clienta con sus palabras. Recibe el catálogo de ese momento y solo puede
 * elegir presentaciones de ahí (por su número de id); el sistema valida cada
 * id, arma el carrito y calcula precios, envío y total con tus datos.
 *
 * Si la IA no está configurada, tarda o contesta algo que no se entiende,
 * devuelve null y el asistente sigue con las reglas de siempre.
 */
class InterpretarIA
{
    public static function disponible(): bool
    {
        return (bool) config('asistente.usar_ia', true) && IA::disponible();
    }

    /**
     * @param array  $catalogo  [['id','talla','tipo','nombre','unidades','precio','oferta'], …]
     * @param array  $estado    lo que ya se sabe: carrito, opciones mostradas, talla que mira…
     * @param array  $charla    últimos mensajes: [['quien' => 'CLIENTE'|'TIENDA', 'texto' => …], …]
     * @param string $nuevo     lo que acaba de escribir
     */
    public static function de(array $catalogo, array $estado, array $charla, string $nuevo): ?array
    {
        if (! static::disponible() || trim($nuevo) === '') return null;

        $lineasCat = [];
        foreach ($catalogo as $c) {
            $lineasCat[] = "id={$c['id']} | talla {$c['talla']} | {$c['tipo']} | {$c['nombre']} | {$c['unidades']} u | \${$c['precio']}"
                . ($c['oferta'] ? " | oferta {$c['oferta']}" : '');
        }

        $pesos = [];
        foreach ((array) config('tallas_peso', []) as $t => $r) $pesos[] = "{$t}: {$r}";
        foreach ((array) config('asistente.tallas_numericas', []) as $num => $t) $pesos[] = "\"talla {$num}\" (numeración de otras marcas) = {$t}";

        $lineasCharla = [];
        foreach (array_slice($charla, -10) as $m) {
            $lineasCharla[] = $m['quien'] . ': ' . mb_substr(str_replace("\n", ' / ', (string) $m['texto']), 0, 280);
        }

        $entrada = "CATÁLOGO DISPONIBLE HOY (solo esto existe):\n" . implode("\n", $lineasCat)
            . "\n\nTALLAS POR PESO:\n" . implode("\n", $pesos)
            . "\n\nESTADO DEL PEDIDO:\n" . json_encode($estado, JSON_UNESCAPED_UNICODE)
            . "\n\nCONVERSACIÓN RECIENTE:\n" . implode("\n", $lineasCharla)
            . "\n\nMENSAJE NUEVO DE LA CLIENTA:\n" . $nuevo;

        $modelo = (string) config('asistente.modelo_ia', 'gpt-5-mini');

        $respuesta = IA::pedirConModelo($modelo, static::instrucciones(), $entrada);
        $datos = IA::json($respuesta);

        if (! is_array($datos)) return null;

        return static::limpiar($datos, $catalogo);
    }

    /** Solo se queda lo que tiene sentido: ids del catálogo, cantidades razonables. */
    private static function limpiar(array $d, array $catalogo): array
    {
        $ids = array_map(fn ($c) => (string) $c['id'], $catalogo);
        $tallasOk = array_map('mb_strtoupper', (array) config('asistente.tallas', []));

        $items = [];
        foreach ((array) ($d['items'] ?? []) as $it) {
            if (! is_array($it)) continue;
            $id = (string) ($it['id'] ?? '');
            if (! in_array($id, $ids, true)) continue;

            $cant = $it['cantidad'] ?? null;
            $cant = is_numeric($cant) ? max(1, (int) $cant) : null;

            $items[] = ['id' => $id, 'cantidad' => $cant];
        }

        $quitar = array_values(array_filter(
            array_map('strval', (array) ($d['quitar'] ?? [])),
            fn ($id) => in_array($id, $ids, true)
        ));

        $tallas = array_values(array_filter(
            array_map(fn ($t) => mb_strtoupper(trim((string) $t)), (array) ($d['tallas'] ?? [])),
            fn ($t) => in_array($t, $tallasOk, true)
        ));

        $tipo = in_array($d['tipo'] ?? null, ['cinta', 'calzoncito', 'ambos'], true) ? $d['tipo'] : null;

        $peso = null;
        if (is_array($d['peso'] ?? null) && is_numeric($d['peso']['valor'] ?? null)) {
            $u = strtolower((string) ($d['peso']['unidad'] ?? ''));
            $peso = ['valor' => (float) $d['peso']['valor'], 'unidad' => in_array($u, ['lb', 'kg'], true) ? $u : null];
        }

        // La pregunta de aclaración no puede llevar precios: los precios los
        // pone el sistema. Si trae un "$", se descarta.
        $aclarar = trim((string) ($d['aclaracion'] ?? ''));
        if ($aclarar !== '' && (str_contains($aclarar, '$') || mb_strlen($aclarar) > 300)) $aclarar = '';

        return [
            'items'      => $items,
            'reemplazar' => (bool) ($d['reemplazar'] ?? false),
            'quitar'     => $quitar,
            'listo'      => (bool) ($d['listo'] ?? false),
            'tallas'     => $tallas,
            'tipo'       => $tipo,
            'peso'       => $peso,
            'municipio'  => trim((string) ($d['municipio'] ?? '')) ?: null,
            'aclaracion' => $aclarar ?: null,
        ];
    }

    private static function instrucciones(): string
    {
        return <<<TXT
Sos el lector de pedidos de Baby-Confort, una tienda de pañales en El Salvador que vende por WhatsApp.
Tu ÚNICO trabajo: leer el mensaje nuevo de la clienta (con la conversación reciente y el estado del pedido como contexto) y devolver un JSON con lo que quiere. No le escribís a la clienta.

Devolvé SOLO este JSON, sin texto alrededor:
{
  "items": [{"id": "123", "cantidad": 2}],
  "reemplazar": false,
  "quitar": [],
  "listo": false,
  "tallas": [],
  "tipo": null,
  "peso": null,
  "municipio": null,
  "aclaracion": null
}

Qué significa cada campo:
- items: presentaciones que quiere COMPRAR o sumar, con su id del CATÁLOGO. "cantidad" en paquetes; si no dijo cuántos, null.
  · "opción 2", "la segunda", "esa", "la de noche" se refieren a las opciones mostradas (estado.mostradas, en orden). Usá esos ids.
  · Si nombra talla y tipo y hay UNA sola presentación que calza, usala. Si hay varias que calzan y no se sabe cuál, no adivines: dejá items vacío y escribí una aclaración.
  · Si solo pregunta o quiere VER, items va vacío.
- reemplazar: true si dice "mejor", "cámbieme", "en vez de" (lo nuevo reemplaza lo que ya lleva).
- quitar: ids que quiere sacar del pedido ("quíteme el M").
- listo: true si dice que ya no quiere nada más ("es todo", "así está bien", "nada más") y ya tiene algo en el pedido.
- tallas: tallas que quiere VER o de las que habla (usá exactamente las del catálogo: RN, S, M, L, XL, XXL, XXXL, 4 A 7 AÑOS, 8 A 14 AÑOS). "8 a 15" es "8 A 14 AÑOS". "extra grande"/"XG" es XL; "XXG" es XXL.
- tipo: "cinta" (de pegar, de broche, normales), "calzoncito" (pants, de subir) o "ambos"; null si no dijo.
- peso: si dice el peso del bebé: {"valor": 22, "unidad": "lb"} o "kg". Si no dice la unidad, unidad null.
- municipio: si nombra el lugar de entrega (municipio o ciudad), el texto tal cual; si no, null.
- aclaracion: SOLO si hace falta preguntar algo para no adivinar (por ejemplo, "¿El de noche lo quiere de cinta o calzoncito?"). Corta, amable, de usted, sin precios ni números de dinero. Si no hace falta, null.

Reglas:
- Nunca inventes ids ni productos. Si lo que pide no está en el catálogo, no lo pongas en items (podés poner la talla en "tallas" para que el sistema le diga que no hay).
- Saludos, gracias, preguntas de precio, de envío o de pago: dejá todo vacío/null.
- Si la conversación muestra que ya eligió algo antes, no lo repitas en items salvo que cambie la cantidad.
TXT;
    }
}
