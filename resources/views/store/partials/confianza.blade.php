@php
    $envioC  = \App\Models\Setting::envio();
    $tiempoC = \App\Models\Setting::get('envio_tiempo', '24 horas hábiles');
@endphp
<section class="contenedor" style="padding:26px 16px 10px">
    <h2 class="seccion-titulo">Comprar es fácil y seguro 🛡️</h2>
    <div class="conf-grid">
        <div class="conf-card">
            <div class="conf-ic">🛒</div>
            <div class="conf-t">Cómo comprar</div>
            <p>Elige tu producto y talla, agrégalo al carrito y confirma. Te contactamos por WhatsApp para coordinar tu entrega.</p>
        </div>
        <div class="conf-card">
            <div class="conf-ic">🚚</div>
            <div class="conf-t">Envíos</div>
            <p>Entregamos en todo El Salvador en {{ $tiempoC }}. Costo de envío ${{ number_format($envioC, 2) }}. Y puedes seguir tu pedido en línea.</p>
        </div>
        <div class="conf-card">
            <div class="conf-ic">💳</div>
            <div class="conf-t">Pagos</div>
            <p>Paga como te quede mejor: transferencia bancaria, efectivo contra entrega o link de pago.</p>
        </div>
    </div>
</section>
<style>
    .conf-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-top:14px}
    @media(max-width:700px){.conf-grid{grid-template-columns:1fr}}
    .conf-card{background:#fff;border:1px solid var(--borde);border-radius:14px;padding:18px;box-shadow:var(--sombra)}
    .conf-ic{font-size:30px}
    .conf-t{font-weight:800;color:var(--azul-osc);margin:6px 0 4px;font-size:16px}
    .conf-card p{font-size:13.5px;color:var(--gris);line-height:1.55;margin:0}
</style>
