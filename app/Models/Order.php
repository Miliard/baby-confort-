<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [
        'customer_name', 'phone', 'address', 'municipio',
        'payment', 'total', 'items', 'status',
    ];

    protected $casts = [
        'items' => 'array',
        'total' => 'decimal:2',
    ];

    public const PAGOS = [
        'transferencia' => 'Transferencia bancaria',
        'efectivo'      => 'Efectivo (contra entrega)',
        'link'          => 'Link de pago',
    ];
}
