@extends('layouts.store')
@section('title', 'Baby-Confort | Pañales Aiwibi antialérgicos en El Salvador')

@section('content')
@php
    $catsConProductos = \App\Models\Product::where('active', true)->whereNotNull('categoria')->distinct()->pluck('categoria')->all();
    $chipsCats = \App\Models\Product::categoriasTienda($catsConProductos);
@endphp
@php
    // Los agotados van al final: en medio de la cuadrícula rompen la fila y
    // el cliente pierde el hilo de lo que sí puede comprar.
    $products = $products->sortBy(fn ($p) => $p->sizes->isNotEmpty() && ! $p->sizes->contains(fn ($s) => (int) $s->quantity > 0) ? 1 : 0)
                         ->values();

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
{{-- El encabezado, en dos columnas: a la izquierda qué vendemos y por qué
     confiar; a la derecha, el buscador de talla. Antes era una sola columna con
     el buscador de productos, y el cliente que no sabe la talla —que es la
     mayoría de los que escriben— no tenía dónde empezar. --}}
<section class="hero">
    <div class="contenedor hero-grid">
        <div>
            <span class="hero-eyebrow">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 13 4 4L19 7"/></svg>
                Distribuidor Aiwibi en El Salvador
            </span>

            <h1>Pañales que no irritan, en la puerta de tu casa mañana</h1>
            <p class="hero-lead">Aiwibi Australia: alta absorción, hipoalergénicos y con indicador
                de humedad. Pagás cuando los recibís.</p>

            <div class="buscador-wrap">
                <span class="buscador-ic"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg></span>
                <input x-model="q" type="text" class="buscador" placeholder="Buscar por producto o talla: pañales, XXL, pachas…">
                <button class="buscador-x" x-show="q" @click="q = ''" style="display:none">✕</button>
            </div>

            {{-- Las razones para confiar, arriba y a la vista. Son las que uno
                 contesta por WhatsApp antes de cada compra. --}}
            <div class="confia" x-show="q.trim() === ''">
                <span class="confia-i"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 13 4 4L19 7"/></svg> Entrega en 24 h hábiles</span>
                <span class="confia-i"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 13 4 4L19 7"/></svg> Pagás al recibir</span>
                <span class="confia-i"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 13 4 4L19 7"/></svg> Envío ${{ number_format((float) ($envio ?? 2.5), 2) }} a todo el país</span>
                <span class="confia-i"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 13 4 4L19 7"/></svg> Alta absorción e hipoalergénicos</span>
            </div>
        </div>

        <div x-show="q.trim() === ''">@include('store.partials.busca-talla')</div>
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

<main class="contenedor" id="productos">
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
                    @php
                        // El precio por pañal es como se decide la compra: sin él,
                        // el cliente tiene que dividir de cabeza para comparar.
                        $tBarata = $p->sizes->sortBy('price')->first();
                    @endphp
                    @if($tBarata && (int) $tBarata->unidades > 0)
                        <div class="unidad">{{ $tBarata->unidades }} uds · ${{ number_format($tBarata->price / $tBarata->unidades, 2) }} c/u</div>
                    @endif
                    <div class="ver">{{ $sinStock ? 'Ver detalles' : 'Ver producto →' }}</div>
                </div>
            </a>
        @endforeach
    </div>

    <div class="sg-none" style="margin:24px 0;display:none"
         x-show="sinResultados()">
        No encontramos productos con "<span x-text="q"></span>".
        <a style="color:var(--azul-osc);font-weight:700" target="_blank"
           href="https://wa.me/{{ config('babyconfort.whatsapp') }}?text=Hola%2C%20busco%20un%20producto">Pregúntanos por WhatsApp</a>.
    </div>
</main>

{{-- Cómo se compra. Va al final a propósito: lo lee quien ya vio algo que le
     gustó y le falta animarse. Responde las tres dudas de siempre —cómo pido,
     cuándo llega, cómo pago— sin que tenga que escribir para preguntarlas. --}}
<section class="pasos-sec" x-show="q.trim() === ''">
    <div class="contenedor">
        <h2 class="seccion-titulo">Comprar es fácil y seguro</h2>
        <p class="seccion-sub">Tres pasos. Pagás cuando el paquete está en tus manos.</p>

        <div class="pasos">
            <div class="paso">
                <div class="paso-ic" aria-hidden="true"><svg width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="8" cy="21" r="1"/><circle cx="19" cy="21" r="1"/><path d="M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h9.78a2 2 0 0 0 1.95-1.57l1.65-7.43H5.12"/></svg></div>
                <h3>1. Elegí y agregá</h3>
                <p>Escogé producto y talla, agregalo al carrito y confirmá con tu nombre,
                   teléfono y dirección.</p>
            </div>

            <div class="paso">
                <div class="paso-ic" aria-hidden="true"><svg width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 17h4V5H2v12h3M20 17h2v-3.34a4 4 0 0 0-1.17-2.83L19 9h-5v8h2"/><circle cx="7.5" cy="17.5" r="2.5"/><circle cx="17.5" cy="17.5" r="2.5"/></svg></div>
                <h3>2. Te escribimos</h3>
                <p>Te contactamos por WhatsApp para coordinar la entrega. Llega en 24 horas
                   hábiles con Expres El Salvador.</p>
            </div>

            <div class="paso">
                <div class="paso-ic" aria-hidden="true"><svg width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/></svg></div>
                <h3>3. Pagás al recibir</h3>
                <p>Efectivo contra entrega, transferencia o link de pago. Vos elegís.
                   Envío ${{ number_format((float) ($envio ?? 2.5), 2) }} a todo el país.</p>
            </div>
        </div>
    </div>
</section>
</div>

{{-- La barra fija de abajo, solo en el teléfono. Las dos cosas que el cliente
     quiere hacer están siempre a un toque, sin importar cuánto haya bajado. --}}
<div class="barra-abajo">
    <a class="ba-btn ba-ver" href="#productos">Ver productos</a>
    <a class="ba-btn ba-wa" target="_blank" rel="noopener"
       href="https://wa.me/{{ config('babyconfort.whatsapp') }}?text=Hola%2C%20quiero%20hacer%20un%20pedido"
       aria-label="Escribinos por WhatsApp">
        <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 11.5a8.4 8.4 0 0 1-9 8.5 8.5 8.5 0 0 1-3.8-.9L3 21l1.9-5.1A8.5 8.5 0 0 1 12 3a8.4 8.4 0 0 1 9 8.5Z"/></svg>
        WhatsApp
    </a>
</div>

<style>
    /* En el teléfono, el menú de tallas va PRIMERO: es lo que el cliente
       necesita tocar, y con el buscador arriba casi nadie bajaba a buscarlo.
       En pantalla grande se ve todo de una, así que ahí no se cambia nada. */
    @media(max-width:820px){
        .bc-portada{display:flex;flex-direction:column}
        .bc-portada > .bc-tallas{order:-1;margin-top:14px}
    }
    .buscador-wrap{position:relative;max-width:560px;margin:16px 0 4px}

    /* ── El encabezado en dos columnas ──
       A la izquierda el qué y el por qué; a la derecha el buscador de talla.
       En el teléfono se apilan, y el de talla va PRIMERO: es la duda que trae
       casi todo el que escribe, y si queda abajo nadie baja a buscarla. */
    .hero{padding:var(--s5) 0}
    .hero-grid{display:grid;grid-template-columns:1.05fr .95fr;gap:var(--s5);align-items:center}
    .hero h1{font-size:clamp(28px,4.4vw,46px);margin:0 0 var(--s2);text-wrap:balance}
    .hero-lead{font-size:clamp(16px,1.6vw,18px);color:var(--gris);max-width:36ch;margin:0}
    .hero-eyebrow{display:inline-flex;align-items:center;gap:8px;background:#fff;
                  border:1px solid var(--borde);color:var(--azul);font-weight:700;
                  font-size:13.5px;padding:7px 14px;border-radius:999px;margin-bottom:var(--s2)}

    .confia{display:flex;flex-wrap:wrap;gap:10px 18px;margin-top:var(--s3);
            padding-top:var(--s3);border-top:1px solid var(--borde)}
    .confia-i{display:flex;align-items:center;gap:8px;font-size:14.5px;font-weight:600}
    .confia-i svg{color:var(--ok);flex:none}

    @media(max-width:820px){
        .hero-grid{grid-template-columns:1fr;gap:var(--s3)}
        .hero-grid > div:last-child{order:-1}
        .hero h1{font-size:26px}
        .confia{gap:8px 14px}
        .confia-i{font-size:13.5px}
    }

    /* ── Buscador de talla por peso ── */
    .buscatalla{background:#fff;border:1px solid var(--borde);border-radius:var(--radio-lg);
                padding:var(--s4);box-shadow:var(--sombra-lg)}
    .bt-t{font-size:21px;margin:0}
    .bt-p{color:var(--gris);font-size:15px;margin:6px 0 0}
    .bt-lab{display:block;font-weight:700;font-size:14px;margin:var(--s3) 0 8px}
    .bt-campo{display:flex;gap:10px}
    .bt-campo input{flex:1;min-width:0;min-height:52px;padding:12px 16px;
                    border:1.5px solid var(--borde);border-radius:var(--radio-sm);
                    font:inherit;font-size:16px;background:#fff;color:var(--texto)}
    .bt-campo input:focus{border-color:var(--azul);outline:none;
                          box-shadow:0 0 0 3px rgba(21,88,176,.15)}
    .bt-btn{min-height:52px;padding:12px 22px;border:0;border-radius:var(--radio-sm);
            background:var(--azul);color:#fff;font:inherit;font-weight:700;font-size:16px;
            cursor:pointer;flex:none}
    .bt-btn:hover{background:var(--azul-osc)}
    .bt-ayuda{font-size:13.5px;color:var(--gris);margin:8px 0 0}
    .bt-res{margin-top:var(--s2);padding:16px;border-radius:var(--radio-sm);
            background:#ecfdf3;border:1px solid #bbf7d0}
    .bt-res strong{font-family:var(--tipo-titulo);font-size:20px;color:var(--ok);display:block}
    .bt-res > span{display:block;font-size:14px;color:var(--gris);margin-top:2px}
    .bt-ver{display:inline-block;margin-top:10px;font-weight:700;color:var(--azul-osc);
            min-height:44px;display:inline-flex;align-items:center}
    [x-cloak]{display:none !important}

    @media(max-width:820px){
        .buscatalla{padding:var(--s3)}
        .bt-campo{flex-wrap:wrap}
        .bt-btn{width:100%}
    }

    /* ── Los tres pasos ── */
    .pasos-sec{background:#fff;border-top:1px solid var(--borde);
               padding:var(--s5) 0 var(--s6);margin-top:var(--s4)}
    .pasos{display:grid;grid-template-columns:repeat(3,1fr);gap:var(--s3);margin-top:var(--s3)}
    .paso{background:var(--fondo);border:1px solid var(--borde);
          border-radius:var(--radio);padding:var(--s3)}
    .paso-ic{width:46px;height:46px;border-radius:14px;background:var(--azul-claro);
             color:var(--azul-osc);display:grid;place-items:center;margin-bottom:var(--s2)}
    .paso h3{font-size:17px;margin:0 0 6px}
    .paso p{margin:0;color:var(--gris);font-size:14.5px;line-height:1.6}
    @media(max-width:820px){ .pasos{grid-template-columns:1fr} }

    /* ── Barra fija de abajo, solo en el teléfono ── */
    .barra-abajo{display:none}
    @media(max-width:820px){
        .barra-abajo{display:flex;gap:10px;position:fixed;left:0;right:0;bottom:0;z-index:60;
                     padding:10px 14px calc(10px + env(safe-area-inset-bottom));
                     background:rgba(255,255,255,.96);backdrop-filter:blur(8px);
                     border-top:1px solid var(--borde)}
        .ba-btn{flex:1;min-height:48px;display:inline-flex;align-items:center;
                justify-content:center;gap:8px;border-radius:999px;font-weight:700;font-size:15.5px}
        .ba-ver{background:var(--cta);color:var(--on-cta)}
        .ba-wa{background:#fff;border:1.5px solid var(--borde);color:var(--texto)}
        /* Para que la barra no tape el último producto de la cuadrícula. */
        body{padding-bottom:76px}
    }
    .buscador{width:100%;padding:13px 40px 13px 42px;border:1px solid var(--borde);border-radius:999px;font-size:15px;background:#fff;box-shadow:0 2px 8px rgba(47,127,191,.06)}
    .buscador:focus{outline:none;border-color:var(--azul);box-shadow:0 0 0 3px rgba(74,163,223,.15)}
    .buscador-ic{position:absolute;left:15px;top:50%;transform:translateY(-50%);display:grid;place-items:center;color:var(--gris)}
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
