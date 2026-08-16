<?php

namespace App\Services;

use App\Models\ExpressEntrega;
use App\Models\GuiaFoto;
use App\Models\Product;
use Illuminate\Support\Facades\Cache;

/**
 * Cuenta qué producto sale más, por CANTIDAD de paquetes (no por dinero).
 *
 * De dónde salen los datos:
 *  - GuiaFoto.contenido guarda qué llevaba cada guía (lo que escribiste al armarla).
 *  - ExpressEntrega da la fecha real de entrega de esa guía.
 *
 * El texto es libre ("2 Calzoncito Magic talla XXL"), así que se compara
 * palabra por palabra contra el catálogo, tolerando errores de dedo
 * ("jumbo" contra "junbo"). Lo que no encaja NO se inventa: va a una lista
 * aparte para que se pueda revisar.
 */
class ProductosVendidos
{
    /** Palabras que no ayudan a identificar el producto. */
    private const VACIAS = [
        'de', 'del', 'la', 'las', 'el', 'los', 'para', 'con', 'y', 'a', 'en', 'por',
        'un', 'una', 'talla', 'tallas', 'paquete', 'paquetes', 'unidades', 'unidad',
        'aiwibi', 'aiwina', 'tipo', 'su', 'al',
    ];

    private const TALLAS = ['S', 'M', 'L', 'XL', 'XXL', 'XXXL'];

    /** Quita tildes, emojis y símbolos; deja palabras en minúscula. */
    public static function palabras(string $texto): array
    {
        $t = mb_strtolower(trim($texto));
        $t = strtr($t, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
        ]);
        $t = preg_replace('/[^a-z0-9\s]/u', ' ', $t);

        $out = [];
        foreach (preg_split('/\s+/', $t) as $p) {
            $p = trim($p);
            if ($p === '' || in_array($p, self::VACIAS, true)) continue;
            $out[] = $p;
        }
        return $out;
    }

    /** ¿Estas dos palabras son la misma, aunque una tenga un error de dedo? */
    private static function pareceIgual(string $a, string $b): bool
    {
        if ($a === $b) return true;
        if (strlen($a) < 4 || strlen($b) < 4) return false;
        if (abs(strlen($a) - strlen($b)) > 2) return false;

        return levenshtein($a, $b) <= 1;
    }

    /** El catálogo, ya troceado en palabras, para comparar rápido. */
    private static function catalogo(): array
    {
        return Cache::remember('pv_catalogo', 600, function () {
            $out = [];
            foreach (Product::where('active', true)->get() as $p) {
                $pal = static::palabras($p->name);
                if (! $pal) continue;
                $out[] = ['id' => $p->id, 'nombre' => $p->name, 'palabras' => $pal];
            }
            return $out;
        });
    }

    /** Busca a qué producto del catálogo se parece un renglón. */
    public static function identificar(string $linea): ?array
    {
        $pal = static::palabras($linea);
        if (! $pal) return null;

        $mejor = null; $mejorPunto = 0.0;

        foreach (static::catalogo() as $c) {
            $aciertos = 0;
            foreach ($c['palabras'] as $cp) {
                foreach ($pal as $lp) {
                    if (static::pareceIgual($cp, $lp)) { $aciertos++; break; }
                }
            }
            if ($aciertos === 0) continue;

            // Proporción de palabras del producto que aparecen en el texto.
            $punto = $aciertos / count($c['palabras']);
            if ($punto > $mejorPunto) { $mejorPunto = $punto; $mejor = $c; }
        }

        // Menos de la mitad de coincidencia es adivinar: mejor no clasificar.
        return $mejorPunto >= 0.5 ? $mejor : null;
    }

    /** Saca la cantidad del inicio del renglón: "2 Calzoncito..." → 2 */
    public static function cantidad(string $linea): int
    {
        return preg_match('/^\s*(\d{1,3})\b/', $linea, $m) ? max(1, (int) $m[1]) : 1;
    }

    /** Saca la talla si aparece: "... talla XXL" → XXL */
    public static function talla(string $linea): ?string
    {
        $pal = array_map('strtoupper', static::palabras($linea));
        foreach (self::TALLAS as $t) {
            if (in_array($t, $pal, true)) return $t;
        }
        return null;
    }

    /**
     * El ranking. Devuelve productos ordenados por cantidad de paquetes,
     * más los renglones que no se pudieron identificar.
     */
    public static function ranking(?string $desde = null, ?string $hasta = null): array
    {
        $vacio = ['items' => [], 'sinIdentificar' => [], 'total' => 0, 'guias' => 0];

        if (! GuiaFoto::hayTablaContenido()) return $vacio;

        // Fecha real de entrega por número de guía (si ya se pegó la liquidación).
        $fechas = [];
        if (ExpressEntrega::hayTabla()) {
            try {
                foreach (ExpressEntrega::select('orden', 'fecha')->get() as $e) {
                    $fechas[$e->orden] = $e->fecha->toDateString();
                }
            } catch (\Throwable $e) {
            }
        }

        try {
            $guias = GuiaFoto::whereNotNull('contenido')->where('contenido', '!=', '')->get();
        } catch (\Throwable $e) {
            return $vacio;
        }

        $items = []; $sin = []; $total = 0; $cuantasGuias = 0;

        foreach ($guias as $g) {
            // Los AIWIBI son de otra empresa: no son productos nuestros.
            if (preg_match('/AIWIB[IY]\s*\.?\s*$/iu', (string) $g->nombre)) continue;

            $fecha = $fechas[$g->guia] ?? optional($g->created_at)->toDateString();
            if ($desde && (! $fecha || $fecha < $desde)) continue;
            if ($hasta && (! $fecha || $fecha > $hasta)) continue;

            $cuantasGuias++;

            foreach (\App\Models\GuiaBorrador::partirDescripcion($g->contenido) as $linea) {
                $cant  = static::cantidad($linea);
                $prod  = static::identificar($linea);
                $talla = static::talla($linea);

                if (! $prod) {
                    $sin[$linea] = ($sin[$linea] ?? 0) + $cant;
                    continue;
                }

                $clave = $prod['id'] . '|' . ($talla ?? '');
                if (! isset($items[$clave])) {
                    $items[$clave] = [
                        'producto' => $prod['nombre'],
                        'talla'    => $talla,
                        'unidades' => 0,
                        'veces'    => 0,
                    ];
                }
                $items[$clave]['unidades'] += $cant;
                $items[$clave]['veces']++;
                $total += $cant;
            }
        }

        usort($items, fn ($a, $b) => $b['unidades'] <=> $a['unidades']);
        arsort($sin);

        return [
            'items'          => array_values($items),
            'sinIdentificar' => array_slice($sin, 0, 15, true),
            'total'          => $total,
            'guias'          => $cuantasGuias,
        ];
    }
}
