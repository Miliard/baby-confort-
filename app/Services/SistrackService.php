<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Setting;
use Illuminate\Support\Facades\Http;

/**
 * Crea guías de envío en Express El Salvador (Sistrack) desde un pedido.
 *
 * Configuración (en Admin → Configuración → Guías Express):
 *  - sistrack_token:     token de API (Bearer) de tu cuenta de Sistrack.
 *  - sistrack_sender_id: tu ID de remitente (Baby-Confort) dentro de Sistrack.
 *  - sistrack_dominio:   subdominio del courier (por defecto: expresselsalvador).
 *
 * Flujo: crea el destinatario con los datos del cliente y luego la orden de envío.
 * El número devuelto se guarda como guía del pedido, con lo que el rastreo
 * automático de la tienda empieza a funcionar solo.
 */
class SistrackService
{
    public static function configurado(): bool
    {
        return trim((string) Setting::get('sistrack_token', '')) !== ''
            && trim((string) Setting::get('sistrack_sender_id', '')) !== '';
    }

    public static function baseUrl(): string
    {
        $dominio = trim((string) Setting::get('sistrack_dominio', 'expresselsalvador')) ?: 'expresselsalvador';
        return "https://{$dominio}.sistrack.net";
    }

    // Descripción sugerida del paquete a partir de los productos del pedido.
    public static function descripcionDe(Order $order): string
    {
        $texto = collect($order->items ?? [])
            ->map(fn ($it) => ((int) ($it['cantidad'] ?? 1)) . ' ' . ($it['producto'] ?? '') . ' ' . ($it['talla'] ?? ''))
            ->implode(', ');
        return trim($texto) !== '' ? trim($texto) : 'Productos para bebé';
    }

    /**
     * Crea la guía. Devuelve ['ok' => true, 'guia' => '...'] o ['ok' => false, 'error' => '...'].
     */
    public function crearGuia(Order $order, string $descripcion, float $cobrar): array
    {
        if (! static::configurado()) {
            return ['ok' => false, 'error' => 'Falta configurar el token y el remitente en Configuración → Guías Express.'];
        }

        $token = trim((string) Setting::get('sistrack_token', ''));
        $base  = static::baseUrl();

        // Teléfono con código de país (+503 para números de 8 dígitos).
        $tel = preg_replace('/\D/', '', (string) $order->phone);
        if (strlen($tel) === 8) $tel = '503' . $tel;
        $tel = '+' . $tel;

        // Municipio viene como "Municipio, Departamento".
        $partes = array_map('trim', explode(',', (string) $order->municipio));
        $ciudad = $partes[0] ?: 'San Salvador';
        $depto  = $partes[1] ?? $ciudad;

        try {
            // 1) Crear el destinatario con los datos del cliente.
            $rd = Http::withToken($token)->acceptJson()->timeout(20)
                ->post("{$base}/api/recipient/create", [
                    'name'           => $order->customer_name,
                    'telephone'      => $tel,
                    'email'          => 'clientes@baby-confort.shop',
                    'id_number'      => '00000000',
                    'address_line_1' => mb_substr((string) $order->address, 0, 190),
                    'address_line_2' => '-',
                    'suburb'         => $ciudad,
                    'city'           => $ciudad,
                    'state'          => $depto,
                    'postal_code'    => '00000',
                    'country'        => 'SV',
                ]);

            if (! $rd->successful()) {
                return ['ok' => false, 'error' => 'Sistrack rechazó el destinatario (HTTP ' . $rd->status() . '): ' . mb_substr($rd->body(), 0, 300)];
            }

            $jd = $rd->json();
            $recipientId = data_get($jd, 'id') ?? data_get($jd, 'recipient.id') ?? data_get($jd, 'data.id');
            if (! $recipientId) {
                return ['ok' => false, 'error' => 'Sistrack no devolvió el ID del destinatario: ' . mb_substr($rd->body(), 0, 300)];
            }

            // 2) Crear la orden de envío.
            $obs = $cobrar > 0
                ? 'COBRAR AL ENTREGAR: $' . number_format($cobrar, 2) . ' (' . (Order::PAGOS[$order->payment] ?? $order->payment) . ')'
                : 'PAGADO — no cobrar al entregar';
            $obs .= ' · Pedido #' . $order->id . ' Baby-Confort';

            $ro = Http::withToken($token)->acceptJson()->timeout(20)
                ->post("{$base}/api/order/create", [
                    'sender_id'        => (int) Setting::get('sistrack_sender_id'),
                    'recipient_id'     => (int) $recipientId,
                    'description'      => mb_substr($descripcion, 0, 250),
                    'weight'           => 1,
                    'price_per_weight' => 0,
                    'declared_value'   => (float) $order->total,
                    'observations'     => mb_substr($obs, 0, 250),
                ]);

            if (! $ro->successful()) {
                return ['ok' => false, 'error' => 'Sistrack rechazó la orden (HTTP ' . $ro->status() . '): ' . mb_substr($ro->body(), 0, 300)];
            }

            $jo = $ro->json();
            $guia = data_get($jo, 'order_id') ?? data_get($jo, 'id')
                 ?? data_get($jo, 'order.order_id') ?? data_get($jo, 'order.id')
                 ?? data_get($jo, 'data.order_id') ?? data_get($jo, 'data.id');

            if (! $guia) {
                return ['ok' => false, 'error' => 'La orden se creó pero no se pudo leer el número de guía: ' . mb_substr($ro->body(), 0, 300)];
            }

            $order->update(['guia' => (string) $guia]);

            return ['ok' => true, 'guia' => (string) $guia];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => 'Error de conexión con Sistrack: ' . $e->getMessage()];
        }
    }
}
