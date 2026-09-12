<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

/**
 * Un texto que se manda con un toque, en vez de escribirlo por décima vez.
 */
class RespuestaRapida extends Model
{
    protected $table = 'respuestas_rapidas';

    protected $fillable = ['titulo', 'texto', 'orden', 'activa'];

    protected $casts = [
        'activa' => 'boolean',
        'orden'  => 'integer',
    ];

    /**
     * Las que se muestran en el chat.
     *
     * Si la tabla todavía no existe (despliegue a medias), devuelve vacío en
     * lugar de tumbar la pantalla de mensajes, que es lo que de verdad importa.
     */
    public static function paraElChat()
    {
        try {
            if (! Schema::hasTable('respuestas_rapidas')) return collect();

            return static::where('activa', true)
                ->orderBy('orden')
                ->orderBy('titulo')
                ->get();
        } catch (\Throwable $e) {
            return collect();
        }
    }
}
