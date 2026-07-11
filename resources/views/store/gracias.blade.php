@extends('layouts.store')
@section('title', '¡Gracias por tu pedido! | Baby-Confort')

@section('content')
<main class="contenedor gracias-wrap">
    <div class="gracias-card">
        <div class="gracias-check">✅</div>
        <h1>¡Gracias por tu pedido, {{ \Illuminate\Support\Str::of($order->customer_name)->before(' ') }}! 💙</h1>
        <p class="gracias-sub">Recibimos tu pedido <b>#{{ $order->id }}</b>. En un momento te escribimos por WhatsApp para coordinar la entrega.</p>

        <div class="gracias-resumen">
            <div class="gr-titulo">Resumen de tu pedido</div>
            @foreach($order->items as $it)
                <div class="gr-linea">
                    <span>{{ $it['cantidad'] }} × {{ $it['producto'] }} <small>({{ $it['talla'] }})</small></span>
                    <b>${{ number_format($it['subtotal'] ?? 0, 2) }}</b>
                </div>
            @endforeach
            <div class="gr-linea gr-envio"><span>Envío</span><span>${{ number_format($order->shipping, 2) }}</span></div>
            <div class="gr-linea gr-total"><span>Total</span><b>${{ number_format($order->total, 2) }}</b></div>
        </div>

        <div class="gracias-pasos">
            <div class="gp"><span class="gp-n">1</span> Te contactamos por WhatsApp para confirmar tu dirección y forma de pago.</div>
            <div class="gp"><span class="gp-n">2</span> Preparamos y enviamos tu pedido (normalmente en 24 h hábiles).</div>
            <div class="gp"><span class="gp-n">3</span> Sigue tu paquete en vivo con el enlace de rastreo. 📦</div>
        </div>

        <div class="gracias-btns">
            <a class="gbtn gbtn-track" href="{{ route('store.rastreo', $order) }}">📦 Rastrear mi pedido</a>
            <a class="gbtn gbtn-wa" target="_blank" rel="noopener"
               href="https://wa.me/{{ config('babyconfort.whatsapp') }}?text={{ rawurlencode('Hola, acabo de hacer el pedido #' . $order->id) }}">💬 Escribirnos por WhatsApp</a>
            <a class="gbtn gbtn-more" href="{{ route('store.index') }}">← Seguir comprando</a>
        </div>
    </div>
</main>
<style>
    .gracias-wrap{padding:30px 16px 60px;display:flex;justify-content:center}
    .gracias-card{background:#fff;border:1px solid var(--borde);border-radius:22px;box-shadow:var(--sombra);max-width:560px;width:100%;padding:34px 26px;text-align:center}
    .gracias-check{font-size:52px;line-height:1;margin-bottom:8px}
    .gracias-card h1{font-size:24px;margin:6px 0 10px;line-height:1.25}
    .gracias-sub{color:var(--gris);font-size:15px;margin:0 0 22px;line-height:1.6}
    .gracias-resumen{background:var(--azul-claro);border-radius:14px;padding:16px 18px;text-align:left;margin-bottom:20px}
    .gr-titulo{font-weight:800;font-size:13px;text-transform:uppercase;letter-spacing:.5px;color:var(--azul-osc);margin-bottom:10px}
    .gr-linea{display:flex;justify-content:space-between;gap:12px;font-size:14.5px;padding:5px 0;color:var(--texto)}
    .gr-linea small{color:var(--gris)}
    .gr-envio{color:var(--gris);border-top:1px dashed var(--borde);margin-top:6px;padding-top:10px}
    .gr-total{border-top:1px solid var(--borde);margin-top:4px;padding-top:10px;font-size:17px}
    .gr-total b{color:var(--azul-osc)}
    .gracias-pasos{text-align:left;display:flex;flex-direction:column;gap:12px;margin-bottom:24px}
    .gp{display:flex;align-items:flex-start;gap:12px;font-size:14px;color:var(--texto);line-height:1.5}
    .gp-n{flex:none;width:26px;height:26px;border-radius:999px;background:var(--teal);color:#fff;display:grid;place-items:center;font-weight:800;font-size:13px}
    .gracias-btns{display:flex;flex-direction:column;gap:10px}
    .gbtn{border-radius:12px;padding:14px;font-weight:800;font-size:15px}
    .gbtn-track{background:var(--azul);color:#fff}
    .gbtn-wa{background:#25D366;color:#fff}
    .gbtn-more{background:#fff;border:1px solid var(--borde);color:var(--texto)}
</style>
@endsection
