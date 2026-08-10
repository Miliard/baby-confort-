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

<main class="contenedor" x-data="{
    sel: {},
    panel: false,
    enviados: {},
    copiado: false,
    selCount(){ return Object.keys(this.sel).length },
    toggleSel(k, d){ if (this.sel[k]) delete this.sel[k]; else this.sel[k] = d; if (this.selCount() === 0) this.panel = false },
    limpiarSel(){ this.sel = {}; this.enviados = {}; this.panel = false },
    compartirSel(){ bcCompartirVarios(Object.values(this.sel)); this.panel = false },
    copiarSel(){
        const t = bcTextoVarios(Object.values(this.sel));
        navigator.clipboard.writeText(t).then(() => { this.copiado = true; setTimeout(() => this.copiado = false, 2500); });
    },
    // Baja una imagen por producto, con el nombre, la talla, las unidades y el precio escritos.
    bajarSel(boton){
        const items = Object.values(this.sel).map(d => ({
            size: d.talla, unidades: d.unidades, price: d.precio, imagen: d.imagen, nombre: d.nombre,
        }));
        bcDescargarConDatos(items, boton);
    },
}">
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
                    $selKey = $p->id . '|' . $s->size;
                    $shareData = [
                        'nombre'   => $p->name,
                        'talla'    => $s->size,
                        'precio'   => (float) $s->price,
                        'antes'    => ($s->price_before && $s->price_before > $s->price) ? (float) $s->price_before : null,
                        'unidades' => $s->unidades ? (int) $s->unidades : null,
                        'combo'    => $s->combo_qty ? ['cantidad' => (int) $s->combo_qty, 'precio' => (float) $s->combo_price] : null,
                        'url'      => $urlProd,
                        'imagen'   => $img,
                    ];
                @endphp
                <div class="pcard" x-data="{ cantidad: 1 }" style="cursor:default" :class="sel[@js($selKey)] ? 'pcard-sel' : ''">
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
                        <div class="share-row">
                            <button class="btn-copiar-link" @click="bcCopiarTexto(bcTextoProducto(@js($shareData)), $event.currentTarget)">🔗 Copiar</button>
                            <button class="btn-elegir" :class="sel[@js($selKey)] ? 'on' : ''"
                                @click="toggleSel(@js($selKey), @js($shareData))">
                                <span x-text="sel[@js($selKey)] ? '✅ Elegido' : '➕ Elegir'"></span>
                            </button>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Panel: elegir cómo compartir (todo junto o cada producto con su foto y su link) --}}
    <div class="sel-panel" x-show="panel && selCount() > 0" x-transition @click.away="panel = false" style="display:none">
        <div class="sel-panel-h">¿Cómo quieres enviarlos?</div>
        <button class="sel-op" :class="copiado ? 'ok' : ''" @click="copiarSel()">
            <span x-text="copiado ? '✅ ¡Copiado! Ya podés pegarlo' : '📋 Copiar el mensaje con todo'"></span>
            <small x-show="!copiado">(no abre WhatsApp: solo lo copia para pegarlo)</small>
        </button>
        <button class="sel-op" @click="compartirSel()">📤 Compartir por WhatsApp <small>(abre WhatsApp con las fotos)</small></button>
        <button class="sel-op" @click="bajarSel($event.currentTarget)">
            <span>⬇️ Bajar las fotos con los datos escritos</span>
            <small>(para mandarlas juntas desde la computadora)</small>
        </button>

        <div class="sel-panel-sub">O copia el mensaje de cada producto por separado:</div>
        <template x-for="(d, k) in sel" :key="k">
            <button class="sel-op sel-op-item" :class="enviados[k] ? 'ok' : ''"
                @click="bcCopiarTexto(bcTextoProducto(d)); enviados[k] = true">
                <span x-text="(enviados[k] ? '✅ Copiado — ' : '📋 ') + d.nombre + ' — Talla ' + d.talla"></span>
            </button>
        </template>
    </div>

    {{-- Barra flotante: aparece al elegir 1 o más productos para compartirlos --}}
    <div class="sel-bar" x-show="selCount() > 0" x-transition style="display:none">
        <button class="sel-limpiar" @click="limpiarSel()" title="Quitar selección">✕</button>
        <span class="sel-txt"><b x-text="selCount()"></b> <span x-text="selCount() === 1 ? 'producto elegido' : 'productos elegidos'"></span></span>
        <button class="sel-compartir" @click="panel = !panel">📤 Enviar</button>
    </div>
</main>

<style>
    /* Fila Copiar + Elegir: discreta, no compite con "Agregar" (2 columnas en teléfono) */
    .share-row{display:flex;gap:8px;margin-top:8px}
    .share-row .btn-copiar-link{margin-top:0;flex:1 1 0;min-width:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
    .share-row .btn-elegir{flex:1 1 0;min-width:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;background:transparent;color:var(--gris);font-weight:600;font-size:12.5px;padding:8px}
    @media(max-width:600px){
        .share-row{gap:6px}
        .share-row .btn-copiar-link,.share-row .btn-elegir{font-size:12px;padding:8px 4px}
        /* Controles de cantidad más compactos para que la fila no se desborde */
        .pcard .qtybox{gap:5px}
        .pcard .qtybox button{width:24px;height:24px;font-size:14px}
        .pcard .btn{font-size:13px;padding:10px 8px}
    }
    .btn-elegir{flex:none;border:1px solid var(--borde);background:#fff;color:var(--texto);border-radius:10px;padding:9px 12px;font-weight:700;font-size:13.5px;cursor:pointer;transition:all .1s;white-space:nowrap}
    .btn-elegir:hover{border-color:var(--azul)}
    .btn-elegir.on{border-color:var(--teal);background:#e9f8f7;color:var(--teal-osc)}
    .pcard-sel{border-color:var(--teal);box-shadow:0 0 0 2px rgba(47,178,172,.35), var(--sombra)}
    .sel-bar{position:fixed;left:50%;transform:translateX(-50%);bottom:18px;z-index:80;display:flex;align-items:center;gap:12px;background:#fff;border:1px solid var(--borde);border-radius:999px;padding:8px 10px 8px 14px;box-shadow:0 10px 30px rgba(20,40,60,.25);font-size:14px;white-space:nowrap;max-width:calc(100vw - 24px)}
    .sel-bar .sel-txt b{color:var(--teal-osc)}
    .sel-limpiar{border:none;background:#f0f3f6;color:var(--gris);border-radius:999px;width:28px;height:28px;cursor:pointer;font-size:13px;flex:none}
    .sel-compartir{border:none;background:#25D366;color:#fff;border-radius:999px;padding:10px 16px;font-weight:800;font-size:14px;cursor:pointer;flex:none}
    .sel-compartir:hover{background:#1fb457}
    @media(max-width:600px){.sel-bar{bottom:12px;font-size:13px;gap:8px}}
    .sel-panel{position:fixed;left:50%;transform:translateX(-50%);bottom:80px;z-index:81;background:#fff;border:1px solid var(--borde);border-radius:16px;padding:14px;box-shadow:0 14px 40px rgba(20,40,60,.3);width:min(360px, calc(100vw - 24px));max-height:60vh;overflow-y:auto}
    .sel-panel-h{font-weight:800;font-size:14.5px;margin-bottom:10px}
    .sel-panel-sub{font-size:12.5px;color:var(--gris);margin:12px 0 8px}
    .sel-op{display:block;width:100%;text-align:left;border:1px solid var(--borde);background:#f8fbff;border-radius:10px;padding:10px 12px;font-weight:700;font-size:13.5px;cursor:pointer;color:var(--texto);margin-bottom:6px}
    .sel-op:hover{border-color:var(--azul)}
    .sel-op small{color:var(--gris);font-weight:600}
    .sel-op.ok{background:#eef8f2;border-color:#bfe6cf;color:var(--teal-osc)}
    html.dark .sel-panel{background:#121b2a;border-color:var(--borde)}
    html.dark .sel-op{background:#16202f;border-color:var(--borde);color:var(--texto)}
    html.dark .sel-op.ok{background:#14261c;border-color:#2f5a3f}
    html.dark .btn-elegir{background:#16202f;border-color:var(--borde);color:var(--texto)}
    html.dark .btn-elegir.on{background:#14261c;border-color:var(--teal);color:var(--teal-osc)}
    html.dark .sel-bar{background:#121b2a;border-color:var(--borde)}
    html.dark .sel-limpiar{background:#233043}
</style>

{{-- Las funciones de compartir (bcCompartir, bcCompartirVarios) viven en el layout,
     disponibles en toda la tienda. --}}
@endsection
