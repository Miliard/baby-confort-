<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;
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

    public const ICONOS_CAT = [
        'bebe' => '👶', 'accesorios' => '🍼', 'mujer' => '🌸', 'adulto' => '🧑',
    ];

    // [slug => nombre] de TODAS las categorías. Lee de la tabla; si falla, usa las 4 por defecto.
    public static function categoriaLabels(): array
    {
        try {
            if (Schema::hasTable('categorias')) {
                $rows = Categoria::orderBy('orden')->orderBy('id')->pluck('nombre', 'slug')->all();
                if (! empty($rows)) return $rows;
            }
        } catch (\Throwable $e) {
        }
        return self::CATEGORIAS;
    }

    public static function categoriaLabel(?string $slug): ?string
    {
        if (! $slug) return null;
        return self::categoriaLabels()[$slug] ?? (self::CATEGORIAS[$slug] ?? $slug);
    }

    // Categorías activas para mostrar en la tienda (colección con ->slug, ->nombre, ->icono).
    // Si $soloSlugs se pasa, filtra a esas. Con fallback total a las 4 por defecto.
    public static function categoriasTienda(?array $soloSlugs = null)
    {
        try {
            if (Schema::hasTable('categorias') && Categoria::query()->exists()) {
                $q = Categoria::where('activo', true)->orderBy('orden')->orderBy('id');
                if ($soloSlugs !== null) $q->whereIn('slug', $soloSlugs);
                return $q->get();
            }
        } catch (\Throwable $e) {
        }

        return collect(self::CATEGORIAS)
            ->map(fn ($nombre, $slug) => (object) [
                'slug'   => $slug,
                'nombre' => $nombre,
                'icono'  => self::ICONOS_CAT[$slug] ?? '🛍️',
            ])
            ->when($soloSlugs !== null, fn ($c) => $c->filter(fn ($x) => in_array($x->slug, $soloSlugs)))
            ->values();
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
