<?php

namespace App\Services;

use Illuminate\Support\Carbon;

/**
 * Lee el bloque de celdas que se copia de la liquidación de Express.
 *
 * Cada renglón viene separado por tabuladores:
 *   [vacío] [vacío] F.ENTREGA  ESTABLECIMIENTO  #ORDEN  CLIENTE  ZONA  MONTO  COMISIÓN  TOTAL
 *
 * Reglas del negocio:
 *  - Express cobra POR BULTO. Tres renglones con el mismo número de orden
 *    son tres bultos: se cobran tres veces.
 *  - Los bultos extra vienen en $0, así que el dinero no se duplica.
 *  - Los clientes que terminan en AIWIBI no son plata nuestra: se apartan.
 */
class ExpressPegado
{
    private const MESES = [
        'ene' => 1, 'feb' => 2, 'mar' => 3, 'abr' => 4,  'may' => 5,  'jun' => 6,
        'jul' => 7, 'ago' => 8, 'sep' => 9, 'oct' => 10, 'nov' => 11, 'dic' => 12,
    ];

    /** Convierte el texto pegado en renglones limpios. */
    public static function leer(string $texto): array
    {
        $filas = [];

        foreach (preg_split("/\r\n|\n|\r/", $texto) as $linea) {
            if (trim($linea) === '') continue;

            // Si no hay tabuladores (algunos navegadores pegan con espacios),
            // se separa por dos o más espacios seguidos.
            $celdas = str_contains($linea, "\t")
                ? explode("\t", $linea)
                : preg_split('/ {2,}/', $linea);

            $celdas = array_map(fn ($c) => trim((string) $c), $celdas);

            // Se busca la columna de la fecha: "13-ago"
            $i = null;
            foreach ($celdas as $k => $c) {
                if (preg_match('/^\d{1,2}\s*-\s*[a-záéíóú]{3,}\.?$/iu', $c)) { $i = $k; break; }
            }
            if ($i === null) continue;   // renglón de totales o basura

            $fecha = static::fecha($celdas[$i]);
            if (! $fecha) continue;

            $g = fn ($n) => $celdas[$i + $n] ?? '';

            $nombre = trim($g(3));
            if ($nombre === '') continue;

            $monto = static::numero($g(5));
            $com   = static::numero($g(6));
            $tot   = static::numero($g(7));

            // Si el total viniera vacío, se deduce.
            if ($tot === 0.0 && $monto > 0) $tot = round($monto - $com, 2);

            // La última columna trae explicaciones de Express.
            $nota = trim($g(8));

            $filas[] = [
                'fecha'     => $fecha,
                'orden'     => trim($g(2)),
                'nombre'    => $nombre,
                'zona'      => trim($g(4)),
                'monto'     => $monto,
                'comision'  => $com,
                'total'     => $tot,
                'nota'      => $nota !== '' ? $nota : null,
                'duplicado' => static::esDuplicado($nota),
                'aiwibi'    => static::esAiwibi($nombre),
            ] + static::deLaNota($nota);
        }

        return $filas;
    }

    public static function esAiwibi(string $nombre): bool
    {
        return (bool) preg_match('/AIWIB[IY]\s*\.?\s*$/iu', trim($nombre));
    }

    /**
     * Express marca con "TYP" los renglones que repitió por error: son el mismo
     * bulto anotado dos veces. No se cuentan ni se pagan.
     */
    public static function esDuplicado(?string $nota): bool
    {
        return (bool) preg_match('/\bTYP\b/i', (string) $nota);
    }

    /**
     * Lee la nota y, si explica por qué el bulto vino en $0, lo contesta solo.
     * "DEPOSITO A TIENDA POR $27,50" -> te lo pagaron directo, $27.50.
     */
    public static function deLaNota(?string $nota): array
    {
        $n = trim((string) $nota);
        if ($n === '') return [];

        if (preg_match('/DEP[O\x{00D3}]SITO\s+A\s+(?:TIENDA|AJ)/iu', $n)) {
            $monto = null;
            if (preg_match('/\$\s*([\d.,]+)/', $n, $m)) {
                $monto = static::numero($m[1]);
            }
            return ['caso' => 'transferencia', 'transferido' => $monto];
        }

        return [];
    }

    /** Huella para no guardar dos veces el mismo renglón si se pega de nuevo. */
    public static function huella(array $f): string
    {
        return md5(implode('|', [
            $f['fecha'], $f['orden'], mb_strtolower(trim($f['nombre'])),
            mb_strtolower(trim($f['zona'] ?? '')),
            number_format($f['monto'], 2, '.', ''),
            number_format($f['total'], 2, '.', ''),
            mb_strtolower(trim($f['nota'] ?? '')),
        ]));
    }

    /**
     * "13-ago" no trae año. Se asume el año en curso; si eso diera una fecha
     * muy adelantada (más de 30 días), se toma el año anterior.
     */
    public static function fecha(string $texto): ?string
    {
        if (! preg_match('/^(\d{1,2})\s*-\s*([a-záéíóú]{3,})/iu', trim($texto), $m)) {
            return null;
        }

        $dia = (int) $m[1];
        $mes = static::MESES[mb_strtolower(mb_substr($m[2], 0, 3))] ?? null;
        if (! $mes || $dia < 1 || $dia > 31) return null;

        try {
            $f = Carbon::create((int) now()->year, $mes, $dia);
            if ($f->greaterThan(now()->addDays(30))) $f->subYear();
            return $f->toDateString();
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** Lee "$ 1.458,40", "$1,458.40", "52,00" o vacío. */
    public static function numero($valor): float
    {
        $s = preg_replace('/[^\d,.\-]/u', '', (string) $valor);
        if ($s === '' || $s === '-') return 0.0;

        $coma  = strrpos($s, ',');
        $punto = strrpos($s, '.');

        if ($coma !== false && $punto !== false) {
            $s = $coma > $punto
                ? str_replace(',', '.', str_replace('.', '', $s))
                : str_replace(',', '', $s);
        } elseif ($coma !== false) {
            $s = (strlen($s) - $coma - 1) <= 2
                ? str_replace(',', '.', $s)
                : str_replace(',', '', $s);
        }

        return round((float) $s, 2);
    }
}
