<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class Order extends Model
{
    protected $fillable = [
        'customer_name', 'phone', 'address', 'municipio',
        'payment', 'subtotal', 'shipping', 'total', 'items', 'status',
        'guia', 'estado_envio',
    ];

    protected $casts = [
        'items'    => 'array',
        'subtotal' => 'decimal:2',
        'shipping' => 'decimal:2',
        'total'    => 'decimal:2',
    ];

    public const PAGOS = [
        'transferencia' => 'Transferencia bancaria',
        'efectivo'      => 'Efectivo (contra entrega)',
        'link'          => 'Link de pago',
    ];

    // Etapas de la barra de progreso
    public const ETAPAS = [
        1 => 'Pedido confirmado',
        2 => 'Recolectado',
        3 => 'En camino',
        4 => 'Entregado',
    ];

    // Devuelve la etapa actual (1-4). Si hay override manual lo usa; si hay guía,
    // lee el estado real de Express (Sistrack) con caché; si no, etapa 1.
    public function etapaEnvio(): int
    {
        if ($this->estado_envio) {
            return (int) $this->estado_envio;
        }
        if (empty($this->guia)) {
            return 1;
        }

        return Cache::remember('track_' . $this->guia, 600, function () {
            try {
                $html = strtoupper(
                    Http::timeout(8)->get('https://expresselsalvador.sistrack.net/track/' . $this->guia)->body()
                );
                if (str_contains($html, 'ENTREGA')) return 4;
                if (str_contains($html, 'EN RUTA') || str_contains($html, 'CAMINO')) return 3;
                if (str_contains($html, 'RECOLECT') || str_contains($html, 'BODEGA')) return 2;
                return 1;
            } catch (\Throwable $e) {
                return 1;
            }
        });
    }

    public function urlRastreoExpress(): ?string
    {
        return $this->guia ? 'https://expresselsalvador.sistrack.net/track/' . $this->guia : null;
    }
}
