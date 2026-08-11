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

        $t  = $primero ? "\u{A1}Hola {$primero}! \u{1F499} Gracias por tu pedido.\n" : "\u{A1}Gracias por tu pedido! \u{1F499}\n";

        if (trim((string) ($fila['descripcion'] ?? '')) !== '') {
            $t .= "\u{1F4E6} " . trim($fila['descripcion']) . "\n";
        }

        $cobrar = (float) ($fila['cobrar'] ?? 0);
        $t .= $cobrar > 0
            ? "\u{1F4B5} Al recibir cancel\u{E1}s $" . number_format($cobrar, 2) . "\n"
            : "\u{2705} Ya est\u{E1} pagado\n";

        // El enlace ya lleva su teléfono: el cliente solo toca y ve su paquete.
        $enlace = static::enlaceRastreo($fila['telefono'] ?? null);

        $t .= "\nYa lo estamos preparando. Entregamos en 24 horas h\u{E1}biles.\n\n"
            . "Pod\u{E9}s ver en qu\u{E9} va tu paquete cuando quer\u{E1}s, solo tocando aqu\u{ED}:\n"
            . "\u{1F449} " . $enlace . "\n\n"
            . "Guard\u{E1} este enlace: te sirve para este pedido y para los que vengan. \u{1F499}";

        return $t;
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
