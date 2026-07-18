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
        'guia', 'estado_envio', 'cupon', 'descuento',
    ];

    protected $casts = [
        'items'     => 'array',
        'subtotal'  => 'decimal:2',
        'shipping'  => 'decimal:2',
        'total'     => 'decimal:2',
        'descuento' => 'decimal:2',
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

                // "ENTREGADO" (no "entrega", que aparece en "fecha estimada de entrega")
                if (str_contains($t, 'ENTREGADO')) return 4;
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

    // Formato $: quita ".00" si es entero (8.5 -> $8.50, 17 -> $17)
    private function mnyWa($n): string
    {
        $s = number_format((float) $n, 2, '.', '');
        $s = rtrim(rtrim($s, '0'), '.');
        return '$' . $s;
    }

    // Mensaje de "Orden de Envío" para WhatsApp (el que le llega al negocio).
    public function mensajeWhatsApp(): string
    {
        $t  = "\u{1F4E6} Orden de Env\u{00ED}o: \u{1F69A}\n\n";
        $t .= "\u{2705} Nombre completo:\n{$this->customer_name}\n\n";
        $t .= "\u{2705} Direcci\u{00F3}n exacta:\n";
        $t .= "Municipio: {$this->municipio}\n";
        $t .= "{$this->address}\n\n";
        $t .= "\u{2705} Producto(s):\n";
        foreach (($this->items ?? []) as $it) {
            $linea = "{$it['cantidad']} {$it['producto']} {$it['talla']} " . $this->mnyWa($it['precio'] ?? 0);
            if (($it['cantidad'] ?? 1) > 1) {
                $linea .= " (" . $this->mnyWa($it['subtotal'] ?? 0) . ")";
            }
            $t .= $linea . "\n";
        }
        if ($this->descuento > 0) {
            $t .= "\n\u{1F3AB} Cup\u{00F3}n {$this->cupon}: -$" . number_format($this->descuento, 2, '.', '');
        }
        $t .= "\n\u{2705} Costo de env\u{00ED}o: $" . number_format($this->shipping, 2, '.', '') . "\n\n";
        $pago = self::PAGOS[$this->payment] ?? $this->payment;
        $t .= "\u{1F4B3} Forma de pago: {$pago}\n\n";
        $t .= "\u{1F4B0} Total a pagar: $" . number_format($this->total, 2, '.', '') . "\n\n";
        $t .= "\u{2728} \u{00A1}Gracias por tu preferencia! Tu pedido estar\u{00E1} en camino muy pronto.";
        return $t;
    }

    public function whatsappUrl(): string
    {
        return 'https://wa.me/' . config('babyconfort.whatsapp') . '?text=' . rawurlencode($this->mensajeWhatsApp());
    }

    // Mensaje corto para la alerta de Telegram al negocio.
    public function mensajeTelegram(): string
    {
        $lineas = collect($this->items ?? [])->map(function ($it) {
            return "\u{2022} " . ($it['cantidad'] ?? '') . "x " . ($it['producto'] ?? '') .
                " (" . ($it['talla'] ?? '') . ") - $" . number_format($it['subtotal'] ?? 0, 2);
        })->implode("\n");

        $pago = self::PAGOS[$this->payment] ?? $this->payment;
        $desc = $this->descuento > 0
            ? "\n\u{1F3AB} Cup\u{00F3}n {$this->cupon}: -$" . number_format($this->descuento, 2)
            : '';

        return "\u{1F6D2} NUEVO PEDIDO #{$this->id}\n\n"
            . "\u{1F464} {$this->customer_name}\n"
            . "\u{1F4DE} {$this->phone}\n"
            . "\u{1F4CD} {$this->municipio}\n{$this->address}\n\n"
            . "{$lineas}{$desc}\n"
            . "\u{1F69A} Env\u{00ED}o: $" . number_format($this->shipping, 2) . "\n"
            . "\u{1F4B3} {$pago}\n"
            . "\u{1F4B0} TOTAL: $" . number_format($this->total, 2);
    }

    // Envía una alerta a Telegram si están configurados el token y el chat.
    public function notificarTelegram(): void
    {
        $token = trim((string) Setting::get('telegram_token', ''));
        $chat  = trim((string) Setting::get('telegram_chat', ''));
        if ($token === '' || $chat === '') return;

        try {
            Http::timeout(6)->get("https://api.telegram.org/bot{$token}/sendMessage", [
                'chat_id' => $chat,
                'text'    => $this->mensajeTelegram(),
            ]);
        } catch (\Throwable $e) {
            // No interrumpir el pedido si Telegram falla.
        }
    }
}
