<?php

namespace App\Services;

use App\Models\ProductSize;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Reconoce qué productos pidió el cliente y cuánto suma, contra el catálogo.
 *
 * La diferencia con leer la orden a secas: el lector de órdenes saca el precio
 * del texto, y el texto lo escribió una persona apurada. Si puso "$17" cuando
 * eran $18, la guía sale con $17 y esa diferencia la paga el negocio.
 *
 * Acá el texto solo dice QUÉ y CUÁNTOS. El CUÁNTO sale de la base de datos,
 * que es el único lugar donde el precio está bien. Y si hay combo cargado, se
 * aplica solo: si el cliente lleva 3 y el combo de 3 vale menos, va el combo.
 *
 * Lo que no reconoce no lo inventa: lo devuelve aparte, en "dudosos", para que
 * se vea que quedó sin precio en vez de que desaparezca de la suma.
 */
class ReconocerProductos
{
    /**
     * El catálogo en una forma cómoda de comparar. Cinco minutos en memoria:
     * cambiar un precio se refleja casi enseguida y no se consulta la base en
     * cada tecla.
     */
    private static function catalogo(): array
    {
        try {
            return Cache::remember('reconocer_catalogo', 300, function () {
                $filas = ProductSize::with('product')
                    ->where('price', '>', 0)
                    ->whereHas('product', fn ($q) => $q->where('active', true))
                    ->get();

                $lista = [];

                foreach ($filas as $s) {
                    if (! $s->product) continue;

                    $lista[] = [
                        'id'          => $s->id,
                        'producto'    => trim((string) $s->product->name),
                        'talla'       => trim((string) $s->size),
                        'precio'      => (float) $s->price,
                        'combo_qty'   => (int) ($s->combo_qty ?? 0),
                        'combo_price' => (float) ($s->combo_price ?? 0),
                        'unidades'    => (int) ($s->unidades ?? 0),
                        // Las palabras del nombre, ya normalizadas, para comparar.
                        'palabras'    => static::palabras($s->product->name),
                        'talla_norm'  => static::normalizar($s->size),
                    ];
                }

                return $lista;
            });
        } catch (\Throwable $e) {
            Log::warning('Catálogo para reconocer productos: ' . $e->getMessage());
            return [];
        }
    }

    /** Sin tildes, sin mayúsculas, sin puntuación. */
    public static function normalizar(?string $t): string
    {
        $s = mb_strtolower(trim((string) $t));

        $s = strtr($s, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
            'ü' => 'u', 'ñ' => 'n',
        ]);

        $s = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $s);

        return trim(preg_replace('/\s+/u', ' ', $s));
    }

    /** Las palabras con peso de un nombre: fuera artículos y preposiciones. */
    private static function palabras(?string $t): array
    {
        $vacias = ['de', 'del', 'la', 'el', 'los', 'las', 'para', 'con', 'y', 'a', 'en', 'un', 'una'];

        $p = array_filter(
            explode(' ', static::normalizar($t)),
            fn ($x) => $x !== '' && ! in_array($x, $vacias, true) && mb_strlen($x) > 1
        );

        return array_values($p);
    }

    /**
     * Lee un texto suelto y devuelve lo que pidió, con precios de verdad.
     *
     * @return array{items: array, total: float, dudosos: array}
     */
    public static function enTexto(?string $texto): array
    {
        $vacio = ['items' => [], 'total' => 0.0, 'dudosos' => []];

        $t = trim((string) $texto);
        if ($t === '') return $vacio;

        $catalogo = static::catalogo();
        if (! $catalogo) return $vacio;

        $items   = [];
        $dudosos = [];

        foreach (preg_split('/\r\n|\n|\r|\s*[,;]\s*|\s+\+\s+/u', $t) as $linea) {
            $l = trim((string) $linea);
            if ($l === '') continue;

            // Renglones que claramente no son productos.
            if (preg_match('/^(nombre|tel|telefono|direccion|municipio|departamento|total|costo|envio)/iu', static::normalizar($l))) {
                continue;
            }

            $cantidad = static::cantidadDe($l);
            $calce    = static::mejorCalce($l, $catalogo);

            if (! $calce) {
                // Solo se reporta como dudoso si el renglón parece decir algo.
                if (mb_strlen(static::normalizar($l)) > 3) $dudosos[] = $l;
                continue;
            }

            $items[] = static::armarItem($calce, $cantidad);
        }

        $total = 0.0;
        foreach ($items as $i) $total += $i['subtotal'];

        return [
            'items'   => $items,
            'total'   => round($total, 2),
            'dudosos' => $dudosos,
        ];
    }

    /**
     * Cuántos pidió. Si no dice, uno.
     *
     * Mira solo el principio del renglón: un número suelto más adelante suele
     * ser parte del nombre ("de 8 a 14 años") o un precio, no una cantidad.
     */
    private static function cantidadDe(string $linea): int
    {
        $n = static::normalizar($linea);

        $palabras = [
            'un' => 1, 'una' => 1, 'dos' => 2, 'tres' => 3, 'cuatro' => 4,
            'cinco' => 5, 'seis' => 6, 'siete' => 7, 'ocho' => 8,
            'nueve' => 9, 'diez' => 10, 'doce' => 12,
        ];

        if (preg_match('/^(\d{1,3})\b/u', $n, $m)) {
            $c = (int) $m[1];
            return ($c >= 1 && $c <= 200) ? $c : 1;
        }

        if (preg_match('/^(\p{L}+)\b/u', $n, $m) && isset($palabras[$m[1]])) {
            return $palabras[$m[1]];
        }

        return 1;
    }

    /**
     * La presentación del catálogo que mejor calza con el renglón.
     *
     * Puntúa cada candidata: cuántas palabras de su nombre aparecen, y si la
     * talla está nombrada. La talla pesa mucho porque es lo que distingue dos
     * filas que por nombre son idénticas.
     */
    private static function mejorCalce(string $linea, array $catalogo): ?array
    {
        $n = ' ' . static::normalizar($linea) . ' ';

        $mejor = null;
        $mejorPunto = 0;

        foreach ($catalogo as $c) {
            $punto = 0;

            foreach ($c['palabras'] as $p) {
                if (str_contains($n, ' ' . $p . ' ')) $punto += 2;
            }

            // Sin una sola palabra del nombre, no es candidata: si no, con la
            // talla sola cualquier renglón calzaría con cualquier producto.
            if ($punto === 0) continue;

            if ($c['talla_norm'] !== '' && static::mencionaTalla($n, $c['talla_norm'])) {
                // Las tallas largas ("4 a 7 años") son más específicas que "m",
                // así que valen más: evitan que "m" le gane a la correcta.
                $punto += 3 + min(4, count(explode(' ', $c['talla_norm'])));
            }

            if ($punto > $mejorPunto) {
                $mejorPunto = $punto;
                $mejor = $c;
            }
        }

        // Un solo acierto de una palabra suelta es demasiado poco.
        return $mejorPunto >= 4 ? $mejor : null;
    }

    /** ¿El renglón nombra esa talla, como palabra entera? */
    private static function mencionaTalla(string $textoNorm, string $talla): bool
    {
        return (bool) preg_match(
            '/(?<![\p{L}\p{N}])' . preg_quote($talla, '/') . '(?![\p{L}\p{N}])/u',
            $textoNorm
        );
    }

    /**
     * Arma la línea final y le pone precio.
     *
     * Acá se aplica el combo: si lleva tantos o más de los que pide el combo,
     * se cobran en paquetes de combo y el resto suelto. Nunca sale más caro
     * que el precio unitario por la cantidad.
     */
    private static function armarItem(array $c, int $cantidad): array
    {
        $suelto = $c['precio'] * $cantidad;
        $precio = $suelto;
        $conCombo = false;

        if ($c['combo_qty'] > 0 && $c['combo_price'] > 0 && $cantidad >= $c['combo_qty']) {
            $paquetes = intdiv($cantidad, $c['combo_qty']);
            $resto    = $cantidad % $c['combo_qty'];
            $conCombo = ($paquetes * $c['combo_price']) + ($resto * $c['precio']);

            // Solo si de verdad conviene: un combo mal cargado no puede salir
            // más caro que comprar suelto.
            if ($conCombo < $suelto) {
                $precio = $conCombo;
                $conCombo = true;
            } else {
                $conCombo = false;
            }
        }

        return [
            'size_id'   => $c['id'],
            'cantidad'  => $cantidad,
            'producto'  => $c['producto'],
            'talla'     => $c['talla'],
            'unidades'  => $c['unidades'],
            'precio'    => $c['precio'],
            'subtotal'  => round($precio, 2),
            'combo'     => (bool) $conCombo,
        ];
    }

    /** Cómo se escribe en la guía: "2 Calzoncito Magic talla M". */
    public static function descripcion(array $items): string
    {
        return collect($items)
            ->map(fn ($i) => $i['cantidad'] . ' ' . $i['producto']
                . ($i['talla'] !== '' ? ' talla ' . $i['talla'] : ''))
            ->implode(', ');
    }
}
