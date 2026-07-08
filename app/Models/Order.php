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
        return static::etapaDeGuia($this->guia);
    }

    // Lee el estado real de Express (Sistrack) a partir de una guía. Con caché.
    public static function etapaDeGuia(?string $guia): int
    {
        if (empty($guia)) {
            return 1;
        }

        return Cache::remember('track_' . $guia, 120, function () use ($guia) {
            try {
                $raw = Http::timeout(8)->get('https://expresselsalvador.sistrack.net/track/' . $guia)->body();
                // Reemplaza etiquetas por espacios (para no partir "EN RUTA") y normaliza.
                $t = strtoupper(preg_replace('/<[^>]+>/', ' ', $raw));
                $t = strtr($t, ['Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U']);
                $t = preg_replace('/\s+/', ' ', $t);

                if (str_contains($t, 'ENTREGA')) return 4;
                foreach (['EN RUTA', 'EN CAMINO', 'CAMINO', 'PILOTO', 'REPARTO', 'TRANSITO', 'DISTRIBUCION', 'SALIO'] as $k) {
                    if (str_contains($t, $k)) return 3;
                }
                if (str_contains($t, 'RECOLECT') || str_contains($t, 'BODEGA') || str_contains($t, 'RECIBID')) return 2;
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
