<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Revendedor extends Model
{
    protected $table = 'revendedores';

    protected $fillable = ['nombre', 'telefono', 'codigo', 'porcentaje', 'activo'];

    protected $casts = [
        'activo'     => 'boolean',
        'porcentaje' => 'integer',
    ];

    public function pedidos()
    {
        return $this->hasMany(Order::class, 'revendedor', 'codigo');
    }

    // Busca un revendedor activo por código (sin distinguir mayúsculas/espacios).
    public static function buscarActivo(?string $codigo): ?self
    {
        $codigo = strtoupper(trim((string) $codigo));
        if ($codigo === '') return null;

        try {
            if (! \Illuminate\Support\Facades\Schema::hasTable('revendedores')) return null;
            return static::whereRaw('UPPER(codigo) = ?', [$codigo])->where('activo', true)->first();
        } catch (\Throwable $e) {
            return null;
        }
    }

    // Genera un código único a partir del nombre.
    public static function generarCodigo(string $nombre): string
    {
        $base = strtoupper(\Illuminate\Support\Str::slug($nombre, '')) ?: 'REV';
        $base = substr($base, 0, 12);
        $codigo = $base;
        $i = 2;
        while (static::where('codigo', $codigo)->exists()) {
            $codigo = $base . $i;
            $i++;
        }
        return $codigo;
    }
}
