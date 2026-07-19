<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Product extends Model
{
    protected $fillable = [
        'orden', 'name', 'brand', 'categoria', 'description', 'image', 'image_upload',
        'gallery', 'features', 'made_in', 'badge', 'oferta', 'stock_warning', 'active',
    ];

    public const CATEGORIAS = [
        'bebe'        => 'Para bebé',
        'accesorios'  => 'Accesorios para bebé',
        'mujer'       => 'Para mujer',
        'adulto'      => 'Para adulto',
    ];

    // Nombres de categoría que se muestran (editables desde Configuración).
    // Las claves (bebe, accesorios…) NO cambian; solo el texto visible.
    public static function categoriaLabels(): array
    {
        $labels = [];
        foreach (self::CATEGORIAS as $slug => $default) {
            $val = Setting::get('cat_' . $slug, $default);
            $labels[$slug] = ($val !== null && trim((string) $val) !== '') ? $val : $default;
        }
        return $labels;
    }

    public static function categoriaLabel(?string $slug): ?string
    {
        if (! $slug) return null;
        return self::categoriaLabels()[$slug] ?? $slug;
    }

    protected $casts = [
        'active'   => 'boolean',
        'gallery'  => 'array',
        'features' => 'array',
    ];

    public function sizes(): HasMany
    {
        return $this->hasMany(ProductSize::class);
    }

    public function precioDesde(): float
    {
        return (float) ($this->sizes->min('price') ?? 0);
    }

    // Convierte una ruta guardada o un link en URL utilizable
    public static function urlDe(?string $img): ?string
    {
        if (empty($img)) return null;
        return str_starts_with($img, 'http') ? $img : '/storage/' . ltrim($img, '/');
    }

    public function imageUrl(): ?string
    {
        // Si subiste una foto propia, se usa esa; si no, el link.
        if (!empty($this->image_upload)) return '/storage/' . ltrim($this->image_upload, '/');
        return static::urlDe($this->image);
    }

    // Todas las fotos: la principal + la galería
    public function galleryUrls(): array
    {
        $urls = [];
        if ($this->image) $urls[] = $this->imageUrl();
        foreach (($this->gallery ?? []) as $g) {
            $u = static::urlDe($g);
            if ($u) $urls[] = $u;
        }
        return $urls;
    }
}
