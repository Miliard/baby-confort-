<?php

namespace App\Services;

use App\Models\Cliente;
use App\Services\OrdenWhatsappParser;
use OpenSpout\Reader\XLSX\Reader;

/**
 * Importa la libreta de clientes exportada desde Sistrack (Excel).
 *
 * Columnas del archivo: ID · (vacío) · NOMBRE · EMAIL · DIRECCIÓN · DEPARTAMENTO · PAÍS
 * El nombre trae el teléfono adentro ("7392 8311 Yesenia Alonso", "CRB 7956 8087 Karla"),
 * así que se separa. El municipio se deduce de la dirección con el catálogo de Sistrack.
 */
class ImportadorClientes
{
    /** Teléfono salvadoreño: 8 dígitos que empiezan con 2, 6 o 7. */
    private const RX_TEL = '/(?<![\d+])([267]\d{3})[\s.\-]?(\d{4})(?!\d)/u';

    /**
     * Lee el Excel y devuelve un resumen:
     * ['leidas' => int, 'nuevos' => int, 'actualizados' => int, 'sin_telefono' => int]
     */
    public static function importar(string $rutaArchivo): array
    {
        $res = ['leidas' => 0, 'nuevos' => 0, 'actualizados' => 0, 'sin_telefono' => 0];

        $reader = new Reader();
        $reader->open($rutaArchivo);

        $encontrados = [];   // telefono => datos (para quedarnos con la mejor fila)

        foreach ($reader->getSheetIterator() as $hoja) {
            foreach ($hoja->getRowIterator() as $fila) {
                $celdas = $fila->toArray();
                if (count($celdas) < 3) continue;

                $nombreCrudo = trim((string) ($celdas[2] ?? ''));
                if ($nombreCrudo === '') continue;

                // La primera fila puede ser encabezado.
                if (mb_strtolower($nombreCrudo) === 'nombre') continue;

                $res['leidas']++;

                $datos = self::separar($nombreCrudo);
                if (! $datos['telefono']) {
                    $res['sin_telefono']++;
                    continue;
                }

                $direccion    = trim((string) ($celdas[4] ?? ''));
                $departamento = trim((string) ($celdas[5] ?? ''));

                $tel = $datos['telefono'];

                // El archivo viene del envío más nuevo al más viejo: nos quedamos con la
                // primera aparición (la más reciente), salvo que venga sin nombre.
                if (isset($encontrados[$tel]) && $encontrados[$tel]['nombre'] !== '') {
                    continue;
                }

                $encontrados[$tel] = [
                    'nombre'       => $datos['nombre'],
                    'direccion'    => $direccion,
                    'departamento' => $departamento,
                ];
            }
            break; // solo la primera hoja
        }

        $reader->close();

        // Guardar en la libreta
        foreach ($encontrados as $tel => $c) {
            $municipio = self::municipioDe($c['direccion'], $c['departamento']);

            $existente = Cliente::where('telefono', $tel)->first();

            if ($existente) {
                // No se pisa lo que ya esté guardado; solo se completa lo que falte.
                $existente->nombre       = $existente->nombre       ?: ($c['nombre'] ?: null);
                $existente->direccion    = $existente->direccion    ?: ($c['direccion'] ?: null);
                $existente->departamento = $existente->departamento ?: ($c['departamento'] ?: null);
                $existente->municipio    = $existente->municipio    ?: ($municipio ?: null);
                $existente->save();
                $res['actualizados']++;
            } else {
                Cliente::create([
                    'telefono'     => $tel,
                    'nombre'       => $c['nombre'] ?: null,
                    'direccion'    => $c['direccion'] ?: null,
                    'departamento' => $c['departamento'] ?: null,
                    'municipio'    => $municipio ?: null,
                    'veces'        => 1,
                ]);
                $res['nuevos']++;
            }
        }

        return $res;
    }

    /** Separa "CRB 7956 8087 Karla Arce AIWIBI" en teléfono + nombre limpio. */
    public static function separar(string $texto): array
    {
        // Ignora números de otros países (+1, +502…) para no confundirlos.
        $t = preg_replace('/\+\d{1,3}\s*\(?\d{2,4}\)?[\s.\-]?\d{3,4}[\s.\-]?\d{3,4}/u', ' ', $texto);

        if (! preg_match(self::RX_TEL, $t, $m)) {
            return ['telefono' => null, 'nombre' => trim($texto)];
        }

        $telefono = $m[1] . $m[2];

        // Solo se quita el teléfono. TODO lo demás se respeta: el "AIWIBI" del final
        // marca a los clientes de esa empresa (sirve para las remuneraciones), y las
        // anotaciones como "CRB" también son del negocio.
        $nombre = preg_replace(self::RX_TEL, ' ', $t);
        // Quita caracteres invisibles de dirección de texto.
        $nombre = preg_replace('/[\x{200E}\x{200F}\x{202A}-\x{202E}\x{2066}-\x{2069}]/u', '', $nombre);
        $nombre = trim(preg_replace('/\s{2,}/u', ' ', $nombre), " \t-.,|:");

        return ['telefono' => $telefono, 'nombre' => $nombre];
    }

    /** Deduce el municipio leyendo la dirección (catálogo oficial de Sistrack). */
    private static function municipioDe(string $direccion, string $departamento): ?string
    {
        if (trim($direccion) === '') return null;

        $lugar = OrdenWhatsappParser::detectarLugar($direccion);

        // Solo se acepta si el municipio pertenece al departamento del archivo.
        if ($lugar['municipio'] && $departamento !== '') {
            $delDepto = config('municipios_sv', [])[$departamento] ?? [];
            if ($delDepto && ! in_array($lugar['municipio'], $delDepto, true)) {
                return null;
            }
        }

        return $lugar['municipio'];
    }
}
