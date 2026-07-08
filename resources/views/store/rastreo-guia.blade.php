@extends('layouts.store')
@section('title', 'Rastrea tu pedido | Baby-Confort')

@section('content')
@php
    $etapas = \App\Models\Order::ETAPAS;
    $iconos = [1 => '✅', 2 => '📦', 3 => '🚚', 4 => '🏠'];
    $fill = $etapa ? (($etapa - 1) / 3) * 75 : 0;
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
    .trk-form{display:flex;gap:8px;margin-top:18px;flex-wrap:wrap}
    .trk-form input{flex:1;min-width:180px;padding:12px 14px;border:1px solid var(--borde);border-radius:12px;font-size:15px}
    .trk-form button{background:linear-gradient(135deg,var(--azul),var(--azul-osc));color:#fff;border:none;border-radius:12px;padding:12px 22px;font-weight:800;font-size:15px;cursor:pointer}
    @media(max-width:460px){.trk-dot{width:48px;height:48px;font-size:22px}.trk-line,.trk-fill{top:23px}.trk-lbl{font-size:11.5px}}
</style>
<main class="trk-wrap">
    <a href="/" class="volver" style="color:var(--azul-osc);font-weight:700;text-decoration:none">← Volver a la tienda</a>
    <h1 style="margin:12px 0 4px;color:var(--texto)">Rastrea tu pedido 📦</h1>
    <p style="color:var(--gris);margin:0">Ingresa tu número de guía para ver el estado de tu envío en vivo.</p>

    <form method="GET" action="{{ route('store.rastreo.guia') }}" class="trk-form">
        <input type="text" name="guia" value="{{ $guia }}" placeholder="Número de guía (ej: 5009506)" required>
        <button type="submit">Rastrear</button>
    </form>

    @if($guia && $etapa)
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
                @if($etapa < 4) Tu pedido está en proceso. 💙
                @else ¡Tu pedido fue entregado! Gracias por tu compra. 🎉 @endif
            </div>
            <div style="margin-top:10px;font-size:12.5px;color:var(--gris)">Guía: <b style="color:var(--texto)">{{ $guia }}</b>
                · <a href="https://expresselsalvador.sistrack.net/track/{{ $guia }}" target="_blank" rel="noopener" style="color:var(--azul-osc);font-weight:700">Detalle del courier ↗</a>
            </div>
        </div>
    @endif

    <div style="text-align:center;margin-top:22px">
        <a class="sg-wa" target="_blank" rel="noopener"
           href="https://wa.me/{{ config('babyconfort.whatsapp') }}?text=Hola%2C%20consulto%20por%20mi%20pedido">
            💬 ¿Dudas? Escríbenos por WhatsApp
        </a>
    </div>
</main>
@endsection
