<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Schema;

/**
 * Una etiqueta para clasificar conversaciones.
 *
 * Propias del panel, no las del teléfono: Meta no expone esas a la API.
 */
class WaEtiqueta extends Model
{
    protected $table = 'wa_etiquetas';

    protected $fillable = ['nombre', 'color', 'orden'];

    protected $casts = ['orden' => 'integer'];

    /** Los colores que se pueden elegir, con su valor real. */
    public const COLORES = [
        'verde'    => '#2e9e6b',
        'azul'     => '#4aa3df',
        'amarillo' => '#d4a017',
        'rojo'     => '#e5695f',
        'morado'   => '#8b5cf6',
        'gris'     => '#64748b',
    ];

    public function conversaciones(): BelongsToMany
    {
        return $this->belongsToMany(
            WaConversacion::class,
            'wa_conversacion_etiqueta',
            'etiqueta_id',
            'conversacion_id'
        );
    }

    public function hex(): string
    {
        return static::COLORES[$this->color] ?? static::COLORES['gris'];
    }

    /**
     * Todas, ordenadas.
     *
     * Devuelve vacío si la tabla aún no existe, para que un despliegue a medias
     * no tumbe la pantalla de mensajes, que es la que de verdad importa.
     */
    public static function todas()
    {
        try {
            if (! Schema::hasTable('wa_etiquetas')) return collect();

            return static::orderBy('orden')->orderBy('nombre')->get();
        } catch (\Throwable $e) {
            return collect();
        }
    }
}
