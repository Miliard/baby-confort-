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

    protected $fillable = ['guia', 'ruta', 'nombre', 'telefono'];

    /** Enlace de seguimiento de esta guía. */
    public function enlaceRastreo(): string
    {
        return route('store.rastreo.guia') . '?guia=' . $this->guia;
    }

    /** Enlace de WhatsApp con el mensaje listo (si se conoce el teléfono). */
    public function whatsapp(): ?string
    {
        $d = preg_replace('/\D/', '', (string) $this->telefono);
        if (strlen($d) === 11 && str_starts_with($d, '503')) $d = substr($d, 3);
        if (strlen($d) !== 8) return null;

        $msg = "\u{A1}Sigue tu pedido, Baby-Confort!\n\nGu\u{ED}a {$this->guia}\nRastr\u{E9}alo aqu\u{ED}: "
             . $this->enlaceRastreo()
             . "\n\nAh\u{ED} pod\u{E9}s ver la foto de tu paquete. \u{A1}Gracias por tu preferencia!";

        return 'https://wa.me/503' . $d . '?text=' . rawurlencode($msg);
    }

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
