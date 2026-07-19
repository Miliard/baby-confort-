<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Categoria extends Model
{
    protected $table = 'categorias';

    protected $fillable = ['slug', 'nombre', 'icono', 'orden', 'activo'];

    protected $casts = [
        'activo' => 'boolean',
        'orden'  => 'integer',
    ];

    public function productos()
    {
        return $this->hasMany(Product::class, 'categoria', 'slug');
    }

    // Genera un slug único a partir del nombre (para categorías nuevas).
    public static function generarSlug(string $nombre): string
    {
        $base = \Illuminate\Support\Str::slug($nombre) ?: 'categoria';
        $slug = $base;
        $i = 2;
        while (static::where('slug', $slug)->exists()) {
            $slug = $base . '-' . $i;
            $i++;
        }
        return $slug;
    }
}
