<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ProductSize extends Model
{
    protected $fillable = ['product_id', 'size', 'price', 'price_before', 'weight', 'unidades', 'details', 'image', 'image_upload', 'quantity', 'combo_qty', 'combo_price'];

    protected $casts = [
        'price'       => 'decimal:2',
        'combo_price' => 'decimal:2',
        'quantity'    => 'integer',
        'unidades'    => 'integer',
    ];

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
