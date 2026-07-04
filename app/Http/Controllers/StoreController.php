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
        $talla = strtoupper($talla);
        $validas = ['S', 'M', 'L', 'XL', 'XXL', 'XXXL'];
        abort_unless(in_array($talla, $validas), 404);

        $prods = Product::with('sizes')->where('active', true)->orderBy('orden')->orderBy('id')->get();
        $items = [];
        foreach ($prods as $p) {
            foreach ($p->sizes as $s) {
                $tokens = preg_split('/[\/\s\-]+/', strtoupper(trim($s->size)));
                if (in_array($talla, $tokens) && (int) $s->quantity > 0) {
                    $items[] = ['product' => $p, 'size' => $s];
                }
            }
        }

        $envio = Setting::envio();
        return view('store.talla', compact('talla', 'items', 'envio'));
    }
}
