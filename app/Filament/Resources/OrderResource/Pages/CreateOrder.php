<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use App\Models\Setting;
use Filament\Resources\Pages\CreateRecord;

/**
 * Registrar un pedido manual (los que llegan por WhatsApp).
 * El subtotal, envío y total se calculan solos a partir de los productos.
 */
class CreateOrder extends CreateRecord
{
    protected static string $resource = OrderResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $items = collect($data['items'] ?? [])->map(function ($it) {
            $cantidad = max(1, (int) ($it['cantidad'] ?? 1));
            $precio   = (float) ($it['precio'] ?? 0);
            return [
                'producto' => trim((string) ($it['producto'] ?? '')),
                'talla'    => trim((string) ($it['talla'] ?? '')) ?: '-',
                'cantidad' => $cantidad,
                'precio'   => $precio,
                'subtotal' => round($cantidad * $precio, 2),
            ];
        })->values()->all();

        $subtotal = round(collect($items)->sum('subtotal'), 2);

        // Si escribió (o pegó) un envío, se respeta; si no, se calcula automático.
        $shipping = ($data['shipping'] ?? '') !== '' && $data['shipping'] !== null
            ? (float) $data['shipping']
            : Setting::envioPara($subtotal);

        $data['items']    = $items;
        $data['subtotal'] = $subtotal;
        $data['shipping'] = round($shipping, 2);
        $data['total']    = round($subtotal + $shipping, 2);
        $data['status']   = 'nuevo';

        return $data;
    }
}
