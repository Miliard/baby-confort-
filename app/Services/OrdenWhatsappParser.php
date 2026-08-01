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
        $out = ['nombre' => null, 'telefono' => null, 'direccion' => null, 'municipio' => null,
                'municipio_texto' => null, 'items' => [], 'envio' => null, 'total' => null];
        if (trim($texto) === '') return $out;

        $lineas = preg_split('/\r?\n/u', $texto);
        $seccion = null;

        foreach ($lineas as $linea) {
            // Quita emojis/símbolos decorativos y espacios sobrantes.
            $l = trim(preg_replace('/[✅☑️✔️🚚💰📦🛒👉•*_]+/u', '', $linea));
            // Quita viñetas al inicio ("- 3 paquetes...", "· 2 pañales").
            $l = trim(preg_replace('/^[\-\x{2013}\x{2014}\x{00B7}>»]+\s*/u', '', $l));
            if ($l === '') continue;

            $clave = self::normalizar($l);

            if (str_starts_with($clave, 'orden de envio')) continue;

            // ¿La línea abre una sección? (el valor puede venir tras ":" o en la línea siguiente)
            foreach ([
                'nombre'    => ['nombre completo', 'nombre'],
                'telefono'  => ['telefono', 'tel', 'celular', 'whatsapp', 'numero'],
                'municipio_texto' => ['municipio', 'depto', 'departamento'],
                'direccion' => ['direccion exacta', 'direccion'],
                'items'     => ['productos', 'producto'],
                'envio'     => ['costo de envio', 'envio'],
                'total'     => ['total a pagar', 'total'],
            ] as $sec => $alias) {
                foreach ($alias as $a) {
                    if (str_starts_with($clave, $a)) {
                        $seccion = $sec;
                        $resto = trim((string) preg_replace('/^[^:：]*[:：]\s*/u', '', $l));
                        if ($resto !== '' && self::normalizar($resto) !== $clave) {
                            self::asignar($out, $sec, $resto);
                            if (in_array($sec, ['nombre', 'telefono', 'municipio_texto', 'envio', 'total'])) $seccion = null;
                        }
                        continue 3; // siguiente línea
                    }
                }
            }

            // Línea de valor dentro de la sección actual.
            if ($seccion) {
                self::asignar($out, $seccion, $l);
                if (in_array($seccion, ['nombre', 'telefono', 'municipio_texto', 'envio', 'total'])) $seccion = null;
            }
        }

        // Si no vino etiquetado, busca un teléfono suelto (8 dígitos) en todo el texto.
        if (! $out['telefono']) {
            $limpio = preg_replace('/\$\s*[\d.,]+/u', ' ', $texto); // ignora montos
            if (preg_match('/(?:^|[^\d])(?:\+?503[\s-]*)?([267]\d{3})[\s-]?(\d{4})(?![\d])/u', $limpio, $m)) {
                $out['telefono'] = $m[1] . ' ' . $m[2];
            }
        }

        // Detecta municipio y departamento reales (catálogo de Sistrack).
        // Se da prioridad a la línea "Municipio: ..." si venía en la orden.
        $lugar = ['municipio' => null, 'departamento' => null];
        if (! empty($out['municipio_texto'])) {
            $lugar = self::detectarLugar($out['municipio_texto']);
        }
        if (! $lugar['municipio']) {
            $lugar = self::detectarLugar(($out['direccion'] ?? '') . ' ' . $texto);
        }
        $out['municipio_nombre'] = $lugar['municipio'];
        $out['departamento']     = $lugar['departamento'];

        if (! $out['municipio'] && $lugar['municipio']) {
            $out['municipio'] = $lugar['municipio'] . ($lugar['departamento'] ? ', ' . $lugar['departamento'] : '');
        }

        return $out;
    }

    /**
     * Busca en el texto un municipio del catálogo oficial (config/municipios_sv.php).
     * Si no encuentra municipio, al menos intenta reconocer el departamento.
     * Devuelve ['municipio' => string|null, 'departamento' => string|null].
     */
    public static function detectarLugar(string $texto): array
    {
        $h = self::normalizar($texto);
        if ($h === '') return ['municipio' => null, 'departamento' => null];

        $catalogo = config('municipios_sv', []);
        if (empty($catalogo)) return ['municipio' => null, 'departamento' => null];

        // Apodos: como la gente los escribe → municipio oficial de Sistrack.
        // Se revisan primero porque son más específicos que el nombre suelto.
        $apodos = [
            'opico'              => ['San Juan Opico', 'La Libertad'],
            'tecla'              => ['Santa Tecla', 'La Libertad'],
            'nueva san salvador' => ['Santa Tecla', 'La Libertad'],
            'antiguo'            => ['Antiguo Cuscatlán', 'La Libertad'],
            'ciudad delgado'     => ['Delgado', 'San Salvador'],
            'puerto la libertad' => ['La Libertad', 'La Libertad'],
            'gotera'             => ['San Francisco Gotera', 'Morazán'],
            'zacate'             => ['Zacatecoluca', 'La Paz'],
            'cojute'             => ['Cojutepeque', 'Cuscatlan'],
        ];
        foreach ($apodos as $apodo => [$muni, $depto]) {
            if (isset($catalogo[$depto]) && in_array($muni, $catalogo[$depto], true) && self::contiene($h, $apodo)) {
                return ['municipio' => $muni, 'departamento' => $depto];
            }
        }

        // 1) Municipio. Se juntan todas las coincidencias y se elige la mejor:
        //    primero la que aparece antes en el texto (el municipio suele ir antes que el
        //    departamento) y, a igual posición, el nombre más largo ("San Miguel Tepezontes"
        //    gana sobre "San Miguel").
        $candidatos = [];
        foreach ($catalogo as $depto => $municipios) {
            foreach ($municipios as $muni) {
                $k = self::normalizar($muni);
                if ($k === '') continue;
                if (preg_match('/(^|[^a-z0-9])' . preg_quote($k, '/') . '([^a-z0-9]|$)/u', $h, $mm, PREG_OFFSET_CAPTURE)) {
                    $candidatos[] = [
                        'muni'  => $muni,
                        'depto' => $depto,
                        'pos'   => $mm[0][1],
                        'largo' => mb_strlen($k),
                    ];
                }
            }
        }

        if (! empty($candidatos)) {
            usort($candidatos, function ($a, $b) {
                // Si uno contiene al otro (San Miguel vs San Miguel Tepezontes), gana el largo.
                if (abs($a['pos'] - $b['pos']) <= 3) {
                    return $b['largo'] <=> $a['largo'];
                }
                return $a['pos'] <=> $b['pos'];
            });
            return ['municipio' => $candidatos[0]['muni'], 'departamento' => $candidatos[0]['depto']];
        }

        // 2) Sin municipio: al menos el departamento (incluye variantes de escritura).
        $alias = [
            'chalatenango' => 'Chaletenango', 'chaletenango' => 'Chaletenango',
            'usulutan' => 'Usulutan', 'cuscatlan' => 'Cuscatlan',
            'ahuachapan' => 'Ahuachapán', 'cabanas' => 'Cabañas',
            'morazan' => 'Morazán', 'la union' => 'La Unión',
        ];
        foreach ($alias as $a => $real) {
            if (isset($catalogo[$real]) && self::contiene($h, $a)) {
                return ['municipio' => null, 'departamento' => $real];
            }
        }
        foreach (array_keys($catalogo) as $depto) {
            if (self::contiene($h, self::normalizar($depto))) {
                return ['municipio' => null, 'departamento' => $depto];
            }
        }

        return ['municipio' => null, 'departamento' => null];
    }

    // Coincidencia de palabra completa (para que "colon" no matchee dentro de otra palabra).
    private static function contiene(string $heno, string $aguja): bool
    {
        return (bool) preg_match('/(^|[^a-z0-9])' . preg_quote($aguja, '/') . '([^a-z0-9]|$)/u', $heno);
    }

    private static function asignar(array &$out, string $seccion, string $valor): void
    {
        switch ($seccion) {
            case 'nombre':
                if (! $out['nombre']) {
                    // A veces el nombre viene con el teléfono pegado ("6061 1693 Mireldy").
                    // Se separa: el teléfono se guarda aparte y el nombre queda limpio.
                    if (preg_match('/(?:^|[^\d])(?:\+?503[\s-]*)?([267]\d{3})[\s.-]?(\d{4})(?![\d])/u', $valor, $m)) {
                        $out['telefono'] = $out['telefono'] ?: ($m[1] . ' ' . $m[2]);
                        $valor = trim(preg_replace('/(?:\+?503[\s-]*)?' . preg_quote($m[1], '/') . '[\s.-]?' . preg_quote($m[2], '/') . '/u', ' ', $valor));
                        $valor = trim(preg_replace('/\s+/u', ' ', $valor), " \t-:.,");
                    }
                    $out['nombre'] = $valor !== '' ? $valor : null;
                }
                break;
            case 'telefono':
                if (! $out['telefono']) {
                    $d = preg_replace('/\D/', '', $valor);
                    if (strlen($d) === 11 && str_starts_with($d, '503')) $d = substr($d, 3);
                    if (strlen($d) === 8) $out['telefono'] = substr($d, 0, 4) . ' ' . substr($d, 4);
                }
                break;
            case 'municipio_texto':
                $out['municipio_texto'] = trim(($out['municipio_texto'] ? $out['municipio_texto'] . ' ' : '') . $valor);
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

        // La cantidad va al inicio ("2 Calzoncito Magic"). Se extrae y se QUITA del
        // nombre, para que luego no quede duplicada ("2 2 Calzoncito Magic").
        // También se admite "2 paquetes de ...", "3 unidades de ...".
        // Quita viñetas sueltas al inicio y toma la cantidad ("3 paquetes ..." -> 3).
        // Se conserva la palabra que sigue ("paquetes") para que la descripción se lea natural.
        $texto = trim(preg_replace('/^[\-\x{2013}\x{2014}\x{00B7}>»]+\s*/u', '', $texto));

        $cantidad = 1;
        if (preg_match('/^(\d{1,3})\s*(?:x\s+)?/u', $texto, $m) && $m[1] !== '') {
            $cantidad = max(1, (int) $m[1]);
            $texto = trim(mb_substr($texto, mb_strlen($m[0])));
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
