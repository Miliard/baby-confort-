<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Setting;

class StoreController extends Controller
{
    public function index()
    {
        $products = Product::with('sizes')->where('active', true)->orderBy('orden')->orderBy('id')->get();
        $envio = Setting::envio();
        return view('store.index', compact('products', 'envio'));
    }

    public function show(Product $product)
    {
        abort_unless($product->active, 404);
        $product->load('sizes');
        $envio = Setting::envio();
        $entregaTexto = Setting::get('envio_tiempo', '24 horas hábiles');
        return view('store.show', compact('product', 'envio', 'entregaTexto'));
    }

    public function talla($talla)
    {
        $babySizes = ['S', 'M', 'L', 'XL', 'XXL', 'XXXL'];
        $tallaUp = strtoupper(trim($talla));
        $esBaby = in_array($tallaUp, $babySizes);
        $slug = \Illuminate\Support\Str::slug($talla);

        $prods = Product::with('sizes')->where('active', true)->orderBy('orden')->orderBy('id')->get();
        $items = [];
        $titulo = $esBaby ? 'Talla ' . $tallaUp : ucfirst(str_replace('-', ' ', $slug));

        foreach ($prods as $p) {
            foreach ($p->sizes as $s) {
                if ($esBaby) {
                    $tokens = preg_split('/[\/\s\-]+/', strtoupper(trim($s->size)));
                    $match = in_array($tallaUp, $tokens);
                } else {
                    $match = (\Illuminate\Support\Str::slug($s->size) === $slug);
                    if ($match) $titulo = $s->size;
                }
                if ($match && (int) $s->quantity > 0) {
                    $items[] = ['product' => $p, 'size' => $s];
                }
            }
        }

        $envio = Setting::envio();
        return view('store.talla', compact('talla', 'titulo', 'esBaby', 'items', 'envio'));
    }

    public function rastreo(\App\Models\Order $order)
    {
        $etapa = $order->etapaEnvio();
        return view('store.rastreo', compact('order', 'etapa'));
    }

    public function rastreoGuia(\Illuminate\Http\Request $request)
    {
        $guia  = trim((string) $request->query('guia', ''));
        $etapa = $guia ? \App\Models\Order::etapaDeGuia($guia) : null;
        return view('store.rastreo-guia', compact('guia', 'etapa'));
    }
}
