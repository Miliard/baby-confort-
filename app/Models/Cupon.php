<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cupon extends Model
{
    protected $table = 'cupones';

    protected $fillable = ['codigo', 'porcentaje', 'activo', 'usos'];

    protected $casts = [
        'activo'     => 'boolean',
        'porcentaje' => 'integer',
        'usos'       => 'integer',
    ];

    // Busca un cupón activo por código (sin distinguir mayúsculas/espacios).
    public static function buscarActivo(?string $codigo): ?self
    {
        $codigo = strtoupper(trim((string) $codigo));
        if ($codigo === '') return null;

        return static::whereRaw('UPPER(codigo) = ?', [$codigo])->where('activo', true)->first();
    }
}
