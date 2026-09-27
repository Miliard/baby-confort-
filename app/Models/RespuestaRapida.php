<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

/**
 * Un texto que se manda con un toque, en vez de escribirlo por décima vez.
 *
 * Puede llevar varias fotos. Al mandarla salen todas seguidas, y el texto va
 * de pie de la primera.
 */
class RespuestaRapida extends Model
{
    protected $table = 'respuestas_rapidas';

    protected $fillable = ['titulo', 'texto', 'imagen', 'imagenes', 'orden', 'activa'];

    protected $casts = [
        'activa'   => 'boolean',
        'orden'    => 'integer',
        'imagenes' => 'array',
    ];

    protected static function booted(): void
    {
        // La columna vieja `imagen` siempre lleva la primera foto de la lista,
        // para que lo que todavía la lee (y la vuelta atrás) siga funcionando.
        static::saving(function (self $r) {
            if ($r->isDirty('imagenes')) {
                $lista = array_values(array_filter((array) ($r->imagenes ?? [])));
                $r->imagenes = $lista ?: null;
                $r->imagen   = $lista[0] ?? null;
            }
        });
    }

    /** Las rutas de todas las fotos, en el orden en que se mandan. */
    public function fotos(): array
    {
        $lista = array_values(array_filter((array) ($this->imagenes ?? []), 'filled'));

        if (! $lista && filled($this->imagen ?? null)) {
            $lista = [$this->imagen];
        }

        return $lista;
    }

    /** ¿Lleva foto? */
    public function tieneFoto(): bool
    {
        return count($this->fotos()) > 0;
    }

    /** Cuántas fotos lleva. */
    public function cuantasFotos(): int
    {
        return count($this->fotos());
    }

    /** Dirección pública de la primera foto, tal como la sirve el sitio. */
    public function url(): ?string
    {
        return $this->urls()[0] ?? null;
    }

    /** La dirección completa de la primera foto. */
    public function urlCompleta(): ?string
    {
        return $this->urlsCompletas()[0] ?? null;
    }

    /** Direcciones públicas de todas las fotos. */
    public function urls(): array
    {
        return array_map(fn ($p) => '/storage/' . ltrim($p, '/'), $this->fotos());
    }

    /** Direcciones completas de todas las fotos: las que Meta va a buscar. */
    public function urlsCompletas(): array
    {
        return array_map(fn ($u) => url($u), $this->urls());
    }

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
