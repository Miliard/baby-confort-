<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

/**
 * Guías que se van armando en la pantalla "Crear guías", antes de bajar el Excel.
 * Se guardan en la base para que no se pierdan si se cae el internet, se reinicia
 * la página o se cambia de aplicación.
 */
class GuiaBorrador extends Model
{
    protected $table = 'guias_borrador';

    protected $fillable = [
        'nombre', 'telefono', 'telefono_recibe', 'direccion',
        'municipio', 'departamento', 'descripcion', 'cobrar', 'enviado_at',
    ];

    protected $casts = ['cobrar' => 'decimal:2', 'enviado_at' => 'datetime'];

    /** ¿Ya se le mandó el enlace de rastreo a este cliente? */
    public function yaEnviado(): bool
    {
        return ! is_null($this->enviado_at ?? null);
    }

    /** Lista guardada (vacía si la tabla aún no existe). */
    public static function lista()
    {
        try {
            if (! Schema::hasTable('guias_borrador')) return collect();
            return static::orderByDesc('id')->get();
        } catch (\Throwable $e) {
            return collect();
        }
    }

    /**
     * Mensaje listo para pegarle al cliente apenas se arma la guía.
     * No lleva número de guía a propósito: el cliente rastrea con su teléfono,
     * así el mismo mensaje le sirve hoy y dentro de tres meses.
     */
    public static function mensajeCliente(array $fila): string
    {
        // Primera palabra que parezca un nombre de verdad.
        $primero = null;
        foreach (preg_split('/\s+/u', trim((string) ($fila['nombre'] ?? ''))) as $palabra) {
            $p = trim($palabra, " .,-");
            if (mb_strlen($p) >= 3 && preg_match('/^[\p{L}]+$/u', $p)) {
                $primero = \Illuminate\Support\Str::title($p);
                break;
            }
        }

        // Saludo
        $t = $primero
            ? "\u{A1}Hola {$primero}! \u{1F499}\n\n"
            : "\u{A1}Hola! \u{1F499}\n\n";

        // Lo que lleva: una línea por producto, no todo en un párrafo
        $productos = static::partirDescripcion($fila['descripcion'] ?? '');
        if ($productos) {
            $t .= "*Tu pedido:*\n";
            foreach ($productos as $p) {
                $t .= "\u{2022} {$p}\n";
            }
            $t .= "\n";
        }

        // Pago y entrega, cada dato en su renglón
        $cobrar = (float) ($fila['cobrar'] ?? 0);
        $t .= $cobrar > 0
            ? "*A pagar al recibir:* $" . number_format($cobrar, 2) . "\n"
            : "*Pago:* Ya est\u{E1} cancelado \u{2705}\n";

        $entrega = trim((string) \App\Models\Setting::get('envio_tiempo', '24 horas h\u{E1}biles')) ?: '24 horas hábiles';
        $t .= "*Entrega:* {$entrega}\n\n";

        // El enlace va solo en su renglón: si lleva emoji pegado, WhatsApp lo parte feo.
        $t .= "*Segu\u{ED} tu paquete aqu\u{ED}:*\n"
            . static::enlaceRastreo($fila['telefono'] ?? null) . "\n\n"
            . "Guard\u{E1} este enlace, te sirve para este pedido y para los que vengan \u{1F499}";

        return $t;
    }

    /**
     * Parte "2 Calzoncito Magic talla XXL, 1 paquete de 50 unidades" en líneas
     * separadas y limpia repeticiones tipo "cincuenta 50" que deja el dictado.
     */
    public static function partirDescripcion(?string $texto): array
    {
        $t = trim((string) $texto);
        if ($t === '') return [];

        $numeros = [
            'cincuenta' => '50', 'cuarenta' => '40', 'treinta' => '30', 'veinte' => '20',
            'diez' => '10', 'nueve' => '9', 'ocho' => '8', 'siete' => '7', 'seis' => '6',
            'cinco' => '5', 'cuatro' => '4', 'tres' => '3', 'dos' => '2', 'uno' => '1',
        ];

        $partes = [];
        foreach (preg_split('/\s*[,;]\s*|\s+\+\s+/u', $t) as $p) {
            $p = trim($p, " \t\n\r.-\u{2022}");
            if ($p === '') continue;

            // "cincuenta 50" → "50" (el dictado escribe el número dos veces)
            foreach ($numeros as $palabra => $cifra) {
                $p = preg_replace('/\b' . $palabra . '\s+' . $cifra . '\b/iu', $cifra, $p);
                $p = preg_replace('/\b' . $cifra . '\s+' . $palabra . '\b/iu', $cifra, $p);
            }

            $p = preg_replace('/\s{2,}/u', ' ', $p);
            $p = preg_replace('/\btalla\s+/iu', 'talla ', $p);
            $partes[] = \Illuminate\Support\Str::ucfirst($p);
        }

        return $partes;
    }

    /** Enlace de rastreo con el teléfono ya puesto (no hay nada que escribir). */
    public static function enlaceRastreo(?string $telefono): string
    {
        $base = route('store.rastreo.guia');
        $d = preg_replace('/\D/', '', (string) $telefono);
        if (strlen($d) >= 8) $d = substr($d, -8);

        return strlen($d) === 8 ? $base . '?tel=' . $d : $base;
    }

    /** Convierte a arreglo simple, como lo espera el generador de Excel. */
    public function aFila(): array
    {
        return [
            'nombre'          => $this->nombre,
            'telefono'        => $this->telefono,
            'telefono_recibe' => $this->telefono_recibe,
            'direccion'       => $this->direccion,
            'municipio'       => $this->municipio,
            'departamento'    => $this->departamento,
            'descripcion'     => $this->descripcion,
            'cobrar'          => (float) $this->cobrar,
        ];
    }
}
