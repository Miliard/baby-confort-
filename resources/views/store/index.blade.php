@extends('layouts.store')
@section('title', 'Baby-Confort | Tienda')

@section('content')
<section class="hero">
    <div class="contenedor">
        <h1>Todo para el confort de tu bebé 👶</h1>
        <p>Pañales y calzoncitos premium, suaves y de alta absorción. Elige tu producto y haz tu pedido en minutos.</p>
        <div class="pills">
            <span class="pill-i">✅ Alta absorción</span>
            <span class="pill-i">🚚 Entrega en El Salvador</span>
            <span class="pill-i">💳 Transferencia · Efectivo · Link</span>
        </div>
    </div>
</section>

<div class="contenedor">@include('store.partials.size-guide')</div>

<main class="contenedor">
    <h2 class="seccion-titulo">Nuestros productos</h2>
    <p class="seccion-sub">Toca un producto para ver sus tallas, precios y detalles.</p>

    <div class="grid">
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
</main>

@include('store.partials.resenas')
@include('store.partials.confianza')
@endsection
