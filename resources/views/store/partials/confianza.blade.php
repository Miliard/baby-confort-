@php
    $envioC  = \App\Models\Setting::envio();
    $tiempoC = \App\Models\Setting::get('envio_tiempo', '24 horas hábiles');
@endphp
<section class="contenedor" style="padding:26px 16px 10px">
    <h2 class="seccion-titulo">Comprar es fácil y seguro</h2>
    <div class="conf-grid">
        <div class="conf-card">
            <div class="conf-ic"><svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="8" cy="21" r="1"/><circle cx="19" cy="21" r="1"/><path d="M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h9.78a2 2 0 0 0 1.95-1.57l1.65-7.43H5.12"/></svg></div>
            <div class="conf-t">Cómo comprar</div>
            <p>Elige tu producto y talla, agrégalo al carrito y confirma. Te contactamos por WhatsApp para coordinar tu entrega.</p>
        </div>
        <div class="conf-card">
            <div class="conf-ic"><svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10 17h4V5H2v12h3M20 17h2v-3.34a4 4 0 0 0-1.17-2.83L19 9h-5v8h2"/><circle cx="7.5" cy="17.5" r="2.5"/><circle cx="17.5" cy="17.5" r="2.5"/></svg></div>
            <div class="conf-t">Envíos</div>
            <p>Entregamos en todo El Salvador en {{ $tiempoC }}. Costo de envío ${{ number_format($envioC, 2) }}. Y puedes seguir tu pedido en línea.</p>
        </div>
        <div class="conf-card">
            <div class="conf-ic"><svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/></svg></div>
            <div class="conf-t">Pagos</div>
            <p>Paga como te quede mejor: transferencia bancaria, efectivo contra entrega o link de pago.</p>
        </div>
    </div>
</section>
<style>
    .conf-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-top:14px}
    @media(max-width:700px){.conf-grid{grid-template-columns:1fr}}
    .conf-card{background:#fff;border:1px solid var(--borde);border-radius:14px;padding:18px;box-shadow:var(--sombra)}
    .conf-ic{width:46px;height:46px;border-radius:14px;background:var(--azul-claro);color:var(--azul-osc);display:grid;place-items:center}
    .conf-t{font-weight:800;color:var(--azul-osc);margin:6px 0 4px;font-size:16px}
    .conf-card p{font-size:13.5px;color:var(--gris);line-height:1.55;margin:0}
</style>
