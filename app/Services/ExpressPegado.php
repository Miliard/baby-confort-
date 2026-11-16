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

            // Las columnas se reconocen por lo que traen, no por su posición:
            // Express cambió el Excel (con el sistema nuevo vino una columna
            // más) y leer por posición ponía el número de guía como cliente,
            // el cliente como zona y el monto en $0. Así sirve el viejo y el nuevo.
            //   · la guía: la primera celda que es solo número (5 a 10 cifras);
            //   · el cliente: la primera celda con letras después de la guía;
            //   · la zona: la siguiente celda con letras;
            //   · los montos: las celdas con número después del cliente
            //     (monto, comisión, total);
            //   · la nota: lo que tenga letras después de los montos.
            $resto = array_values(array_slice($celdas, $i + 1));
            $esGuia   = fn ($c) => (bool) preg_match('/^\d{5,10}$/', $c);
            $esNumero = fn ($c) => $c === '' ? false : (bool) preg_match('/^[\s$\-]*[\d.,]+\s*$/u', $c);
            $esTexto  = fn ($c) => (bool) preg_match('/\p{L}/u', $c);

            $iG = null;
            foreach ($resto as $k => $c) { if ($esGuia($c)) { $iG = $k; break; } }
            if ($iG === null) continue;

            $orden = $resto[$iG];
            $nombre = ''; $zona = ''; $iN = null;
            for ($k = $iG + 1; $k < count($resto); $k++) {
                if ($esTexto($resto[$k])) { $nombre = $resto[$k]; $iN = $k; break; }
            }
            if ($nombre === '') continue;

            $nums = []; $nota = ''; $iZ = null;
            for ($k = $iN + 1; $k < count($resto); $k++) {
                $c = $resto[$k];
                if ($c === '') { if ($nums) $nums[] = 0.0; continue; }
                if ($esNumero($c)) { $nums[] = static::numero($c); continue; }
                if ($esTexto($c)) {
                    if (! $nums && $iZ === null) { $zona = $c; $iZ = $k; continue; }
                    if ($nums) { $nota = trim($nota . ' ' . $c); }
                }
            }

            $monto = (float) ($nums[0] ?? 0);
            $com   = (float) ($nums[1] ?? 0);
            $tot   = (float) ($nums[2] ?? 0);

            // Si el total viniera vacío, se deduce.
            if ($tot === 0.0 && $monto > 0) $tot = round($monto - $com, 2);

            // AIWIBI puede venir en el cliente o en el establecimiento.
            $aiwibi = false;
            foreach ($celdas as $c) { if (static::esAiwibi($c)) { $aiwibi = true; break; } }

            $filas[] = [
                'fecha'     => $fecha,
                'orden'     => $orden,
                'nombre'    => $nombre,
                'zona'      => $zona,
                'monto'     => $monto,
                'comision'  => $com,
                'total'     => $tot,
                'nota'      => $nota !== '' ? $nota : null,
                'duplicado' => static::esDuplicado($nota),
                'aiwibi'    => $aiwibi,
            ] + static::deLaNota($nota);
        }

        return $filas;
    }

    public static function esAiwibi(string $nombre): bool
    {
        // Antes iba al final ("Juan Pérez AIWIBI"); ahora a veces va adelante
        // ("7815 7877 AIWIBI Alexandra"). Cuenta en cualquier lugar del nombre.
        return (bool) preg_match('/\bAIWIB[IY]\b/iu', trim($nombre));
    }

    /**
     * "TYP" es Tamaño y Peso: Express vuelve a cobrar la misma guía porque el
     * bulto se pasó de los límites de carga. SÍ se cobra: es un renglón real.
     * Se marca solo para poder distinguirlo en pantalla.
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
