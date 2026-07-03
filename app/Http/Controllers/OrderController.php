<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function store(Request $request)
    {
        // 1) Validacion: TODOS los campos del cliente son obligatorios,
        //    incluido el municipio (para evitar problemas con la direccion).
        $data = $request->validate([
            'customer_name'      => ['required', 'string', 'max:255'],
            'phone'              => ['required', 'string', 'max:50'],
            'address'            => ['required', 'string', 'max:1000'],
            'municipio'          => ['required', 'string', 'max:255'],
            'payment'            => ['required', 'in:transferencia,efectivo,link'],
            'items'              => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer'],
            'items.*.size'       => ['required', 'string'],
            'items.*.cantidad'   => ['required', 'integer', 'min:1'],
        ], [], [
            'customer_name' => 'nombre',
            'phone'         => 'teléfono',
            'address'       => 'dirección',
            'municipio'     => 'municipio',
        ]);

        // 2) Recalculamos el total en el servidor (no confiamos en el navegador)
        $items = [];
        $total = 0;

        foreach ($data['items'] as $line) {
            $product = Product::find($line['product_id']);
            if (! $product || ! $product->active) {
                continue;
            }
            $cantidad = (int) $line['cantidad'];
            $subtotal = $product->subtotalPara($cantidad);
            $total   += $subtotal;

            $items[] = [
                'producto' => $product->name,
                'talla'    => $line['size'],
                'cantidad' => $cantidad,
                'subtotal' => round($subtotal, 2),
            ];
        }

        if (empty($items)) {
            return response()->json(['ok' => false, 'error' => 'El carrito está vacío.'], 422);
        }

        // 3) Guardar el pedido (se ve en el panel /admin)
        $order = Order::create([
            'customer_name' => $data['customer_name'],
            'phone'         => $data['phone'],
            'address'       => $data['address'],
            'municipio'     => $data['municipio'],
            'payment'       => $data['payment'],
            'total'         => round($total, 2),
            'items'         => $items,
            'status'        => 'nuevo',
        ]);

        // 4) Armar el mensaje de WhatsApp
        $numero = config('babyconfort.whatsapp');
        $texto  = $this->mensajeWhatsApp($order);
        $url    = 'https://wa.me/' . $numero . '?text=' . rawurlencode($texto);

        return response()->json([
            'ok'           => true,
            'folio'        => $order->id,
            'whatsapp_url' => $url,
        ]);
    }

    private function mensajeWhatsApp(Order $order): string
    {
        $t  = "*Nuevo pedido - Baby-Confort*\n";
        $t .= "Folio: #{$order->id}\n\n";
        $t .= "*Productos:*\n";
        foreach ($order->items as $it) {
            $t .= "• {$it['cantidad']}x {$it['producto']} (Talla {$it['talla']}) — $"
                . number_format($it['subtotal'], 2) . "\n";
        }
        $t .= "\n*Total: $" . number_format($order->total, 2) . "*\n\n";
        $t .= "*Cliente:*\n";
        $t .= "Nombre: {$order->customer_name}\n";
        $t .= "Teléfono: {$order->phone}\n";
        $t .= "Dirección: {$order->address}\n";
        $t .= "Municipio: {$order->municipio}\n";
        $t .= "Forma de pago: " . (Order::PAGOS[$order->payment] ?? $order->payment) . "\n";
        return $t;
    }
}
