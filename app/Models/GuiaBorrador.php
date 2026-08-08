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
        'municipio', 'departamento', 'descripcion', 'cobrar',
    ];

    protected $casts = ['cobrar' => 'decimal:2'];

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
