<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    protected $fillable = [
        'name', 'brand', 'price', 'combo_qty', 'combo_price',
        'description', 'image', 'active',
    ];

    protected $casts = [
        'price'       => 'decimal:2',
        'combo_price' => 'decimal:2',
        'active'      => 'boolean',
    ];

    public function sizes(): HasMany
    {
        return $this->hasMany(ProductSize::class);
    }

    /**
     * Calcula el subtotal de N paquetes aplicando el combo.
     * Ej: precio 10, combo 3x25  =>  4 paquetes = 25 + 10 = 35
     */
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
