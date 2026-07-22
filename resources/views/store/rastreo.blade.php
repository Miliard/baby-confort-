@extends('layouts.store')
@section('title', 'Seguimiento de tu pedido | Baby-Confort')
@section('og_title', 'Sigue tu pedido en vivo 📦 — Baby-Confort')
@section('og_desc', 'Rastrea tu paquete y mira en qué etapa va tu envío, paso a paso.')
@section('og_image', 'og-rastreo.png')

@section('content')
@php
    $etapas = \App\Models\Order::ETAPAS;
    $iconos = [1 => '✅', 2 => '📦', 3 => '🚚', 4 => '🏠'];
    $fill = (($etapa - 1) / 3) * 75;
@endphp
<style>
    .trk-wrap{max-width:720px;margin:0 auto;padding:30px 16px 60px}
    .trk{position:relative;margin:34px 0 8px}
    .trk-line{position:absolute;top:28px;left:12.5%;right:12.5%;height:6px;background:#e2e8ee;border-radius:99px;z-index:0}
    .trk-fill{position:absolute;top:28px;left:12.5%;height:6px;background:linear-gradient(90deg,#4aa3df,#2e9e6b);border-radius:99px;z-index:1;transition:width .6s ease}
    .trk-steps{position:relative;z-index:2;display:flex;justify-content:space-between}
    .trk-step{display:flex;flex-direction:column;align-items:center;width:25%;text-align:center}
    .trk-dot{width:58px;height:58px;border-radius:50%;background:#eef1f5;border:3px solid #e2e8ee;display:grid;place-items:center;font-size:30px;font-weight:900;color:#b3bcc7;transition:.3s}
    .trk-step.on .trk-dot{background:#2e9e6b;border-color:#2e9e6b;color:#fff}
    .trk-step.cur .trk-dot{box-shadow:0 0 0 6px rgba(46,158,107,.18);transform:scale(1.07)}
    .trk-lbl{margin-top:10px;font-size:13px;font-weight:800;color:var(--gris);line-height:1.2}
    .trk-step.on .trk-lbl{color:var(--texto)}
    .trk-estado{background:linear-gradient(135deg,#eafaf2,#eaf5fc);border:1px solid var(--borde);border-radius:16px;padding:16px 18px;text-align:center;margin-top:22px}
    .trk-estado .e{font-size:19px;font-weight:800;color:var(--azul-osc)}
    .trk-card{background:#fff;border:1px solid var(--borde);border-radius:14px;padding:16px 18px;margin-top:16px}
    @media(max-width:460px){.trk-dot{width:48px;height:48px;font-size:22px}.trk-line,.trk-fill{top:23px}.trk-lbl{font-size:11.5px}}
</style>
<main class="trk-wrap">
    <a href="/" class="volver" style="color:var(--azul-osc);font-weight:700;text-decoration:none">← Volver a la tienda</a>
    <h1 style="margin:12px 0 4px;color:var(--texto)">Seguimiento de tu pedido 📦</h1>
    <p style="color:var(--gris);margin:0">Hola{{ $order->customer_name ? ', ' . $order->customer_name : '' }} 👋 &nbsp; Pedido <b>#{{ $order->id }}</b></p>

    <div class="trk">
        <div class="trk-line"></div>
        <div class="trk-fill" style="width:{{ $fill }}%"></div>
        <div class="trk-steps">
            @foreach($etapas as $n => $label)
                <div class="trk-step {{ $n <= $etapa ? 'on' : '' }} {{ $n == $etapa ? 'cur' : '' }}">
                    <div class="trk-dot">{{ $n <= $etapa ? '✓' : '✕' }}</div>
                    <div class="trk-lbl">{{ $iconos[$n] }} {{ $label }}</div>
                </div>
            @endforeach
        </div>
    </div>

    <div class="trk-estado">
        <div class="e">{{ $iconos[$etapa] }} {{ $etapas[$etapa] }}</div>
        <div style="color:var(--gris);font-size:13.5px;margin-top:4px">
            @if($etapa < 4) Tu pedido está en proceso. Te avisamos cualquier novedad. 💙
            @else ¡Tu pedido fue entregado! Gracias por tu compra. 🎉 @endif
        </div>
    </div>

    @include('store.partials.historial')

    @include('store.partials.recomendados')

    <div class="trk-card">
        <div style="font-weight:800;color:var(--azul-osc);margin-bottom:8px">Detalle del pedido</div>
        @foreach(($order->items ?? []) as $it)
            <div style="display:flex;justify-content:space-between;font-size:14px;padding:3px 0;color:var(--texto)">
                <span>{{ $it['cantidad'] ?? '' }} × {{ $it['producto'] ?? '' }} <span style="color:var(--gris)">({{ $it['talla'] ?? '' }})</span></span>
                <span>${{ number_format($it['subtotal'] ?? 0, 2) }}</span>
            </div>
        @endforeach
        @if($order->descuento > 0)
            <div style="display:flex;justify-content:space-between;font-size:13px;color:var(--teal-osc);font-weight:700;padding-top:6px">
                <span>Cupón {{ $order->cupon }}</span><span>− ${{ number_format($order->descuento, 2) }}</span>
            </div>
        @endif
        <div style="display:flex;justify-content:space-between;font-size:13px;color:var(--gris);padding-top:6px">
            <span>Envío</span><span>${{ number_format($order->shipping, 2) }}</span>
        </div>
        <div style="display:flex;justify-content:space-between;font-weight:800;color:var(--azul-osc);border-top:1px solid var(--borde);margin-top:6px;padding-top:8px">
            <span>Total</span><span>${{ number_format($order->total, 2) }}</span>
        </div>
        @if($order->guia)
            <div style="margin-top:12px;font-size:13px;color:var(--gris)">Nº de guía: <b style="color:var(--texto)">{{ $order->guia }}</b>
                · <a href="{{ $order->urlRastreoExpress() }}" target="_blank" rel="noopener" style="color:var(--azul-osc);font-weight:700">Ver detalle del courier ↗</a>
            </div>
        @endif
    </div>

    <div style="text-align:center;margin-top:20px">
        <a class="sg-wa" target="_blank" rel="noopener"
           href="https://wa.me/{{ config('babyconfort.whatsapp') }}?text=Hola%2C%20consulto%20por%20mi%20pedido%20%23{{ $order->id }}">
            💬 ¿Dudas con tu pedido? Escríbenos
        </a>
    </div>
    <p style="text-align:center;color:var(--gris);font-size:12px;margin-top:14px">El estado se actualiza automáticamente. Vuelve a abrir el enlace para ver el avance.</p>
</main>
@endsection
