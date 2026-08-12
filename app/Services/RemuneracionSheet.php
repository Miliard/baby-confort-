<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Lee la hoja de entregas publicada en Google Sheets (formato CSV) y calcula
 * cuánto hay que remunerar por los paquetes de AIWIBI.
 *
 * Fórmula acordada con el negocio:
 *   1. Se suma el MONTO de los pedidos cobrados en efectivo (monto mayor a $0).
 *   2. A esa suma se le descuenta el 2.5 % de comisión.
 *   3. Se cuentan TODOS los envíos AIWIBI, incluso los de $0.00.
 *   4. Se resta $2.40 por cada uno de esos envíos.
 */
class RemuneracionSheet
{
    public const COMISION   = 2.5;     // % de comisión (se puede cambiar en el panel)
    public const POR_ENVIO  = 3.40;    // $ por envío, aunque venga en cero

    /** Cada cuánto se vuelve a leer la hoja, en segundos. */
    public const REFRESCO = 180;   // 3 minutos

    /** Filas de la hoja, ya limpias. Se releen solas cada pocos minutos. */
    public static function filas(string $url, bool $refrescar = false): array
    {
        return static::lectura($url, $refrescar)['filas'];
    }

    /** Dónde se guarda el CSV que se sube a mano. */
    public static function rutaArchivo(): string
    {
        return storage_path('app/remuneracion.csv');
    }

    /** Filas del archivo subido a mano (vacío si no hay). */
    public static function filasDeArchivo(): array
    {
        $ruta = static::rutaArchivo();
        if (! is_file($ruta)) return [];

        return Cache::remember('remun_archivo_' . filemtime($ruta), 3600, function () use ($ruta) {
            $bytes = file_get_contents($ruta);
            // Excel exporta en Windows-1252 si no se elige "CSV UTF-8".
            if (! mb_check_encoding($bytes, 'UTF-8')) {
                $bytes = mb_convert_encoding($bytes, 'UTF-8', 'Windows-1252');
            }
            return static::parsear($bytes);
        });
    }

    public static function archivoSubidoEn(): ?Carbon
    {
        $ruta = static::rutaArchivo();
        return is_file($ruta) ? Carbon::createFromTimestamp(filemtime($ruta)) : null;
    }

    /** Cuándo se leyó la hoja por última vez (null si nunca). */
    public static function leidoEn(string $url): ?Carbon
    {
        $at = static::lectura($url)['at'] ?? null;
        return $at ? Carbon::createFromTimestamp($at) : null;
    }

    private static function lectura(string $url, bool $refrescar = false): array
    {
        $clave = 'remun_' . md5($url);
        if ($refrescar) Cache::forget($clave);

        return Cache::remember($clave, static::REFRESCO, function () use ($url) {
            try {
                $r = Http::timeout(30)->get($url);
                $filas = $r->successful() ? static::parsear($r->body()) : [];
            } catch (\Throwable $e) {
                $filas = [];
            }
            return ['filas' => $filas, 'at' => time()];
        });
    }

    /** Convierte el CSV en filas con nombre, monto y fecha. */
    public static function parsear(string $csv): array
    {
        $lineas = preg_split("/\r\n|\n|\r/", $csv);
        $tabla  = [];
        foreach ($lineas as $l) {
            if (trim($l) === '') continue;
            $tabla[] = str_getcsv($l);
        }
        if (! $tabla) return [];

        // La hoja trae título y estadísticas arriba: se busca la fila de encabezados.
        $iEnc = null;
        foreach ($tabla as $i => $fila) {
            $texto = mb_strtoupper(implode('|', array_map('strval', $fila)));
            if (str_contains($texto, 'NOMBRE DE CLIENTE')) { $iEnc = $i; break; }
        }
        if ($iEnc === null) return [];

        $enc = array_map(fn ($c) => mb_strtoupper(trim((string) $c)), $tabla[$iEnc]);
        $col = function (array $nombres) use ($enc) {
            foreach ($nombres as $n) {
                $k = array_search($n, $enc, true);
                if ($k !== false) return $k;
            }
            return null;
        };

        $cFecha  = $col(['F.ENTREGA', 'FECHA', 'F. ENTREGA']);
        $cNombre = $col(['NOMBRE DE CLIENTE', 'CLIENTE', 'NOMBRE']);
        $cOrden  = $col(['# ORDEN', 'ORDEN', 'N ORDEN', 'GUIA', 'GUÍA']);
        $cMonto  = $col(['MONTO']);
        $cZona   = $col(['ZONA DE ENTREGA', 'ZONA', 'DEPARTAMENTO']);
        $cCanc   = $col(['CANCELACION', 'CANCELACIÓN']);

        if ($cNombre === null || $cMonto === null) return [];

        $filas = [];
        foreach (array_slice($tabla, $iEnc + 1) as $f) {
            $nombre = trim((string) ($f[$cNombre] ?? ''));
            if ($nombre === '') continue;

            // Si la fila trae nota en CANCELACION (por ejemplo "DEPOSITO A TIENDA")
            // no fue un envío del courier: no se cuenta ni se le cobra el flete.
            $cancel = $cCanc !== null ? trim((string) ($f[$cCanc] ?? '')) : '';

            $filas[] = [
                'fecha'     => $cFecha !== null ? static::fecha($f[$cFecha] ?? '') : null,
                'nombre'    => $nombre,
                'orden'     => $cOrden !== null ? trim((string) ($f[$cOrden] ?? '')) : '',
                'zona'      => $cZona  !== null ? trim((string) ($f[$cZona]  ?? '')) : '',
                'monto'     => static::numero($f[$cMonto] ?? ''),
                'cancelado' => $cancel !== '',
                'nota'      => $cancel,
                'aiwibi'    => static::esAiwibi($nombre),
            ];
        }

        return $filas;
    }

    /** ¿El nombre termina en AIWIBI? Esos son los paquetes de la otra empresa. */
    public static function esAiwibi(string $nombre): bool
    {
        return (bool) preg_match('/AIWIB[IY]\s*\.?\s*$/iu', trim($nombre));
    }

    /**
     * Lee cantidades escritas a la salvadoreña o a la inglesa:
     * "$ 1.458,40", "$1,458.40", "20,50", "20.50" y "" (vacío = 0).
     */
    public static function numero($valor): float
    {
        $s = trim((string) $valor);
        if ($s === '') return 0.0;

        $s = preg_replace('/[^\d,.\-]/u', '', $s);
        if ($s === '' || $s === '-') return 0.0;

        $ultimaComa  = strrpos($s, ',');
        $ultimoPunto = strrpos($s, '.');

        if ($ultimaComa !== false && $ultimoPunto !== false) {
            // El separador decimal es el que está más a la derecha.
            if ($ultimaComa > $ultimoPunto) {
                $s = str_replace('.', '', $s);
                $s = str_replace(',', '.', $s);
            } else {
                $s = str_replace(',', '', $s);
            }
        } elseif ($ultimaComa !== false) {
            // Solo coma: decimal si le siguen 1 o 2 dígitos ("20,50"); si no, miles.
            $s = (strlen($s) - $ultimaComa - 1) <= 2
                ? str_replace(',', '.', $s)
                : str_replace(',', '', $s);
        }

        return (float) $s;
    }

    /** Entiende "10-ago", "10/08/2026", "2026-08-10". Si no puede, devuelve null. */
    public static function fecha($valor): ?string
    {
        $s = trim((string) $valor);
        if ($s === '') return null;

        $meses = [
            'ene' => 1, 'feb' => 2, 'mar' => 3, 'abr' => 4,  'may' => 5,  'jun' => 6,
            'jul' => 7, 'ago' => 8, 'sep' => 9, 'oct' => 10, 'nov' => 11, 'dic' => 12,
        ];

        if (preg_match('/^(\d{1,2})[\-\/\s]([a-záéíóú]{3,})\.?(?:[\-\/\s](\d{2,4}))?$/iu', $s, $m)) {
            $mes = $meses[mb_strtolower(mb_substr($m[2], 0, 3))] ?? null;
            if ($mes) {
                $anio = isset($m[3]) ? (int) $m[3] : (int) date('Y');
                if ($anio < 100) $anio += 2000;
                return sprintf('%04d-%02d-%02d', $anio, $mes, (int) $m[1]);
            }
        }

        try {
            return Carbon::parse($s)->toDateString();
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** Aplica la fórmula sobre las filas AIWIBI del rango pedido. */
    public static function calcular(
        array $filas,
        ?string $desde = null,
        ?string $hasta = null,
        ?float $comisionPct = null,
        ?float $porEnvio = null,
    ): array {
        $comisionPct = $comisionPct ?? self::COMISION;
        $porEnvio    = $porEnvio    ?? self::POR_ENVIO;

        $todas = array_values(array_filter($filas, function ($f) use ($desde, $hasta) {
            if (! $f['aiwibi']) return false;
            if ($desde && (! $f['fecha'] || $f['fecha'] < $desde)) return false;
            if ($hasta && (! $f['fecha'] || $f['fecha'] > $hasta)) return false;
            return true;
        }));

        // Las canceladas (nota en CANCELACION) se apartan: no pagan flete.
        $cancelados = array_values(array_filter($todas, fn ($f) => $f['cancelado'] ?? false));
        $sel        = array_values(array_filter($todas, fn ($f) => ! ($f['cancelado'] ?? false)));

        $efectivo = 0.0;
        $enEfectivo = 0;
        foreach ($sel as $f) {
            if ($f['monto'] > 0) { $efectivo += $f['monto']; $enEfectivo++; }
        }

        $envios    = count($sel);
        $comision  = round($efectivo * ($comisionPct / 100), 2);
        $subtotal  = round($efectivo - $comision, 2);
        $descuento = round($envios * $porEnvio, 2);
        $aPagar    = round($subtotal - $descuento, 2);

        return [
            'filas'       => $sel,
            'cancelados'  => $cancelados,
            'envios'      => $envios,
            'enEfectivo'  => $enEfectivo,
            'sinCobro'    => $envios - $enEfectivo,
            'efectivo'    => round($efectivo, 2),
            'comisionPct' => $comisionPct,
            'comision'    => $comision,
            'subtotal'    => $subtotal,
            'porEnvio'    => $porEnvio,
            'descuento'   => $descuento,
            'aPagar'      => $aPagar,
        ];
    }
}
