<?php

namespace App\Services\Asistente;

/**
 * Del peso a la talla, y de la talla a cuántos paquetes.
 *
 * Las cuentas salen solo de las tablas (config/tallas_peso.php y el consumo
 * diario de config/asistente.php). No depende de Laravel: se prueba suelto.
 */
class Tallas
{
    /**
     * Lee "4–8 kg · 9–18 lb" → ['min' => 4, 'max' => 8] en kilos.
     * Las que no tienen rango legible se saltan.
     */
    public static function rangos(array $tablaPesos): array
    {
        $r = [];

        foreach ($tablaPesos as $talla => $texto) {
            if (preg_match('/(\d+(?:[.,]\d+)?)\s*[–\-—a]+\s*(\d+(?:[.,]\d+)?)\s*kg/u', (string) $texto, $m)) {
                $r[mb_strtoupper(trim((string) $talla))] = [
                    'min' => (float) str_replace(',', '.', $m[1]),
                    'max' => (float) str_replace(',', '.', $m[2]),
                ];
            }
        }

        return $r;
    }

    /**
     * Qué talla (o qué dos tallas) le quedan con ese peso.
     *
     * Devuelve ['tallas' => [...], 'caso' => …]:
     *   'una'   · le queda una sola
     *   'dos'   · está donde se juntan dos (o cerca del tope de una)
     *   'fuera' · el peso no está en la tabla (muy bajo o muy alto)
     *
     * $orden   · las tallas en orden de chica a grande
     * $ninos   · las de niño grande: solo si ninguna de bebé le queda
     */
    public static function porPeso(float $kg, array $rangos, array $orden, array $ninos = []): array
    {
        $orden = array_values(array_filter($orden, fn ($t) => isset($rangos[$t])));

        $bebe  = array_values(array_filter($orden, fn ($t) => ! in_array($t, $ninos, true)));
        $grand = array_values(array_filter($orden, fn ($t) => in_array($t, $ninos, true)));

        $calzan = fn (array $lista) => array_values(array_filter(
            $lista,
            fn ($t) => $kg >= $rangos[$t]['min'] && $kg <= $rangos[$t]['max']
        ));

        $les = $calzan($bebe) ?: $calzan($grand);

        if (! $les) return ['tallas' => [], 'caso' => 'fuera'];

        // Más de dos: se quedan las dos donde el peso cae más al centro.
        if (count($les) > 2) {
            usort($les, function ($a, $b) use ($kg, $rangos) {
                $ca = abs($kg - ($rangos[$a]['min'] + $rangos[$a]['max']) / 2);
                $cb = abs($kg - ($rangos[$b]['min'] + $rangos[$b]['max']) / 2);
                return $ca <=> $cb;
            });
            $les = array_slice($les, 0, 2);
            usort($les, fn ($a, $b) => array_search($a, $orden) <=> array_search($b, $orden));
        }

        if (count($les) === 2) return ['tallas' => $les, 'caso' => 'dos'];

        // Una sola, pero ya en el último kilo: se sugiere también la siguiente,
        // porque en un mes ya no le va a quedar.
        $t = $les[0];
        $i = array_search($t, $orden, true);
        $siguiente = $orden[$i + 1] ?? null;

        if ($siguiente && ! in_array($siguiente, $ninos, true) && $kg >= $rangos[$t]['max'] - 1) {
            return ['tallas' => [$t, $siguiente], 'caso' => 'dos'];
        }

        return ['tallas' => [$t], 'caso' => 'una'];
    }

    /**
     * Paquetes para cubrir tantos días, redondeando para arriba. null si no
     * hay datos para calcular.
     */
    public static function paquetes(?int $porDia, ?int $unidadesPaquete, int $dias = 30): ?int
    {
        if (! $porDia || ! $unidadesPaquete || $porDia <= 0 || $unidadesPaquete <= 0) return null;

        return max(1, (int) ceil($porDia * $dias / $unidadesPaquete));
    }
}
