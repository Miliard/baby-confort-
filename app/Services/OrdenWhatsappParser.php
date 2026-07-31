<?php

namespace App\Services;

/**
 * Interpreta el texto de una "Orden de envío" pegada desde WhatsApp y saca
 * los datos del pedido. Formato típico:
 *
 *   Orden de envío:🚚
 *   ✅Nombre completo:
 *   Bianca pimentel
 *   ✅Dirección:
 *   Final de la 85 avenida norte... san salvador
 *   ✅producto:
 *   4 paquetes de niño de 8 a 15 años $30
 *   ✅envío:$2.50
 *   💰total: $32.50
 *
 * Tolera variaciones: con o sin emojis, valores en la misma línea o en la
 * siguiente, varios productos (una línea por producto).
 */
class OrdenWhatsappParser
{
    private const DEPARTAMENTOS = [
        'san salvador', 'la libertad', 'santa ana', 'sonsonate', 'ahuachapan',
        'chalatenango', 'cuscatlan', 'la paz', 'cabañas', 'cabanas', 'san vicente',
        'usulutan', 'san miguel', 'morazan', 'la union',
    ];

    public static function parsear(string $texto): array
    {
        $out = ['nombre' => null, 'direccion' => null, 'municipio' => null, 'items' => [], 'envio' => null, 'total' => null];
        if (trim($texto) === '') return $out;

        $lineas = preg_split('/\r?\n/u', $texto);
        $seccion = null;

        foreach ($lineas as $linea) {
            // Quita emojis/símbolos decorativos y espacios sobrantes.
            $l = trim(preg_replace('/[✅☑️✔️🚚💰📦🛒👉•*_]+/u', '', $linea));
            if ($l === '') continue;

            $clave = self::normalizar($l);

            if (str_starts_with($clave, 'orden de envio')) continue;

            // ¿La línea abre una sección? (el valor puede venir tras ":" o en la línea siguiente)
            foreach ([
                'nombre'    => ['nombre completo', 'nombre'],
                'direccion' => ['direccion exacta', 'direccion'],
                'items'     => ['productos', 'producto'],
                'envio'     => ['envio', 'costo de envio'],
                'total'     => ['total a pagar', 'total'],
            ] as $sec => $alias) {
                foreach ($alias as $a) {
                    if (str_starts_with($clave, $a)) {
                        $seccion = $sec;
                        $resto = trim((string) preg_replace('/^[^:：]*[:：]\s*/u', '', $l));
                        if ($resto !== '' && self::normalizar($resto) !== $clave) {
                            self::asignar($out, $sec, $resto);
                            if (in_array($sec, ['nombre', 'envio', 'total'])) $seccion = null;
                        }
                        continue 3; // siguiente línea
                    }
                }
            }

            // Línea de valor dentro de la sección actual.
            if ($seccion) {
                self::asignar($out, $seccion, $l);
                if (in_array($seccion, ['nombre', 'envio', 'total'])) $seccion = null;
            }
        }

        // Municipio: intenta detectar el departamento dentro de la dirección.
        if ($out['direccion'] && ! $out['municipio']) {
            $dirNorm = self::normalizar($out['direccion']);
            foreach (self::DEPARTAMENTOS as $depto) {
                if (str_contains($dirNorm, $depto)) {
                    $bonito = ucwords($depto);
                    $out['municipio'] = $bonito . ', ' . $bonito;
                    break;
                }
            }
        }

        return $out;
    }

    private static function asignar(array &$out, string $seccion, string $valor): void
    {
        switch ($seccion) {
            case 'nombre':
                $out['nombre'] = $out['nombre'] ?? $valor;
                break;
            case 'direccion':
                $out['direccion'] = trim(($out['direccion'] ? $out['direccion'] . ' ' : '') . $valor);
                break;
            case 'items':
                $item = self::parsearItem($valor);
                if ($item) $out['items'][] = $item;
                break;
            case 'envio':
                $out['envio'] = self::monto($valor);
                break;
            case 'total':
                $out['total'] = self::monto($valor);
                break;
        }
    }

    // "4 paquetes de niño de 8 a 15 años $30" -> cantidad 4, precio c/u 7.50
    private static function parsearItem(string $l): ?array
    {
        $monto = self::monto($l);
        $texto = trim((string) preg_replace('/\$\s*[\d.,]+\s*$/u', '', $l));
        if ($texto === '' && $monto === null) return null;

        $cantidad = 1;
        if (preg_match('/^(\d{1,3})\s+/u', $texto, $m)) {
            $cantidad = max(1, (int) $m[1]);
        }
        $precioUnitario = $monto !== null ? round($monto / $cantidad, 2) : 0;

        return [
            'producto' => $texto !== '' ? $texto : 'Producto',
            'talla'    => '-',
            'cantidad' => $cantidad,
            'precio'   => $precioUnitario,
        ];
    }

    private static function monto(string $l): ?float
    {
        if (preg_match_all('/\$\s*([\d]+(?:[.,]\d{1,2})?)/u', $l, $m) && ! empty($m[1])) {
            $n = str_replace(',', '.', end($m[1]));
            return (float) $n;
        }
        return null;
    }

    private static function normalizar(string $s): string
    {
        $s = mb_strtolower(trim($s));
        return strtr($s, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u']);
    }
}
