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
        $historial = \App\Models\Order::historialDeGuia($order->guia);
        return view('store.rastreo', compact('order', 'etapa', 'historial'));
    }

    /**
     * Rastreo público. Acepta el número de guía O el teléfono del cliente.
     * Con el teléfono no hace falta que le mandemos nada: él ya se lo sabe.
     */
    public function rastreoGuia(\Illuminate\Http\Request $request)
    {
        $busqueda = trim((string) $request->query('guia', ''));
        $digitos  = preg_replace('/\D/', '', $busqueda);

        $guia      = '';
        $opciones  = collect();   // varios paquetes del mismo teléfono
        $sinGuia   = null;        // pedido confirmado que todavía no tiene guía

        if ($digitos !== '') {
            $esGuia = false;
            try {
                $esGuia = \Illuminate\Support\Facades\Schema::hasTable('guia_fotos')
                    && \App\Models\GuiaFoto::where('guia', $digitos)->exists();
            } catch (\Throwable $e) {
            }

            if ($esGuia) {
                $guia = $digitos;
            } else {
                $opciones = \App\Models\GuiaFoto::porTelefono($digitos);

                if ($opciones->count() === 1) {
                    $guia = (string) $opciones->first()->guia;
                    $opciones = collect();
                } elseif ($opciones->isEmpty()) {
                    // Sin guía todavía. Dos casos: la guía ya se armó y espera el
                    // PDF, o el pedido se hizo en la tienda y aún no se procesa.
                    $sinGuia = \App\Models\GuiaFoto::pendientePorTelefono($digitos);

                    $corto = \App\Models\GuiaFoto::telefonoCorto($digitos);
                    if (! $sinGuia && $corto && strlen($corto) === 8) {
                        $limpio = "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(phone,''),' ',''),'-',''),'(',''),')',''),'+','')";
                        $pedido = \App\Models\Order::whereRaw("$limpio LIKE ?", ['%' . $corto])
                            ->orderByDesc('id')->first();

                        if ($pedido && $pedido->guia) {
                            $guia = (string) $pedido->guia;
                        } elseif ($pedido) {
                            $sinGuia = $pedido;
                        }
                    }
                    // Si no es guía ni teléfono conocido, se rastrea tal cual:
                    // puede ser una guía nueva que aún no hemos registrado.
                    if (! $guia && ! $sinGuia && $opciones->isEmpty()) {
                        $guia = $digitos;
                    }
                }
            }
        }

        $etapa     = $guia ? \App\Models\Order::etapaDeGuia($guia) : null;
        $historial = $guia ? \App\Models\Order::historialDeGuia($guia) : [];

        return view('store.rastreo-guia', compact('guia', 'etapa', 'historial', 'busqueda', 'opciones', 'sinGuia'));
    }

    public function sitemap()
    {
        // [url => lastmod|null] — lastmod ayuda a Google a saber qué re-visitar.
        $urls = [
            url('/')                     => null,
            route('store.rastreo.guia')  => null,
            route('store.nosotros')      => null,
            route('store.devoluciones')  => null,
            route('store.privacidad')    => null,
        ];

        try {
            foreach (\App\Models\Categoria::where('activo', true)->get() as $c) {
                $urls[route('store.categoria', $c->slug)] = optional($c->updated_at)->toDateString();
            }
        } catch (\Throwable $e) {
        }

        foreach (Product::where('active', true)->orderBy('id')->get() as $p) {
            $urls[route('store.show', $p)] = optional($p->updated_at)->toDateString();
        }

        $xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach ($urls as $u => $lastmod) {
            $xml .= '  <url><loc>' . htmlspecialchars($u, ENT_XML1) . '</loc>'
                 . ($lastmod ? '<lastmod>' . $lastmod . '</lastmod>' : '')
                 . '</url>' . "\n";
        }
        $xml .= '</urlset>';

        return response($xml, 200)->header('Content-Type', 'application/xml');
    }

    /**
     * Mensajes listos para copiar y pegar en WhatsApp, agrupados por talla y por
     * producto. Lo consume el programita de escritorio (Baby-Confort Enviar).
     * Siempre trae los precios actuales del panel.
     */
    public function mensajes()
    {
        $productos = Product::with('sizes')->where('active', true)
            ->orderBy('orden')->orderBy('id')->get();

        $bloque = function ($p, $s) {
            $t  = "\u{1F37C} *{$p->name}*\n";
            $t .= "Talla {$s->size}";
            if ($s->unidades) $t .= " \u{00B7} {$s->unidades} unidades";
            $t .= "\n$" . number_format($s->price, 2);
            if ($s->price_before && $s->price_before > $s->price) {
                $t .= " (antes $" . number_format($s->price_before, 2) . ")";
            }
            if ($s->combo_qty && $s->combo_price) {
                $t .= "\n\u{1F389} Combo {$s->combo_qty} x $" . number_format($s->combo_price, 2);
            }
            $t .= "\n\u{1F449} " . route('store.show', $p) . '?t=' . urlencode($s->size);
            return $t;
        };

        // --- Por talla: TODOS los productos que existen en esa talla ---
        // Se agrupa igual que la página /talla/{talla}: una talla "S/M" entra en S y en M.
        $tallas = [];
        foreach (['S', 'M', 'L', 'XL', 'XXL', 'XXXL'] as $talla) {
            $bloques = [];
            foreach ($productos as $p) {
                foreach ($p->sizes as $s) {
                    if ((int) $s->quantity <= 0) continue;
                    $tokens = preg_split('/[\/\s\-]+/', strtoupper(trim($s->size)));
                    if (in_array($talla, $tokens, true)) {
                        $bloques[] = $bloque($p, $s);
                    }
                }
            }
            if (! $bloques) continue;

            $tallas[] = [
                'nombre'    => 'Talla ' . $talla,
                'talla'     => $talla,
                'cantidad'  => count($bloques),
                'texto'     => "\u{1F37C} *Disponible en talla {$talla}:*\n\n"
                             . implode("\n\n", $bloques)
                             . "\n\n\u{1F69A} Entrega a domicilio en todo El Salvador.",
            ];
        }

        // --- Por producto: un mensaje con todas sus tallas ---
        $prods = [];
        foreach ($productos as $p) {
            $bloques = [];
            foreach ($p->sizes as $s) {
                if ((int) $s->quantity <= 0) continue;
                $bloques[] = $bloque($p, $s);
            }
            if (! $bloques) continue;

            $prods[] = [
                'nombre'   => $p->name,
                'cantidad' => count($bloques),
                'texto'    => implode("\n\n", $bloques)
                            . "\n\n\u{1F69A} Entrega a domicilio en todo El Salvador.",
            ];
        }

        return response()->json([
            'tienda'      => 'Baby-Confort',
            'actualizado' => now()->format('d/m/Y H:i'),
            'tallas'      => $tallas,
            'productos'   => $prods,
        ], 200, [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
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
