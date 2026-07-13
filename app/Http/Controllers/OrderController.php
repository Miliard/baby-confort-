<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function store(Request $request)
    {
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

        $items = [];
        $subtotal = 0;

        foreach ($data['items'] as $line) {
            $product = Product::with('sizes')->find($line['product_id']);
            if (! $product || ! $product->active) continue;

            $size = $product->sizes->firstWhere('size', $line['size']);
            if (! $size) continue;

            $cantidad = (int) $line['cantidad'];
            $lineTotal = $size->subtotalPara($cantidad);
            $subtotal += $lineTotal;

            $items[] = [
                'producto' => $product->name,
                'talla'    => $size->size,
                'cantidad' => $cantidad,
                'precio'   => (float) $size->price,   // precio unitario de la talla
                'subtotal' => round($lineTotal, 2),
            ];
        }

        if (empty($items)) {
            return response()->json(['ok' => false, 'error' => 'El carrito está vacío.'], 422);
        }

        // Cupón: se re-valida en el servidor (nunca se confía en el navegador).
        $descuento   = 0;
        $cuponCodigo = null;
        $cupon = \App\Models\Cupon::buscarActivo($request->input('cupon'));
        if ($cupon) {
            $descuento   = round($subtotal * ($cupon->porcentaje / 100), 2);
            $cuponCodigo = $cupon->codigo;
            $cupon->increment('usos');
        }

        $shipping = Setting::envioPara($subtotal);
        $total    = max(0, $subtotal - $descuento) + $shipping;

        $order = Order::create([
            'customer_name' => $data['customer_name'],
            'phone'         => $data['phone'],
            'address'       => $data['address'],
            'municipio'     => $data['municipio'],
            'payment'       => $data['payment'],
            'subtotal'      => round($subtotal, 2),
            'shipping'      => round($shipping, 2),
            'total'         => round($total, 2),
            'cupon'         => $cuponCodigo,
            'descuento'     => $descuento,
            'items'         => $items,
            'status'        => 'nuevo',
        ]);

        $url = 'https://wa.me/' . config('babyconfort.whatsapp')
             . '?text=' . rawurlencode($this->mensajeWhatsApp($order));

        return response()->json(['ok' => true, 'folio' => $order->id, 'whatsapp_url' => $url]);
    }

    // Formato $: quita ".00" si es entero (8.5 -> $8.50, 17 -> $17)
    private function mny($n): string
    {
        $s = number_format((float) $n, 2, '.', '');
        $s = rtrim(rtrim($s, '0'), '.');
        return '$' . $s;
    }

    private function mensajeWhatsApp(Order $order): string
    {
        $t  = "\u{1F4E6} Orden de Env\u{00ED}o: \u{1F69A}\n\n";
        $t .= "\u{2705} Nombre completo:\n{$order->customer_name}\n\n";
        $t .= "\u{2705} Direcci\u{00F3}n exacta:\n";
        $t .= "Municipio: {$order->municipio}\n";
        $t .= "{$order->address}\n\n";
        $t .= "\u{2705} Producto(s):\n";
        foreach ($order->items as $it) {
            $linea = "{$it['cantidad']} {$it['producto']} {$it['talla']} " . $this->mny($it['precio']);
            if ($it['cantidad'] > 1) {
                $linea .= " (" . $this->mny($it['subtotal']) . ")";
            }
            $t .= $linea . "\n";
        }
        if ($order->descuento > 0) {
            $t .= "\n\u{1F3AB} Cup\u{00F3}n {$order->cupon}: -$" . number_format($order->descuento, 2, '.', '');
        }
        $t .= "\n\u{2705} Costo de env\u{00ED}o: $" . number_format($order->shipping, 2, '.', '') . "\n\n";
        $pago = \App\Models\Order::PAGOS[$order->payment] ?? $order->payment;
        $t .= "\u{1F4B3} Forma de pago: {$pago}\n\n";
        $t .= "\u{1F4B0} Total a pagar: $" . number_format($order->total, 2, '.', '') . "\n\n";
        $t .= "\u{2728} \u{00A1}Gracias por tu preferencia! Tu pedido estar\u{00E1} en camino muy pronto.";
        return $t;
    }
}
