@extends('layouts.store')
@section('title', 'Cambios y devoluciones | Baby-Confort')

@section('content')
<main class="contenedor pagina-legal">
    <a href="/" class="volver" style="color:var(--azul-osc);font-weight:700;text-decoration:none">← Volver a la tienda</a>
    <h1>Cambios y devoluciones 🔄</h1>

    <p>Queremos que estés 100% conforme con tu compra. Si necesitas un cambio o tienes algún problema con tu pedido, aquí te explicamos cómo lo manejamos.</p>

    <h2>Cambios de talla</h2>
    <p>Si te equivocaste de talla, podemos coordinar el cambio siempre que el empaque <b>no haya sido abierto</b> y el producto esté en las mismas condiciones en que lo recibiste. Escríbenos por WhatsApp dentro de los primeros días después de recibir tu pedido y te ayudamos.</p>

    <h2>Producto dañado o incorrecto</h2>
    <p>Si tu pedido llegó dañado o no corresponde a lo que ordenaste, contáctanos de inmediato por WhatsApp con una foto del producto. Lo revisamos y coordinamos la solución: reemplazo o el ajuste que corresponda, sin costo adicional para ti.</p>

    <h2>¿Cómo solicito un cambio?</h2>
    <p>Es muy sencillo: escríbenos por WhatsApp indicando tu número de pedido y el motivo. Nuestro equipo te guiará en el proceso y coordinará la logística de la manera más cómoda para ti.</p>

    <h2>Consideraciones</h2>
    <p>Por higiene y por tratarse de productos de cuidado del bebé, no podemos aceptar devoluciones de empaques abiertos o productos usados, salvo defecto de fábrica. Agradecemos tu comprensión: es para cuidar la seguridad de todas las familias.</p>

    <div class="pl-cta">
        <a href="https://wa.me/{{ config('babyconfort.whatsapp') }}?text={{ rawurlencode('Hola, necesito ayuda con un cambio/devolución') }}" target="_blank" rel="noopener" class="sg-wa">💬 Solicitar un cambio por WhatsApp</a>
    </div>
</main>
@include('store.partials.pagina-estilo')
@endsection
