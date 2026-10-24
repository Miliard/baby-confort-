<?php

namespace App\Services;

use App\Models\Order;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

/**
 * Genera el Excel de carga masiva de guías. Por defecto en el formato del nuevo
 * sistema de T&T (hoja ORDENES, template-ordenes-tnt.xlsx); con GUIAS_FORMATO=sistrack
 * vuelve al formato anterior de Sistrack (modelo_carga.xlsx) a partir
 * de pedidos seleccionados en el admin. Columnas del modelo oficial:
 * ORDEN, NOMBRE, TELEFONO, EMAIL, DIRECCION, MUNICIPIO, DEPARTAMENTO,
 * PAIS, CODIGO POSTAL, DESCRIPCION, PESO, PRECIO, OBSERVACIONES.
 *
 * Notas:
 * - ORDEN se deja vacío para que Sistrack asigne el número automáticamente.
 * - NOMBRE sigue la convención del negocio: teléfono primero, luego el nombre.
 * - PRECIO lleva el monto a cobrar contra entrega (0 si ya está pagado).
 * - OBSERVACIONES incluye "Pedido #N" para encontrar fácil la guía de cada
 *   pedido después de importar.
 */
class SistrackExcel
{
    /**
     * Limpia un texto para el Excel: quita emojis y símbolos raros que pueden
     * hacer que el importador de Sistrack descarte la celda, y recorta espacios.
     */
    public static function limpiar(?string $texto, int $max = 240): string
    {
        $t = (string) $texto;
        // Comillas y guiones tipográficos -> versiones simples (por código, para no
        // romper la sintaxis del archivo).
        $t = str_replace(
            ["\u{2014}", "\u{2013}", "\u{00B7}", "\u{201C}", "\u{201D}", "\u{2018}", "\u{2019}"],
            ['-', '-', '-', '"', '"', "'", "'"],
            $t
        );
        // Quita emojis y caracteres de control (deja letras acentuadas y ñ).
        $t = preg_replace('/[\x{1F000}-\x{1FAFF}\x{2190}-\x{2BFF}\x{FE00}-\x{FE0F}\x{200D}]/u', '', $t);
        $t = preg_replace('/[\x00-\x1F\x7F]/u', ' ', $t);
        $t = trim(preg_replace('/\s+/u', ' ', $t));

        return mb_substr($t, 0, $max);
    }

    /**
     * Nombre para Sistrack: teléfono primero en formato "7777 7777" y luego el
     * nombre del cliente, para poder buscarlo por número. Ej: "7777 7777 Juan Pérez".
     */
    public static function nombreConTelefono(?string $telefono, ?string $nombre): string
    {
        $d = preg_replace('/\D/', '', (string) $telefono);
        // Quita el código de país si viene incluido (503).
        if (strlen($d) === 11 && str_starts_with($d, '503')) {
            $d = substr($d, 3);
        }
        // Formato salvadoreño: 4 dígitos, espacio, 4 dígitos.
        $tel = strlen($d) === 8 ? substr($d, 0, 4) . ' ' . substr($d, 4) : $d;

        $nom = trim((string) $nombre);

        // Si el nombre ya trae el teléfono al inicio (pasa cuando el cliente lo
        // escribe en la orden), se le quita para no duplicarlo.
        if ($d !== '') {
            $nom = preg_replace(
                '/^\s*(?:\+?503[\s-]*)?' . preg_quote(substr($d, 0, 4), '/') . '[\s.-]*' . preg_quote(substr($d, 4), '/') . '\s*[-:]?\s*/u',
                '',
                $nom
            );
            $nom = trim((string) $nom);
        }

        return trim($tel . ' ' . $nom);
    }

    /** Encabezados exactos de la plantilla de T&T (hoja ORDENES). Sin valor_declarado desde el 24/10/2026. */
    public const TNT_ENCABEZADOS = [
        'nombre*', 'telefono*', 'email', 'direccion*', 'referencia', 'departamento*', 'municipio*',
        'contenido*', 'cantidad_bultos', 'observaciones', 'monto_cod',
        'id_comercio', 'tipo_envio', 'agencia_origen', 'agencia_destino', 'lugar_encuentro',
    ];

    /** Formato del Excel: 'tnt' (nuevo sistema de T&T, por defecto) o 'sistrack' (el anterior). */
    public static function formato(): string
    {
        return strtolower((string) env('GUIAS_FORMATO', 'tnt')) === 'sistrack' ? 'sistrack' : 'tnt';
    }

    /** Prefijo del nombre del archivo según el formato. */
    public static function prefijo(): string
    {
        return self::formato() === 'tnt' ? 'tnt_lote' : 'sistrack_lote';
    }

    public static function generar($orders, string $path): void
    {
        $filas = [];
        foreach ($orders as $order) {
            $filas[] = self::datosDeOrden($order);
        }
        self::escribir($filas, $path);
    }

    /**
     * Genera el Excel a partir de una lista simple (pantalla "Crear guías"),
     * sin necesidad de que los pedidos estén guardados en la base de datos.
     * Cada elemento: nombre, telefono, telefono_recibe, direccion, municipio,
     * departamento, descripcion, cobrar.
     */
    public static function generarDesdeLista(array $lista, string $path): void
    {
        self::escribir($lista, $path);
    }

    /** Escribe la lista en el formato que toque (T&T o Sistrack). */
    protected static function escribir(array $lista, string $path): void
    {
        $tnt    = self::formato() === 'tnt';
        $writer = new Writer();
        $writer->openToFile($path);
        // T&T lee la hoja "ORDENES"; Sistrack leía "Hoja1".
        $writer->getCurrentSheet()->setName($tnt ? 'ORDENES' : 'Hoja1');

        $writer->addRow(Row::fromValues($tnt ? self::TNT_ENCABEZADOS : [
            'ORDEN', 'NOMBRE', 'TELEFONO', 'EMAIL', 'DIRECCION', 'MUNICIPIO',
            'DEPARTAMENTO', 'PAIS', 'CODIGO POSTAL', 'DESCRIPCION', 'PESO', 'PRECIO', 'OBSERVACIONES',
        ]));

        foreach ($lista as $g) {
            $writer->addRow(Row::fromValues($tnt ? self::filaTnt($g) : self::filaSistrack($g)));
        }

        $writer->close();
    }

    /** Teléfono de contacto en 8 dígitos (quien recibe, si es otra persona). */
    protected static function digitosContacto(array $g): string
    {
        $telContacto = trim((string) ($g['telefono_recibe'] ?? '')) !== ''
            ? $g['telefono_recibe']
            : ($g['telefono'] ?? '');

        $d = preg_replace('/\D/', '', (string) $telContacto);
        if (strlen($d) === 11 && str_starts_with($d, '503')) {
            $d = substr($d, 3);
        }

        return $d;
    }

    /** Fila para el nuevo sistema de T&T. */
    public static function filaTnt(array $g): array
    {
        $cobrar = round((float) ($g['cobrar'] ?? 0), 2);
        [$depto, $muni] = self::ubicacionTnt($g['departamento'] ?? '', $g['municipio'] ?? '');

        // T&T quitó la columna valor_declarado (24/10/2026): el precio va en monto_cod.

        $obs = $cobrar > 0
            ? 'COBRAR AL ENTREGAR: $' . number_format($cobrar, 2)
            : 'PAGADO - no cobrar';
        if (! empty($g['pedido'])) {
            $obs .= ' - Pedido #' . $g['pedido'];
        }

        return [
            self::nombreConTelefono($g['telefono'] ?? '', $g['nombre'] ?? ''), // nombre* ("7777 7777 Juan Pérez")
            self::digitosContacto($g),                                         // telefono* (8 dígitos)
            '',                                                                // email
            self::limpiar($g['direccion'] ?? ''),                              // direccion*
            '',                                                                // referencia
            $depto,                                                            // departamento*
            $muni,                                                             // municipio*
            self::limpiar($g['descripcion'] ?? '') ?: 'Productos para bebe',   // contenido*
            1,                                                                 // cantidad_bultos
            self::limpiar($obs),                                               // observaciones
            $cobrar > 0 ? $cobrar : '',                                        // monto_cod
            '',                                                                // id_comercio
            'puerta a puerta',                                                 // tipo_envio
            '', '', '',                                                        // agencia_origen, agencia_destino, lugar_encuentro
        ];
    }

    /** Fila para el sistema anterior (Sistrack). */
    public static function filaSistrack(array $g): array
    {
        $d      = self::digitosContacto($g);
        $tel    = strlen($d) === 8 ? '+503' . $d : '+' . $d;
        $cobrar = (float) ($g['cobrar'] ?? 0);
        $obs    = $cobrar > 0 ? 'COBRAR AL ENTREGAR: $' . number_format($cobrar, 2) : 'PAGADO - no cobrar';
        if (! empty($g['pedido'])) {
            $obs .= ' - Pedido #' . $g['pedido'];
        }

        return [
            '',                                                             // ORDEN (la asigna Sistrack)
            self::nombreConTelefono($g['telefono'] ?? '', $g['nombre'] ?? ''), // NOMBRE
            $tel,                                                           // TELEFONO
            'clientes@baby-confort.shop',                                   // EMAIL
            self::limpiar($g['direccion'] ?? ''),                            // DIRECCION
            self::limpiar($g['municipio'] ?? '', 60),                        // MUNICIPIO
            self::limpiar($g['departamento'] ?? '', 60),                     // DEPARTAMENTO
            'El Salvador',                                                  // PAIS
            '',                                                             // CODIGO POSTAL
            self::limpiar($g['descripcion'] ?? '') ?: 'Productos para bebe', // DESCRIPCION
            1,                                                              // PESO
            $cobrar,                                                        // PRECIO (a cobrar)
            self::limpiar($obs),                                            // OBSERVACIONES
        ];
    }

    /** Convierte un pedido guardado en la lista simple que usan los generadores. */
    public static function datosDeOrden(Order $order): array
    {
        $partes = array_map('trim', explode(',', (string) $order->municipio));
        $muni   = $partes[0] ?: '';
        $depto  = $partes[1] ?? $muni;

        return [
            'nombre'       => $order->customer_name,
            'telefono'     => $order->phone,
            'direccion'    => $order->address,
            'municipio'    => $muni,
            'departamento' => $depto,
            'descripcion'  => SistrackService::descripcionDe($order),
            'cobrar'       => $order->payment === 'efectivo' ? (float) $order->total : 0.0,
            'valor'        => (float) $order->total,
            'pedido'       => $order->id,
        ];
    }

    /** Compatibilidad: fila de un pedido en el formato que toque. */
    public static function fila(Order $order): array
    {
        $g = self::datosDeOrden($order);

        return self::formato() === 'tnt' ? self::filaTnt($g) : self::filaSistrack($g);
    }

    /** Quita tildes y pasa a minúsculas para comparar nombres. */
    protected static function clave(string $t): string
    {
        $t = mb_strtolower(trim($t));
        $t = strtr($t, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n']);

        return trim(preg_replace('/[^a-z0-9]+/', ' ', $t));
    }

    /**
     * Ajusta departamento y municipio a los nombres exactos del catálogo de T&T
     * (con tildes). Si el municipio es de otro departamento, corrige el departamento.
     * Si no lo encuentra, deja lo que venía para que T&T lo marque.
     *
     * @return array{0:string,1:string} [departamento, municipio]
     */
    public static function ubicacionTnt(?string $depto, ?string $muni): array
    {
        $catalogo = config('tnt_catalogo', []);
        $kD = self::clave((string) $depto);
        $kM = self::clave((string) $muni);

        // "San Salvador Centro", "La Libertad Sur"… (nombres de 2024) -> sin la zona.
        $kM2 = trim(preg_replace('/\s+(centro|norte|sur|este|oeste)$/', '', $kM));

        $deptoOk = null;
        foreach (array_keys($catalogo) as $d) {
            if (self::clave($d) === $kD) {
                $deptoOk = $d;
                break;
            }
        }

        $buscar = function (string $k, ?string $soloDepto) use ($catalogo) {
            foreach ($catalogo as $d => $ms) {
                if ($soloDepto && $d !== $soloDepto) {
                    continue;
                }
                foreach ($ms as $m) {
                    if (self::clave($m) === $k) {
                        return [$d, $m];
                    }
                }
            }

            return null;
        };

        foreach ([$kM, $kM2] as $k) {
            if ($k === '') {
                continue;
            }
            if ($r = ($deptoOk ? $buscar($k, $deptoOk) : null) ?? $buscar($k, null)) {
                return $r;
            }
        }

        // El "municipio" es en realidad el departamento (p. ej. "San Miguel, San Miguel").
        if ($deptoOk === null && $kM !== '') {
            foreach (array_keys($catalogo) as $d) {
                if (self::clave($d) === $kM) {
                    $deptoOk = $d;
                }
            }
        }

        return [$deptoOk ?? self::limpiar((string) $depto, 60), self::limpiar((string) $muni, 60)];
    }
}
