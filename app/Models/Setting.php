<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = ['key', 'value'];
    public $timestamps = true;

    public static function get(string $key, $default = null)
    {
        $row = static::where('key', $key)->first();
        return $row ? $row->value : $default;
    }

    public static function put(string $key, $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
    }

    // Costo de envío configurable (por defecto 2.50)
    public static function envio(): float
    {
        return (float) static::get('envio', 2.50);
    }

    // Monto de productos a partir del cual el envío es gratis (0 = desactivado)
    public static function envioGratisDesde(): float
    {
        return (float) static::get('envio_gratis_desde', 0);
    }

    // Envío efectivo dado un subtotal de productos (aplica envío gratis si corresponde)
    public static function envioPara(float $subtotal): float
    {
        $desde = static::envioGratisDesde();
        if ($desde > 0 && $subtotal >= $desde) return 0.0;
        return static::envio();
    }
}
