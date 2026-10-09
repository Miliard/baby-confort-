<?php

namespace App\Services\Asistente;

use App\Services\IA;

/**
 * El lector del guion: la IA lee lo que escribió la clienta y devuelve lo
 * que entendió (talla, peso, producto, cantidad, municipio, datos, si dijo
 * que sí…). Si además hizo una pregunta suelta ("¿son calientes?"), la
 * contesta en una o dos líneas con tu manera de hablar, mirando los ejemplos
 * de tus chats.
 *
 * Lo que NO hace: decidir qué se pregunta después ni escribir los mensajes
 * del pedido. Eso es el guion que ensayaste, y lo escribe el sistema con los
 * datos reales (precios, envío, total, orden).
 *
 * La respuesta suelta se revisa antes de mandarla: sin precios inventados,
 * sin "gratis", sin enlaces. Si no pasa, se descarta.
 */
class Vendedora
{
    /**
     * @param array $ctx catalogo, datos, tallas, estado, charla, nuevo, ejemplos, permitidos (montos válidos)
     * @return array|null  lo que entendió, o null si la IA no contestó
     */
    public static function leer(array $ctx): ?array
    {
        if (! IA::disponible()) return null;

        $modelo = (string) config('asistente.modelo_ia', 'gpt-5-mini');
        $d = IA::json(IA::pedirConModelo($modelo, static::instrucciones(), static::entrada($ctx)));

        if (! is_array($d)) return null;

        $r = static::limpiar($d, array_map(fn ($c) => (string) $c['id'], $ctx['catalogo']));

        // La respuesta suelta, revisada: si trae un precio que no existe o un
        // "gratis", no se manda.
        if ($r['respuesta'] !== null && static::revisar($r['respuesta'], (array) ($ctx['permitidos'] ?? [])) !== null) {
            $r['respuesta'] = null;
        }

        return $r;
    }

    public static function entrada(array $ctx): string
    {
        $cat = [];
        foreach ($ctx['catalogo'] as $c) {
            $cat[] = "id={$c['id']} | talla {$c['talla']} | {$c['tipo']} | {$c['nombre']}"
                . ($c['unidades'] ? " | {$c['unidades']} unidades" : '') . " | \${$c['precio']}"
                . ($c['oferta'] ? " | oferta {$c['oferta']}" : '');
        }

        $ej = [];
        foreach ((array) ($ctx['ejemplos'] ?? []) as $e) {
            $ej[] = '— Clienta: ' . str_replace("\n", ' / ', $e['cliente']) . "\n  Wil: " . str_replace("\n", ' / ', $e['respuesta']);
        }

        $charla = [];
        foreach ((array) ($ctx['charla'] ?? []) as $m) {
            $charla[] = $m['quien'] . ': ' . mb_substr(str_replace("\n", ' / ', (string) $m['texto']), 0, 300);
        }

        return "DATOS DE LA TIENDA:\n" . implode("\n", (array) $ctx['datos'])
            . "\n\nCATÁLOGO DE HOY (con existencia):\n" . ($cat ? implode("\n", $cat) : '(nada)')
            . "\n\nTALLAS Y EQUIVALENCIAS:\n" . implode("\n", (array) ($ctx['tallas'] ?? []))
            . "\n\nLO QUE YA SE SABE DEL PEDIDO (y lo último que se le preguntó):\n" . json_encode($ctx['estado'] ?? [], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
            . "\n\nEJEMPLOS DE CÓMO CONTESTA WIL (solo para el tono de \"respuesta\"; sus precios y existencias pueden ser viejos):\n" . ($ej ? implode("\n", $ej) : '(sin ejemplos)')
            . "\n\nCONVERSACIÓN (lo más reciente al final):\n" . ($charla ? implode("\n", $charla) : '(recién empieza)')
            . "\n\nLO QUE ACABA DE ESCRIBIR LA CLIENTA:\n" . $ctx['nuevo'];
    }

    /** Solo se queda lo que tiene sentido. */
    public static function limpiar(array $d, array $ids): array
    {
        $str = fn ($v, $max = 200) => is_scalar($v) && trim((string) $v) !== '' && ! in_array(mb_strtolower(trim((string) $v)), ['null', 'ninguno'], true)
            ? mb_substr(trim((string) $v), 0, $max) : null;

        $tallasOk = array_map('mb_strtoupper', (array) config('asistente.tallas', []));
        $tallas = array_values(array_unique(array_filter(
            array_map(fn ($t) => mb_strtoupper(trim((string) $t)), (array) ($d['tallas'] ?? [])),
            fn ($t) => in_array($t, $tallasOk, true)
        )));

        $items = [];
        foreach ((array) ($d['items'] ?? []) as $it) {
            if (! is_array($it)) continue;
            $id = (string) ($it['id'] ?? '');
            if (! in_array($id, $ids, true)) continue;
            $n = $it['cantidad'] ?? null;
            $items[] = ['id' => $id, 'cantidad' => is_numeric($n) && (int) $n > 0 ? (int) $n : null];
        }

        $peso = null;
        if (is_array($d['peso'] ?? null) && is_numeric($d['peso']['valor'] ?? null) && (float) $d['peso']['valor'] > 0) {
            $u = strtolower((string) ($d['peso']['unidad'] ?? ''));
            $peso = ['valor' => (float) $d['peso']['valor'], 'unidad' => in_array($u, ['lb', 'kg'], true) ? $u : null];
        }

        $si = $d['acepta'] ?? null;

        return [
            'tallas'         => $tallas,
            'peso'           => $peso,
            'edad_meses'     => is_numeric($d['edad_meses'] ?? null) ? max(0, (int) $d['edad_meses']) : null,
            'tipo'           => in_array($d['tipo'] ?? null, ['cinta', 'calzoncito', 'ambos'], true) ? $d['tipo'] : null,
            'items'          => $items,
            'cantidad'       => is_numeric($d['cantidad'] ?? null) && (int) $d['cantidad'] > 0 ? (int) $d['cantidad'] : null,
            'quitar'         => array_values(array_filter(array_map('strval', (array) ($d['quitar'] ?? [])), fn ($id) => in_array($id, $ids, true))),
            'reemplazar'     => (bool) ($d['reemplazar'] ?? false),
            'municipio'      => $str($d['municipio'] ?? null, 80),
            'nombre'         => $str($d['nombre'] ?? null, 120),
            'direccion'      => $str($d['direccion'] ?? null, 250),
            'telefono'       => $str($d['telefono'] ?? null, 30),
            'acepta'         => is_bool($si) ? $si : null,
            'corregir'       => (bool) ($d['corregir'] ?? false),
            'pregunta_envio' => (bool) ($d['pregunta_envio'] ?? false),
            'respuesta'      => $str($d['respuesta'] ?? null, 500),
            'pasar_a_wil'    => (bool) ($d['pasar_a_wil'] ?? false),
            'motivo'         => $str($d['motivo'] ?? null, 150) ?? '',
        ];
    }

    /** null si el texto se puede mandar; si no, qué tiene mal. */
    public static function revisar(string $texto, array $permitidos): ?string
    {
        if (mb_strlen($texto) > 600) return 'demasiado largo';

        $n = Entender::normalizar($texto);
        if (preg_match('/\b(gratis|gratuito|gratuita|sin costo|sin cargo|free)\b/', $n)) return 'dice gratis';
        if (preg_match('~https?://|www\.~i', $texto)) return 'trae un enlace';
        if (preg_match('/\[(nombre|tel[eé]fono|correo|enlace|tapado|foto|tarjeta)/iu', $texto)) return 'copió una marca de los ejemplos';

        foreach (static::montos($texto) as $m) {
            $ok = false;
            foreach ($permitidos as $p) {
                if (abs((float) $p - $m) < 0.011) { $ok = true; break; }
            }
            if (! $ok) return 'monto inventado $' . number_format($m, 2);
        }

        return null;
    }

    /** Los montos de dinero de un texto: "$20", "$ 2.50", "20 dólares". */
    public static function montos(string $t): array
    {
        preg_match_all('/\$\s*(\d{1,4}(?:[.,]\d{1,2})?)/u', $t, $a);
        preg_match_all('/(\d{1,4}(?:[.,]\d{1,2})?)\s*(?:\$|d[oó]lares?\b|usd\b)/iu', $t, $b);

        $out = [];
        foreach (array_merge($a[1], $b[1]) as $v) $out[] = (float) str_replace(',', '.', $v);

        return array_values(array_unique($out, SORT_REGULAR));
    }

    public static function instrucciones(): string
    {
        return <<<'TXT'
Leés los mensajes de WhatsApp de las clientas de Baby-Confort (pañales Aiwibi, El Salvador) y devolvés lo que entendiste en un JSON. El sistema decide qué contestar con un guion fijo; vos solo entendés, y si la clienta hizo una pregunta suelta, la contestás corto.

Devolvé SOLO este JSON:
{
  "tallas": [],
  "peso": null,
  "edad_meses": null,
  "tipo": null,
  "items": [],
  "cantidad": null,
  "quitar": [],
  "reemplazar": false,
  "municipio": null,
  "nombre": null,
  "direccion": null,
  "telefono": null,
  "acepta": null,
  "corregir": false,
  "pregunta_envio": false,
  "respuesta": null,
  "pasar_a_wil": false,
  "motivo": ""
}

QUÉ VA EN CADA CAMPO (solo lo que dijo en ESTE mensaje; si no lo dijo, vacío o null)
- tallas: las tallas que pide o elige, con NUESTROS nombres: RN, S, M, L, XL, XXL, XXXL, 4 A 7 AÑOS, 8 A 14 AÑOS. Traducí: G/grande/talla 4 = L; XG/extra grande/talla 5 = XL; XXG/talla 6 = XXL; XXXG/talla 7 = XXXL; P/pequeña/talla 2 = S; talla 3/mediana = M; talla 0, 1, recién nacido = RN. Si responde a "¿le muestro la L, la XL o las dos?" con "las dos", poné las dos.
- peso: {"valor": 36, "unidad": "lb"} o "kg". Si no dijo la unidad, unidad null.
- edad_meses: la edad del bebé en meses ("2 años" = 24, "8 meses" = 8).
- tipo: "cinta" (de pegar, de broche, normal), "calzoncito" (pants, de subir) o "ambos".
- items: productos del CATÁLOGO que elige, con su id. "la opción 2", "el de noche", "ese", "el Magic" se resuelven con fotos_enviadas y la talla de LO QUE YA SE SABE. "cantidad" en paquetes, o null si no dijo cuántos. Si no se sabe cuál es, no adivines: items vacío.
- cantidad: si SOLO dice un número de paquetes ("2", "dos paquetes", "deme 3") para el producto que ya eligió.
- quitar / reemplazar: si quita productos o dice "mejor", "cámbieme", "en vez de".
- municipio: el municipio o ciudad de entrega tal como lo escribió (aunque esté mal escrito). Si en la dirección nombra un lugar, eso NO va acá: va en direccion.
- nombre: nombre y apellido de quien recibe. direccion: la dirección tal cual (colonia, calle, casa, referencia). telefono: un número de teléfono que dé, o "este" si dice que al mismo número.
- acepta: true si dice que sí / está bien / ok / correcto a lo último que se le preguntó (ver ultima_pregunta); false si dice que no. null si no contesta eso.
- corregir: true si quiere corregir algo de la orden.
- pregunta_envio: true si pregunta cuánto cuesta el envío, si hacen envíos o si llegan a su zona.
- respuesta: SOLO si hizo una pregunta que no es del pedido (si son calientes, la marca, cómo se paga, dónde están, cuándo llega, la diferencia entre cinta y calzoncito…). Contestala en 1 o 2 líneas, de usted, con el tono de Wil en los ejemplos, y SOLO con DATOS DE LA TIENDA. Sin precios. Sin saludar. Contestá SOLO lo que preguntó: no recomiendes ni sugieras productos, no preguntes cuál quiere ni cuántos. Si no hizo pregunta, null. No contestes el envío (de eso se encarga el sistema).
- pasar_a_wil: true si reclama o tiene un problema con un pedido, pregunta por un pedido ya enviado, manda o habla de un comprobante, pide por mayor o rebaja, dice que el envío está caro, pide otro producto (pañal de adulto, toallitas…), está molesta, pide hablar con una persona, o pregunta algo que no está en los datos. motivo: en pocas palabras.

Nunca inventes ids. Nunca pongas en items algo que no está en el CATÁLOGO.
TXT;
    }
}
