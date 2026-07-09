@extends('layouts.store')
@section('title', $titulo . ' | Baby-Confort')

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
                    <div class="img">@if($p->oferta)<span class="oferta-bubble">{{ $p->oferta }}</span>@endif<img src="{{ $p->imageUrl() }}" alt="{{ $p->name }}" loading="lazy"></div>
                    <div class="body">
                        <div class="marca">{{ $p->brand }}</div>
                        <div class="nom">{{ $p->name }}</div>
                        <div class="precio">desde ${{ number_format($p->precioDesde(), 2) }}</div>
                        <div class="ver">Ver producto →</div>
                    </div>
                </a>
            @endforeach
        </div>
    @else
        <div class="sg-none" style="margin:24px 0">Por ahora no hay productos en esta categoría.</div>
    @endif
</main>
@endsection
