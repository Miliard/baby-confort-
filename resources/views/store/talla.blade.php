@extends('layouts.store')
@section('title', 'Pañales ' . $titulo . ' | Baby-Confort El Salvador')
@section('meta_desc', 'Todos los pañales y calzoncitos Aiwibi disponibles en ' . $titulo . '. Alta absorción, hipoalergénicos y entrega a domicilio en El Salvador.')
@section('og_title', 'Colección ' . $titulo . ' — Baby-Confort')

@section('content')
<section class="hero">
    <div class="contenedor">
        <a href="/" class="volver">← Volver a la tienda</a>
        <h1 style="margin-top:8px">Colección {{ $titulo }} 👶</h1>
        <p>Todos los productos disponibles en {{ $titulo }}. Agrégalos al carrito directo aquí.</p>
        <div class="pills" style="margin-top:14px">
            @foreach(['S','M','L','XL','XXL','XXXL'] as $t)
                <a class="pill-i" href="{{ route('store.talla', $t) }}"
                   style="{{ ($esBaby && $t === strtoupper($talla)) ? 'background:var(--azul);color:#fff;border-color:var(--azul)' : '' }}">Talla {{ $t }}</a>
            @endforeach
        </div>
    </div>
</section>

<main class="contenedor">
    @if(count($items) === 0)
        <div class="sg-none" style="margin:24px 0">Por ahora no hay productos en {{ $titulo }}.
            <a style="color:var(--teal-osc);font-weight:700" target="_blank"
               href="https://wa.me/{{ config('babyconfort.whatsapp') }}?text=Hola%2C%20consulto%20talla%20{{ $talla }}">Consúltanos por WhatsApp</a>.
        </div>
    @else
        <div class="grid" style="padding-top:24px">
            @foreach($items as $it)
                @php
                    $p = $it['product']; $s = $it['size'];
                    $img = $s->imageUrl() ?? $p->imageUrl();
                    $urlProd = route('store.show', $p) . '?t=' . urlencode($s->size);
                @endphp
                <div class="pcard" x-data="{ cantidad: 1 }" style="cursor:default">
                    <a class="img" href="{{ $urlProd }}">@if($p->oferta)<span class="oferta-bubble">{{ $p->oferta }}</span>@endif<img src="{{ $img }}" alt="{{ $p->name }} talla {{ $s->size }}" loading="lazy"></a>
                    <div class="body">
                        <div class="marca">{{ $p->brand }}</div>
                        <a class="nom" href="{{ $urlProd }}" style="color:inherit">{{ $p->name }}</a>
                        <div style="font-size:13px;color:var(--gris)">Talla {{ $s->size }}@if($s->weight) · {{ $s->weight }}@endif · Quedan {{ $s->quantity }}</div>
                        <div class="precio">@if($s->price_before && $s->price_before > $s->price)<span class="precio-antes">${{ number_format($s->price_before, 2) }}</span>@endif ${{ number_format($s->price, 2) }}</div>
                        @if($s->combo_qty)
                            <div style="background:#fff4f3;color:var(--coral-osc);border:1px dashed var(--coral);border-radius:10px;padding:5px 9px;font-size:12.5px;font-weight:700">🎉 {{ $s->combo_qty }} x ${{ number_format($s->combo_price, 2) }}</div>
                        @endif
                        <div style="display:flex;gap:8px;align-items:center;margin-top:8px">
                            <div class="qtybox">
                                <button @click="cantidad = Math.max(1, cantidad-1)">−</button>
                                <span x-text="cantidad"></span>
                                <button @click="cantidad = cantidad+1">+</button>
                            </div>
                            <button class="btn btn-azul" style="flex:1"
                                @click="$store.cart.agregar({ id: {{ $p->id }}, talla: @js($s->size), cantidad: Math.max(1, cantidad), nombre: @js($p->name), imagen: @js($img), precio: {{ (float) $s->price }}, combo: @js($s->combo_qty ? ['cantidad' => (int) $s->combo_qty, 'precio' => (float) $s->combo_price] : null) })">
                                Agregar 🛒
                            </button>
                        </div>
                        {{-- Compartir con un cliente por WhatsApp (ideal para vendedoras) --}}
                        <button class="btn-compartir"
                            @click="bcCompartir(@js([
                                'nombre'   => $p->name,
                                'talla'    => $s->size,
                                'precio'   => (float) $s->price,
                                'antes'    => ($s->price_before && $s->price_before > $s->price) ? (float) $s->price_before : null,
                                'unidades' => $s->unidades ? (int) $s->unidades : null,
                                'combo'    => $s->combo_qty ? ['cantidad' => (int) $s->combo_qty, 'precio' => (float) $s->combo_price] : null,
                                'url'      => $urlProd,
                            ]))">
                            📤 Compartir por WhatsApp
                        </button>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</main>

<style>
    .btn-compartir{margin-top:8px;width:100%;border:1px solid #25D366;background:#f0fbf4;color:#1a9e4b;border-radius:10px;padding:9px;font-weight:700;font-size:13.5px;cursor:pointer;transition:background .1s}
    .btn-compartir:hover{background:#e0f7e9}
    html.dark .btn-compartir{background:#14261c;border-color:#2f5a3f;color:#63d891}
</style>

<script>
// Comparte el producto con todos sus datos. En el teléfono abre el menú de compartir
// (eliges WhatsApp y el contacto); en computadora abre WhatsApp con el mensaje listo.
// Si el link de la página trae ?rev=CODIGO (código de vendedora), se agrega al link
// compartido para que la comisión quede registrada al pedido del cliente.
function bcCompartir(d){
    let url = d.url;
    try {
        const rev = new URLSearchParams(location.search).get('rev') || localStorage.getItem('bc_rev');
        if (rev) url += (url.includes('?') ? '&' : '?') + 'rev=' + encodeURIComponent(rev.toUpperCase());
    } catch(e) {}
    let t = '🍼 ' + d.nombre + ' — Talla ' + d.talla + '\n';
    t += '💵 Precio: $' + Number(d.precio).toFixed(2);
    if (d.antes) t += ' (antes $' + Number(d.antes).toFixed(2) + ')';
    t += '\n';
    if (d.unidades) t += '📦 Trae ' + d.unidades + ' unidades\n';
    if (d.combo) t += '🎉 Combo: ' + d.combo.cantidad + ' x $' + Number(d.combo.precio).toFixed(2) + '\n';
    t += '🚚 Entrega a domicilio en todo El Salvador\n';
    t += '👉 Pídelo aquí: ' + url;
    if (navigator.share) {
        navigator.share({ text: t }).catch(function(){});
    } else {
        window.open('https://wa.me/?text=' + encodeURIComponent(t), '_blank');
    }
}
</script>
@endsection
