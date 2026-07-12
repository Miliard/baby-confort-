@extends('layouts.store')
@section('title', $product->name . ' | Baby-Confort')

@section('content')
@php
    $fotos = $product->galleryUrls();
    $sizesJs = $product->sizes->map(fn ($s) => [
        'size'     => $s->size,
        'weight'   => $s->weight,
        'unidades' => $s->unidades ? (int) $s->unidades : null,
        'details'  => $s->details,
        'imagen'   => $s->imageUrl(),
        'price'    => (float) $s->price,
        'antes'    => $s->price_before ? (float) $s->price_before : null,
        'quantity' => (int) $s->quantity,
        'combo'    => $s->combo_qty ? ['cantidad' => (int) $s->combo_qty, 'precio' => (float) $s->combo_price] : null,
    ])->values();
    $tParam = request('t');
    $tallaSel = $tParam ? $product->sizes->first(fn ($s) => \Illuminate\Support\Str::slug($s->size) === \Illuminate\Support\Str::slug($tParam)) : null;
    $primeraTalla = $tallaSel ?? $product->sizes->firstWhere('quantity', '>', 0) ?? $product->sizes->first();
@endphp

<main class="contenedor"
      x-data="{
        fotos: @js($fotos),
        sizes: @js($sizesJs),
        idx: 0,
        current: '',
        zoom: false, zx: 50, zy: 50,
        panZoom(e){ const r = e.currentTarget.getBoundingClientRect(); this.zx = ((e.clientX - r.left) / r.width) * 100; this.zy = ((e.clientY - r.top) / r.height) * 100; },
        talla: '{{ $primeraTalla->size ?? '' }}',
        cantidad: 1,
        init(){ const s = this.sel(); this.current = (s && s.imagen) ? s.imagen : (this.fotos[0] || ''); },
        sel(){ return this.sizes.find(s => s.size === this.talla) || {}; },
        precio(){ return this.sel().price || 0; },
        money(n){ return '$' + Number(n||0).toFixed(2); },
        pickSize(t){ this.talla = t; const s = this.sel(); this.current = (s && s.imagen) ? s.imagen : (this.fotos[0] || ''); },
        pickFoto(k){ this.idx = k; this.current = this.fotos[k]; },
        add(){
            const s = this.sel();
            if(!s.size){ alert('Elige una talla'); return; }
            if((s.quantity||0) <= 0){ alert('Esta talla está agotada por el momento. ¡Pronto la tendremos de nuevo!'); return; }
            $store.cart.agregar({ id: {{ $product->id }}, talla: s.size, cantidad: Math.max(1, this.cantidad),
                nombre: @js($product->name), imagen: (s.imagen || this.fotos[0] || ''), precio: s.price, combo: s.combo });
        }
      }">

    <a href="/" class="volver">← Volver a la tienda</a>

    <div class="pdp">
        {{-- Galería --}}
        <div>
            <div class="gal-main">
                @if($product->oferta)<span class="oferta-bubble">{{ $product->oferta }}</span>@endif
                <img :src="current" x-ref="mimg" class="gal-zoomable"
                     x-effect="current; if($refs.mimg){ $refs.mimg.classList.remove('anim'); void $refs.mimg.offsetWidth; $refs.mimg.classList.add('anim'); }"
                     @mouseenter="if(window.matchMedia('(hover: hover)').matches) zoom = true"
                     @mouseleave="zoom = false"
                     @mousemove="if(zoom) panZoom($event)"
                     @click="if(window.matchMedia('(hover: none)').matches) zoom = !zoom"
                     :style="zoom ? 'transform:scale(2.3);transform-origin:'+zx+'% '+zy+'%;cursor:zoom-out' : ''"
                     :alt="@js($product->name)">
                <template x-if="fotos.length > 1">
                    <button class="gal-nav prev" @click="idx=(idx-1+fotos.length)%fotos.length; current=fotos[idx]">‹</button>
                </template>
                <template x-if="fotos.length > 1">
                    <button class="gal-nav next" @click="idx=(idx+1)%fotos.length; current=fotos[idx]">›</button>
                </template>
                <div class="gal-hint" x-show="!zoom">🔍 <span x-text="window.matchMedia('(hover: none)').matches ? 'Toca para ampliar' : 'Pasa el cursor para ampliar'"></span></div>
            </div>
            <div class="gal-thumbs">
                <template x-for="(f, k) in fotos" :key="k">
                    <div class="th" :class="current === f ? 'sel' : ''" @click="pickFoto(k)"><img :src="f"></div>
                </template>
            </div>
        </div>

        {{-- Info --}}
        <div>
            <div class="trust">
                <div class="avs"><span>👩</span><span>🧑</span><span>👵</span></div>
                <div><b>Confiados por 10000+ padres felices</b><br><span style="color:var(--gris)">Calidad superior para el bienestar de tu bebé</span></div>
            </div>

            <h1>{{ $product->name }}</h1>
            <div class="desc">{{ $product->description }}</div>
            <div class="precio">
                <template x-if="sel().antes && sel().antes > sel().price"><span class="precio-antes">$<span x-text="Number(sel().antes).toFixed(2)"></span></span></template>
                $<span x-text="precio().toFixed(2)"></span> <small>USD</small>
            </div>
            <template x-if="sel().unidades">
                <div style="display:inline-block;margin-top:6px;background:var(--azul-claro);border:1px solid var(--borde);color:var(--azul-osc);border-radius:999px;padding:5px 14px;font-size:13.5px;font-weight:700">
                    📦 Esta talla trae <span x-text="sel().unidades"></span> unidades
                </div>
            </template>

            <hr class="sep">

            {{-- Detalle de la talla elegida (si tiene). Si no, se muestran las características. --}}
            <template x-if="sel().details">
                <div style="white-space:pre-line;background:var(--azul-claro);border:1px solid var(--borde);border-radius:12px;padding:16px;font-size:14px;line-height:1.65;color:var(--texto)" x-text="sel().details"></div>
            </template>
            @if(!empty($product->features))
                <div x-show="!sel().details">
                    @foreach($product->features as $f)
                        <div class="feat"><div class="ic">{{ $f['icon'] ?? '•' }}</div><div>{{ $f['text'] ?? '' }}</div></div>
                    @endforeach
                </div>
            @endif

            <div class="infobar"><span class="dot"></span> Envío en <b style="margin:0 4px">{{ $entregaTexto }}</b> · 🇸🇻 Envío rápido en SV</div>

            <div class="size-label">TALLA</div>
            <div class="size-pills">
                @foreach($product->sizes as $s)
                    <button class="spill" :class="talla === '{{ $s->size }}' ? 'sel' : ''"
                            @click="pickSize('{{ $s->size }}')"
                            @if($s->quantity <= 0) disabled title="Agotado por el momento" @endif>{{ $s->size }}@if($s->quantity <= 0) <small>· Agotado</small>@endif</button>
                @endforeach
            </div>

            <div style="display:flex;align-items:center;gap:12px;margin-top:14px">
                <span style="font-size:13px;font-weight:700;color:var(--gris)">Cantidad</span>
                <div class="qtybox">
                    <button @click="cantidad = Math.max(1, cantidad-1)">−</button>
                    <span x-text="cantidad"></span>
                    <button @click="cantidad = cantidad+1">+</button>
                </div>
                <template x-if="sel().combo">
                    <span style="font-size:12.5px;color:var(--coral-osc);font-weight:700">🎉 Combo <span x-text="sel().combo.cantidad"></span> x <span x-text="money(sel().combo.precio)"></span></span>
                </template>
            </div>

            <button class="cta" @click="add()">🛒 Añadir al carrito</button>

            <div class="metarow">
                @if($product->made_in)<div class="m">🏭 Fabricado en <b>{{ $product->made_in }}</b></div>@endif
                @if($product->badge)<div class="m">🏆 <b>{{ $product->badge }}</b></div>@endif
            </div>

            @if($product->stock_warning)
                <div class="warn"><div class="t">⚠ Advertencia: Aviso de pocas unidades</div><div class="b">{{ $product->stock_warning }}</div></div>
            @endif
        </div>
    </div>

    @include('store.partials.size-guide')

    @include('store.partials.faq')

    @include('store.partials.relacionados')

    {{-- Barra fija de compra (solo móvil) --}}
    <div class="buy-sticky">
        <div class="buy-sticky-info">
            <div class="buy-sticky-price">$<span x-text="precio().toFixed(2)"></span></div>
            <div class="buy-sticky-sub">
                <b x-text="talla || '—'"></b><template x-if="sel().unidades"><span> · <b x-text="sel().unidades"></b> uds</span></template>
            </div>
        </div>
        <button class="buy-sticky-btn" @click="add()" :disabled="(sel().quantity||0) <= 0">
            <span x-text="(sel().quantity||0) <= 0 ? 'Agotado' : '🛒 Añadir al carrito'"></span>
        </button>
    </div>
</main>
<style>
    .gal-zoomable{cursor:zoom-in;transition:transform .12s ease-out}
    .gal-zoomable.anim{transition:none}
    .gal-hint{position:absolute;bottom:10px;right:10px;z-index:3;background:rgba(255,255,255,.9);color:var(--texto);border-radius:999px;padding:5px 12px;font-size:12px;font-weight:700;pointer-events:none;display:flex;align-items:center;gap:5px}
    @media(hover:none){.gal-zoomable{cursor:zoom-in}}
    .buy-sticky{display:none}
    @media(max-width:820px){
        .buy-sticky{display:flex;position:fixed;left:0;right:0;bottom:0;z-index:60;background:#fff;border-top:1px solid var(--borde);padding:10px 14px;gap:12px;align-items:center;box-shadow:0 -4px 16px rgba(47,127,191,.12)}
        .buy-sticky-info{display:flex;flex-direction:column;line-height:1.15;white-space:nowrap}
        .buy-sticky-price{font-weight:900;font-size:19px;color:var(--azul-osc)}
        .buy-sticky-sub{font-size:11.5px;color:var(--gris);font-weight:600;margin-top:1px}
        .buy-sticky-btn{flex:1;background:var(--teal);color:#fff;border:none;border-radius:12px;padding:14px;font-weight:800;font-size:15px;cursor:pointer}
        .buy-sticky-btn:disabled{background:var(--gris);cursor:not-allowed}
        main.contenedor{padding-bottom:82px}
        .wa-float{bottom:82px}
    }
</style>
<script>
    if (window.fbq) fbq('track', 'ViewContent', {
        content_type: 'product',
        content_ids: ['{{ $product->id }}'],
        content_name: @js($product->name),
        value: {{ number_format($product->precioDesde(), 2, '.', '') }},
        currency: 'USD'
    });
</script>
@endsection
