<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

/**
 * Una foto guardada para mandar seguido desde el chat.
 */
class WaFoto extends Model
{
    protected $table = 'wa_fotos';

    protected $fillable = ['titulo', 'pie', 'ruta', 'orden', 'activa'];

    protected $casts = [
        'activa' => 'boolean',
        'orden'  => 'integer',
    ];

    /** Dirección pública, tal como la sirve el sitio. */
    public function url(): ?string
    {
        return filled($this->ruta) ? '/storage/' . ltrim($this->ruta, '/') : null;
    }

    /** La dirección completa, que es la que Meta necesita para ir a buscarla. */
    public function urlCompleta(): ?string
    {
        $u = $this->url();
        return $u ? url($u) : null;
    }

    /** Las que se ofrecen en el chat. */
    public static function paraElChat()
    {
        try {
            if (! Schema::hasTable('wa_fotos')) return collect();

            return static::where('activa', true)
                ->orderBy('orden')
                ->orderBy('titulo')
                ->get();
        } catch (\Throwable $e) {
            return collect();
        }
    }
}
