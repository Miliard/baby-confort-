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

        // Revendedor: NO cambia el precio del cliente; solo etiqueta el pedido y calcula la comisión.
        $revCodigo = null;
        $comision  = 0;
        $rev = \App\Models\Revendedor::buscarActivo($request->input('revendedor'));
        if ($rev) {
            $revCodigo = $rev->codigo;
            $comision  = round($subtotal * ($rev->porcentaje / 100), 2);
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
            'revendedor'    => $revCodigo,
            'comision'      => $comision,
            'items'         => $items,
            'status'        => 'nuevo',
        ]);

        // Aviso instantáneo al negocio por Telegram (si está configurado).
        $order->notificarTelegram();

        return response()->json(['ok' => true, 'folio' => $order->id, 'whatsapp_url' => $order->whatsappUrl()]);
    }
}
