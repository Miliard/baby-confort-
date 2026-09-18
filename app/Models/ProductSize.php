<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ProductSize extends Model
{
    protected $fillable = ['product_id', 'size', 'price', 'price_before', 'weight', 'unidades', 'details', 'image', 'image_upload', 'fotos_uso', 'quantity', 'combo_qty', 'combo_price'];

    protected $casts = [
        'price'       => 'decimal:2',
        'combo_price' => 'decimal:2',
        'quantity'    => 'integer',
        'unidades'    => 'integer',
        'fotos_uso'   => 'array',
    ];

    /**
     * Las fotos de cómo queda puesta esta talla, con dirección completa.
     *
     * Completa y no relativa porque Meta las va a buscar a nuestro sitio desde
     * afuera: con una ruta relativa no llegaría a ninguna parte.
     *
     * Solo se usan en el chat. La página no las muestra.
     */
    public function fotosUsoUrls(): array
    {
        $rutas = $this->fotos_uso;

        if (! is_array($rutas) || ! $rutas) return [];

        $urls = [];

        foreach ($rutas as $r) {
            $r = trim((string) $r);
            if ($r === '') continue;

            $urls[] = str_starts_with($r, 'http')
                ? $r
                : url('/storage/' . ltrim($r, '/'));
        }

        return $urls;
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    // Foto de la talla: si subiste una, se usa esa; si no, el link. Si no hay, null.
    public function imageUrl(): ?string
    {
        if (!empty($this->image_upload)) return '/storage/' . ltrim($this->image_upload, '/');
        if (!empty($this->image)) return str_starts_with($this->image, 'http') ? $this->image : '/storage/' . ltrim($this->image, '/');
        return null;
    }

    public function subtotalPara(int $cantidad): float
    {
        if ($this->combo_qty && $this->combo_qty > 0 && $this->combo_price) {
            $grupos = intdiv($cantidad, $this->combo_qty);
            $resto  = $cantidad % $this->combo_qty;
            return $grupos * (float) $this->combo_price + $resto * (float) $this->price;
        }
        return $cantidad * (float) $this->price;
    }
}
