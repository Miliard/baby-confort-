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

        // Productos relacionados: primero de la misma categoría, luego se rellena con otros.
        $relacionados = Product::with('sizes')->where('active', true)->where('id', '!=', $product->id)
            ->when($product->categoria, fn ($q) => $q->where('categoria', $product->categoria))
            ->orderBy('orden')->orderBy('id')->take(4)->get();
        if ($relacionados->count() < 4) {
            $faltan = 4 - $relacionados->count();
            $extra = Product::with('sizes')->where('active', true)
                ->where('id', '!=', $product->id)
                ->whereNotIn('id', $relacionados->pluck('id'))
                ->orderBy('orden')->orderBy('id')->take($faltan)->get();
            $relacionados = $relacionados->concat($extra);
        }

        return view('store.show', compact('product', 'envio', 'entregaTexto', 'relacionados'));
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

    public function categoria($cat)
    {
        // Válida si es una de las 4 por defecto o existe en la tabla de categorías.
        $valido = array_key_exists($cat, Product::CATEGORIAS);
        if (! $valido) {
            try {
                $valido = \Illuminate\Support\Facades\Schema::hasTable('categorias')
                    && \App\Models\Categoria::where('slug', $cat)->exists();
            } catch (\Throwable $e) {
            }
        }
        abort_unless($valido, 404);

        $titulo = Product::categoriaLabel($cat);
        $products = Product::with('sizes')->where('active', true)->where('categoria', $cat)
            ->orderBy('orden')->orderBy('id')->get();
        return view('store.categoria', compact('products', 'titulo', 'cat'));
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

    public function validarCupon(\Illuminate\Http\Request $request)
    {
        $cupon = \App\Models\Cupon::buscarActivo($request->query('codigo'));
        if (! $cupon) {
            return response()->json(['ok' => false, 'error' => 'Cupón no válido o inactivo.']);
        }
        return response()->json([
            'ok'         => true,
            'codigo'     => $cupon->codigo,
            'porcentaje' => $cupon->porcentaje,
        ]);
    }

    public function gracias(\App\Models\Order $order)
    {
        $waUrl = $order->whatsappUrl();
        return view('store.gracias', compact('order', 'waUrl'));
    }

    public function nosotros()
    {
        return view('store.paginas.nosotros');
    }

    public function devoluciones()
    {
        return view('store.paginas.devoluciones');
    }

    public function privacidad()
    {
        return view('store.paginas.privacidad');
    }
}
