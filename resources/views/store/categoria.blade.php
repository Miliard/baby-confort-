@extends('layouts.store')
@section('title', $titulo . ' | Baby-Confort El Salvador')
@section('meta_desc', $titulo . ' en Baby-Confort: productos Aiwibi de calidad con entrega a domicilio en todo El Salvador. Pide fácil por WhatsApp.')
@section('og_title', $titulo . ' — Baby-Confort')
@section('og_desc', 'Descubre ' . mb_strtolower($titulo) . ' con entrega en todo El Salvador. Pide por WhatsApp.')

@section('content')
<section class="hero">
    <div class="contenedor">
        <a href="/" class="volver" style="color:var(--azul-osc);font-weight:700;text-decoration:none">← Volver a la tienda</a>
        <h1 style="margin-top:8px">{{ $titulo }} 👶</h1>
        <p>Todos nuestros productos de esta categoría.</p>
    </div>
</section>

<main class="contenedor">
    @if($products->count())
        <div class="grid" style="padding-top:24px">
            @foreach ($products as $p)
                <a class="pcard" href="{{ route('store.show', $p) }}">
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
    @else
        <div class="sg-none" style="margin:24px 0">Por ahora no hay productos en esta categoría.</div>
    @endif
</main>
@endsection
