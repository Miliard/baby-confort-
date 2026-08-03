<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

/**
 * Foto del paquete asociada a un número de guía.
 * La guía se lee del código QR de la etiqueta, así la foto siempre queda
 * emparejada con el envío correcto y el cliente la ve en su página de rastreo.
 */
class GuiaFoto extends Model
{
    protected $table = 'guia_fotos';

    protected $fillable = ['guia', 'ruta', 'nombre'];

    /** Nombre del cliente asociado a una guía (leído de la etiqueta), si se tiene. */
    public static function nombreDeGuia(?string $guia): ?string
    {
        $g = static::deGuia($guia)->firstWhere(fn ($f) => filled($f->nombre));
        return $g?->nombre;
    }

    public function url(): string
    {
        return '/storage/' . ltrim($this->ruta, '/');
    }

    /** Fotos de una guía (vacío si la tabla aún no existe). */
    public static function deGuia(?string $guia)
    {
        $guia = trim((string) $guia);
        if ($guia === '') return collect();

        try {
            if (! Schema::hasTable('guia_fotos')) return collect();
            return static::where('guia', $guia)->orderBy('id')->get();
        } catch (\Throwable $e) {
            return collect();
        }
    }
}
