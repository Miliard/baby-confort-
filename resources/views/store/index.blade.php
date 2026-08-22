@extends('layouts.store')
@section('title', 'Baby-Confort | Pañales Aiwibi antialérgicos en El Salvador')

@section('content')
@php
    $catsConProductos = \App\Models\Product::where('active', true)->whereNotNull('categoria')->distinct()->pluck('categoria')->all();
    $chipsCats = \App\Models\Product::categoriasTienda($catsConProductos);
@endphp
@php
    // Índice de búsqueda: nombre + marca + inicio de la descripción (para encontrar "toallitas", "premium", etc.)
    $indiceBusqueda = $products->map(fn ($p) => \Illuminate\Support\Str::lower(
        $p->name . ' ' . ($p->brand ?? '') . ' ' . \Illuminate\Support\Str::limit(strip_tags($p->description ?? ''), 160, '')
    ))->values();

    // Tallas disponibles de cada producto (solo las que tienen existencia).
    // Van aparte porque "L" es una letra suelta: si se buscara dentro del texto,
    // coincidiría con casi todo.
    $indiceTallas = $products->map(function ($p) {
        $tokens = [];
        foreach ($p->sizes as $s) {
            if ((int) $s->quantity <= 0) continue;
            $texto = \Illuminate\Support\Str::lower(trim($s->size));
            $tokens[] = $texto;
            foreach (preg_split('/[\/\s\-]+/', $texto) as $t) {
                if ($t !== '') $tokens[] = $t;
            }
        }
        return array_values(array_unique($tokens));
    })->values();
@endphp
<div class="bc-portada" x-data="{
    q: '',
    productos: @js($indiceBusqueda),
    tallas: @js($indiceTallas),
    coincide(i) {
        const q = this.q.toLowerCase().trim();
        if (q === '') return true;

        const texto    = this.productos[i] || '';
        const palabras = texto.split(/\s+/);
        const tallas   = this.tallas[i] || [];

        // Cada palabra que escriba el cliente tiene que encajar en algo.
        return q.split(/\s+/).filter(Boolean).every(function (t) {
            if (t === 'talla' || t === 'tallas') return true;
            if (tallas.indexOf(t) !== -1) return true;
            // Una sola letra solo puede ser una talla (S, M, L): si no, saldría todo.
            if (t.length === 1) return false;
            if (palabras.some(function (w) { return w.indexOf(t) === 0; })) return true;
            return t.length >= 4 && texto.indexOf(t) !== -1;
        });
    },
    sinResultados() {
        if (this.q.trim() === '') return false;
        for (let i = 0; i < this.productos.length; i++) if (this.coincide(i)) return false;
        return true;
    },
}">
<section class="hero">
    <div class="contenedor">
        <h1>Todo para el confort de tu bebé 👶</h1>
        <p>Pañales y calzoncitos premium, suaves y de alta absorción. Elige tu producto y haz tu pedido en minutos.</p>
        <div class="buscador-wrap">
            <span class="buscador-ic">🔍</span>
            <input x-model="q" type="text" class="buscador" placeholder="Buscar por producto o talla: pañales, XXL, pachas…">
            <button class="buscador-x" x-show="q" @click="q = ''" style="display:none">✕</button>
        </div>
        <div class="pills" x-show="q.trim() === ''">
            <span class="pill-i">✅ Alta absorción</span>
            <span class="pill-i">🚚 Entrega en El Salvador</span>
            <span class="pill-i">💳 Transferencia · Efectivo · Link</span>
        </div>
    </div>
</section>

@if($chipsCats->count() > 0)
<div class="contenedor cat-chips-wrap" x-show="q.trim() === ''">
    <div class="cat-chips">
        @foreach($chipsCats as $c)
            <a class="cat-chip" href="{{ route('store.categoria', $c->slug) }}">
                <span class="cat-chip-ic">{{ $c->icono ?: '🛍️' }}</span>
                <span class="cat-chip-txt">{{ $c->nombre }}</span>
            </a>
        @endforeach
    </div>
</div>
@endif

<div class="contenedor bc-tallas" x-show="q.trim() === ''">@include('store.partials.size-guide')</div>

<main class="contenedor">
    <h2 class="seccion-titulo" x-text="q.trim() === '' ? 'Nuestros productos' : 'Resultados de tu búsqueda'"></h2>
    <p class="seccion-sub" x-show="q.trim() === ''">Toca un producto para ver sus tallas, precios y detalles.</p>

    <div class="grid">
        @foreach ($products as $p)
            <a class="pcard" href="{{ route('store.show', $p) }}"
               x-show="coincide({{ $loop->index }})">
                @php $sinStock = $p->sizes->isNotEmpty() && ! $p->sizes->contains(fn ($s) => (int) $s->quantity > 0); @endphp
                <div class="img">@if($p->oferta && ! $sinStock)<span class="oferta-bubble">{{ $p->oferta }}</span>@endif @if($sinStock)<span class="agotado-chip">Agotado</span>@endif<img src="{{ $p->imageUrl() }}" alt="{{ $p->name }}" loading="lazy" @if($sinStock) style="filter:grayscale(.7);opacity:.7" @endif></div>
                <div class="body">
                    <div class="marca">{{ $p->brand }}</div>
                    <div class="nom">{{ $p->name }}</div>
                    <div class="precio">desde ${{ number_format($p->precioDesde(), 2) }}</div>
                    <div class="ver">{{ $sinStock ? 'Ver detalles' : 'Ver producto →' }}</div>
                </div>
            </a>
        @endforeach
    </div>

    <div class="sg-none" style="margin:24px 0;display:none"
         x-show="sinResultados()">
        No encontramos productos con "<span x-text="q"></span>".
        <a style="color:var(--teal-osc);font-weight:700" target="_blank"
           href="https://wa.me/{{ config('babyconfort.whatsapp') }}?text=Hola%2C%20busco%20un%20producto">Pregúntanos por WhatsApp</a>.
    </div>
</main>
</div>

<style>
    /* En el teléfono, el menú de tallas va PRIMERO: es lo que el cliente
       necesita tocar, y con el buscador arriba casi nadie bajaba a buscarlo.
       En pantalla grande se ve todo de una, así que ahí no se cambia nada. */
    @media(max-width:820px){
        .bc-portada{display:flex;flex-direction:column}
        .bc-portada > .bc-tallas{order:-1;margin-top:14px}
    }
    .buscador-wrap{position:relative;max-width:560px;margin:16px auto 4px}
    @media(min-width:821px){.hero .contenedor{text-align:center}.hero p{margin-left:auto;margin-right:auto}.pills{justify-content:center}.cat-chips{justify-content:center}}
    .buscador{width:100%;padding:13px 40px 13px 42px;border:1px solid var(--borde);border-radius:999px;font-size:15px;background:#fff;box-shadow:0 2px 8px rgba(47,127,191,.06)}
    .buscador:focus{outline:none;border-color:var(--azul);box-shadow:0 0 0 3px rgba(74,163,223,.15)}
    .buscador-ic{position:absolute;left:15px;top:50%;transform:translateY(-50%);font-size:16px;opacity:.7}
    .buscador-x{position:absolute;right:8px;top:50%;transform:translateY(-50%);border:none;background:#eef2f6;border-radius:50%;width:26px;height:26px;cursor:pointer;color:var(--gris);font-size:13px}
    /* Chips de categoría */
    .cat-chips-wrap{margin-top:18px}
    .cat-chips{display:flex;gap:12px;flex-wrap:wrap}
    .cat-chip{display:flex;align-items:center;gap:9px;background:#fff;border:1px solid var(--borde);border-radius:999px;padding:9px 18px 9px 12px;font-weight:700;font-size:14.5px;color:var(--texto);box-shadow:0 2px 8px rgba(47,127,191,.06);transition:transform .08s,box-shadow .08s,border-color .08s}
    .cat-chip:hover{transform:translateY(-2px);border-color:var(--azul);box-shadow:0 8px 18px rgba(47,127,191,.14);color:var(--azul-osc)}
    .cat-chip-ic{width:34px;height:34px;border-radius:999px;background:var(--azul-claro);display:grid;place-items:center;font-size:18px;flex:none}
    @media(max-width:600px){
        .cat-chips{gap:9px}
        .cat-chip{flex:1 1 calc(50% - 9px);justify-content:flex-start;padding:9px 12px;font-size:13.5px}
    }
</style>

@include('store.partials.resenas')
@include('store.partials.confianza')
@endsection
