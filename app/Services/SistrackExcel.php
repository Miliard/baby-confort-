<?php

namespace App\Services;

use App\Models\Order;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

/**
 * Genera el Excel de carga masiva de Sistrack (modelo_carga.xlsx) a partir
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
        $t = str_replace(['—', '–', '·', '"', '"', ''', '''], ['-', '-', '-', '"', '"', "'", "'"], $t);
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

        return trim($tel . ' ' . trim((string) $nombre));
    }

    public static function generar($orders, string $path): void
    {
        $writer = new Writer();
        $writer->openToFile($path);
        // Sistrack espera la hoja como en su plantilla ("Hoja1").
        $writer->getCurrentSheet()->setName('Hoja1');

        $writer->addRow(Row::fromValues([
            'ORDEN', 'NOMBRE', 'TELEFONO', 'EMAIL', 'DIRECCION', 'MUNICIPIO',
            'DEPARTAMENTO', 'PAIS', 'CODIGO POSTAL', 'DESCRIPCION', 'PESO', 'PRECIO', 'OBSERVACIONES',
        ]));

        foreach ($orders as $order) {
            $writer->addRow(Row::fromValues(self::fila($order)));
        }

        $writer->close();
    }

    /**
     * Genera el Excel a partir de una lista simple (pantalla "Crear guías"),
     * sin necesidad de que los pedidos estén guardados en la base de datos.
     * Cada elemento: nombre, telefono, direccion, municipio, departamento, descripcion, cobrar.
     */
    public static function generarDesdeLista(array $lista, string $path): void
    {
        $writer = new Writer();
        $writer->openToFile($path);
        // Sistrack espera la hoja como en su plantilla ("Hoja1").
        $writer->getCurrentSheet()->setName('Hoja1');

        $writer->addRow(Row::fromValues([
            'ORDEN', 'NOMBRE', 'TELEFONO', 'EMAIL', 'DIRECCION', 'MUNICIPIO',
            'DEPARTAMENTO', 'PAIS', 'CODIGO POSTAL', 'DESCRIPCION', 'PESO', 'PRECIO', 'OBSERVACIONES',
        ]));

        foreach ($lista as $g) {
            $digitos = preg_replace('/\D/', '', (string) ($g['telefono'] ?? ''));
            $tel     = strlen($digitos) === 8 ? '+503' . $digitos : '+' . $digitos;
            $cobrar  = (float) ($g['cobrar'] ?? 0);

            $writer->addRow(Row::fromValues([
                '',                                                             // ORDEN (la asigna Sistrack)
                self::nombreConTelefono($g['telefono'] ?? '', $g['nombre'] ?? ''), // NOMBRE ("7777 7777 Juan Pérez")
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
                $cobrar > 0
                    ? 'COBRAR AL ENTREGAR: $' . number_format($cobrar, 2)
                    : 'PAGADO - no cobrar',                                     // OBSERVACIONES
            ]));
        }

        $writer->close();
    }

    public static function fila(Order $order): array
    {
        $digitos = preg_replace('/\D/', '', (string) $order->phone);
        $tel     = strlen($digitos) === 8 ? '+503' . $digitos : '+' . $digitos;

        $partes = array_map('trim', explode(',', (string) $order->municipio));
        $muni   = $partes[0] ?: '';
        $depto  = $partes[1] ?? $muni;

        $cobrar = $order->payment === 'efectivo' ? (float) $order->total : 0.0;
        $obs    = ($cobrar > 0
                ? 'COBRAR AL ENTREGAR: $' . number_format($cobrar, 2) . ' (efectivo)'
                : 'PAGADO - no cobrar')
            . ' - Pedido #' . $order->id;

        return [
            '',                                                          // ORDEN (la asigna Sistrack)
            self::nombreConTelefono($order->phone, $order->customer_name), // NOMBRE ("7777 7777 Juan Pérez")
            $tel,                                                        // TELEFONO
            'clientes@baby-confort.shop',                                // EMAIL
            self::limpiar($order->address),                              // DIRECCION
            self::limpiar($muni, 60),                                    // MUNICIPIO
            self::limpiar($depto, 60),                                   // DEPARTAMENTO
            'El Salvador',                                               // PAIS
            '',                                                          // CODIGO POSTAL
            self::limpiar(SistrackService::descripcionDe($order)) ?: 'Productos para bebe', // DESCRIPCION
            1,                                                           // PESO
            $cobrar,                                                     // PRECIO (monto a cobrar)
            self::limpiar($obs),                                         // OBSERVACIONES
        ];
    }
}
