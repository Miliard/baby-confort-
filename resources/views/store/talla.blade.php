@extends('layouts.store')
@section('title', 'Talla ' . $talla . ' | Baby-Confort')

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
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</main>
@endsection
