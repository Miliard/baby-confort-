@php
    $faqs = [
        ['q' => '¿Los productos son hipoalergénicos?', 'a' => 'Sí. Nuestros pañales y calzoncitos tienen un diseño hipoalergénico, con materiales suaves pensados para cuidar la piel delicada del bebé y reducir el riesgo de irritaciones.'],
        ['q' => '¿Cuánto tarda la entrega?', 'a' => 'Coordinamos la entrega por WhatsApp apenas recibimos tu pedido. Normalmente entregamos en 24 horas hábiles dentro de El Salvador. Al confirmar te damos un enlace para rastrear tu paquete en vivo.'],
        ['q' => '¿Puedo cambiar el producto si me equivoco de talla?', 'a' => 'Claro. Si el empaque no ha sido abierto, coordinamos el cambio por la talla correcta. Escríbenos por WhatsApp y te ayudamos. También podemos recomendarte la talla ideal según el peso del bebé.'],
        ['q' => '¿Cómo puedo pagar?', 'a' => 'Aceptamos transferencia bancaria, efectivo contra entrega y link de pago. Eliges tu forma de pago al hacer el pedido y lo confirmamos por WhatsApp.'],
        ['q' => '¿Hacen envíos a todo el país?', 'a' => 'Sí, hacemos envíos a todo El Salvador. El costo de envío se muestra en tu carrito antes de confirmar.'],
    ];
@endphp
<section class="faq" x-data="{ abierta: null }">
    <h2 class="faq-titulo">Preguntas frecuentes 💬</h2>
    <div class="faq-list">
        @foreach($faqs as $i => $f)
            <div class="faq-item" :class="abierta === {{ $i }} ? 'on' : ''">
                <button class="faq-q" @click="abierta = abierta === {{ $i }} ? null : {{ $i }}">
                    <span>{{ $f['q'] }}</span>
                    <span class="faq-ic" x-text="abierta === {{ $i }} ? '−' : '+'"></span>
                </button>
                <div class="faq-a" x-show="abierta === {{ $i }}" x-transition>{{ $f['a'] }}</div>
            </div>
        @endforeach
    </div>
    <div class="faq-garantia">
        🛡️ <b>Compra con confianza:</b> si algo no está bien con tu pedido, escríbenos por WhatsApp y lo resolvemos. Tu satisfacción es lo primero.
    </div>
</section>
<style>
    .faq{max-width:760px;margin:8px auto 36px}
    .faq-titulo{font-size:22px;margin:0 0 16px;text-align:center}
    .faq-list{display:flex;flex-direction:column;gap:10px}
    .faq-item{background:#fff;border:1px solid var(--borde);border-radius:14px;overflow:hidden;transition:border-color .12s,box-shadow .12s}
    .faq-item.on{border-color:var(--azul);box-shadow:0 6px 16px rgba(47,127,191,.10)}
    .faq-q{width:100%;background:none;border:none;cursor:pointer;display:flex;align-items:center;justify-content:space-between;gap:12px;padding:15px 18px;font-weight:700;font-size:15px;color:var(--texto);text-align:left}
    .faq-ic{flex:none;width:26px;height:26px;border-radius:999px;background:var(--azul-claro);color:var(--azul-osc);display:grid;place-items:center;font-size:18px;font-weight:800}
    .faq-a{padding:0 18px 16px;color:var(--gris);font-size:14.5px;line-height:1.6}
    .faq-garantia{margin-top:16px;background:#eef8f2;border:1px solid #bfe6cf;border-radius:14px;padding:14px 16px;font-size:14px;line-height:1.55;color:var(--texto)}
</style>
