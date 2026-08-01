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
    public static function generar($orders, string $path): void
    {
        $writer = new Writer();
        $writer->openToFile($path);

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
                trim($digitos . ' ' . ($g['nombre'] ?? '')),                    // NOMBRE (teléfono primero)
                $tel,                                                           // TELEFONO
                'clientes@baby-confort.shop',                                   // EMAIL
                trim(preg_replace('/\s+/', ' ', (string) ($g['direccion'] ?? ''))), // DIRECCION
                (string) ($g['municipio'] ?? ''),                               // MUNICIPIO
                (string) ($g['departamento'] ?? ''),                            // DEPARTAMENTO
                'El Salvador',                                                  // PAIS
                '',                                                             // CODIGO POSTAL
                (string) ($g['descripcion'] ?? ''),                             // DESCRIPCION
                1,                                                              // PESO
                $cobrar,                                                        // PRECIO (a cobrar)
                $cobrar > 0
                    ? 'COBRAR AL ENTREGAR: $' . number_format($cobrar, 2)
                    : 'PAGADO — no cobrar',                                     // OBSERVACIONES
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
                : 'PAGADO — no cobrar')
            . ' · Pedido #' . $order->id;

        return [
            '',                                                          // ORDEN (la asigna Sistrack)
            trim($digitos . ' ' . $order->customer_name),                // NOMBRE (convención: teléfono primero)
            $tel,                                                        // TELEFONO
            'clientes@baby-confort.shop',                                // EMAIL
            trim(preg_replace('/\s+/', ' ', (string) $order->address)),  // DIRECCION
            $muni,                                                       // MUNICIPIO
            $depto,                                                      // DEPARTAMENTO
            'El Salvador',                                               // PAIS
            '',                                                          // CODIGO POSTAL
            SistrackService::descripcionDe($order),                      // DESCRIPCION
            1,                                                           // PESO
            $cobrar,                                                     // PRECIO (monto a cobrar)
            $obs,                                                        // OBSERVACIONES
        ];
    }
}
