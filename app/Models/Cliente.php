<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

/**
 * Libreta de clientes: guarda los datos de cada envío para que la próxima vez
 * baste con escribir el teléfono y se llene todo solo.
 */
class Cliente extends Model
{
    protected $table = 'clientes';

    protected $fillable = ['telefono', 'nombre', 'direccion', 'municipio', 'departamento', 'veces'];

    /** Deja solo los dígitos (sin +503, espacios ni guiones). */
    public static function normalizar(?string $telefono): string
    {
        $d = preg_replace('/\D/', '', (string) $telefono);
        if (strlen($d) === 11 && str_starts_with($d, '503')) $d = substr($d, 3);
        return $d;
    }

    /**
     * Busca un cliente por teléfono. Si no está en la libreta, lo busca en los
     * pedidos anteriores de la tienda para no perder ese dato.
     */
    public static function buscar(?string $telefono): ?array
    {
        $d = static::normalizar($telefono);
        if (strlen($d) < 8) return null;

        try {
            if (Schema::hasTable('clientes')) {
                $c = static::where('telefono', $d)->first();
                if ($c) {
                    return [
                        'nombre'       => $c->nombre,
                        'direccion'    => $c->direccion,
                        'municipio'    => $c->municipio,
                        'departamento' => $c->departamento,
                        'veces'        => $c->veces,
                    ];
                }
            }

            // Respaldo: buscar en los pedidos de la tienda.
            $pedido = Order::whereRaw(
                "REPLACE(REPLACE(REPLACE(COALESCE(phone,''),' ',''),'-',''),'+','') LIKE ?",
                ['%' . $d]
            )->latest('id')->first();

            if ($pedido) {
                $partes = array_map('trim', explode(',', (string) $pedido->municipio));
                return [
                    'nombre'       => $pedido->customer_name,
                    'direccion'    => $pedido->address,
                    'municipio'    => $partes[0] ?? null,
                    'departamento' => $partes[1] ?? null,
                    'veces'        => 1,
                ];
            }
        } catch (\Throwable $e) {
        }

        return null;
    }

    /** Guarda o actualiza al cliente con los datos del último envío. */
    public static function recordar(array $datos): void
    {
        $d = static::normalizar($datos['telefono'] ?? '');
        if (strlen($d) < 8) return;

        try {
            if (! Schema::hasTable('clientes')) return;

            $c = static::firstOrNew(['telefono' => $d]);
            $c->nombre       = $datos['nombre']       ?: $c->nombre;
            $c->direccion    = $datos['direccion']    ?: $c->direccion;
            $c->municipio    = $datos['municipio']    ?: $c->municipio;
            $c->departamento = $datos['departamento'] ?: $c->departamento;
            $c->veces        = ($c->exists ? (int) $c->veces : 0) + 1;
            $c->save();
        } catch (\Throwable $e) {
        }
    }
}
