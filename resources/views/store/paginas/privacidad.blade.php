@extends('layouts.store')
@section('title', 'Política de privacidad | Baby-Confort')

@section('content')
<main class="contenedor pagina-legal">
    <a href="/" class="volver" style="color:var(--azul-osc);font-weight:700;text-decoration:none">← Volver a la tienda</a>
    <h1>Política de privacidad 🔒</h1>

    <p>En Baby-Confort respetamos tu privacidad y cuidamos la información que compartes con nosotros. Aquí te explicamos qué datos recopilamos y cómo los usamos.</p>

    <h2>Qué información recopilamos</h2>
    <p>Cuando haces un pedido, te pedimos tu nombre, número de teléfono/WhatsApp, municipio y dirección. Usamos estos datos únicamente para procesar tu pedido, coordinar la entrega y comunicarnos contigo sobre tu compra.</p>

    <h2>Cómo usamos tus datos</h2>
    <p>Tu información se utiliza para: confirmar y enviar tu pedido, coordinar la entrega, brindarte soporte y, si lo deseas, informarte sobre promociones. No vendemos ni alquilamos tus datos personales a terceros.</p>

    <h2>Con quién los compartimos</h2>
    <p>Solo compartimos los datos necesarios con la empresa de mensajería para poder entregar tu pedido. No compartimos tu información con nadie más sin tu consentimiento.</p>

    <h2>Cookies y medición</h2>
    <p>Nuestro sitio puede usar herramientas de medición (como el Píxel de Facebook) para entender cómo se usa la tienda y mejorar tu experiencia. Puedes desactivar las cookies desde la configuración de tu navegador.</p>

    <h2>Tus derechos</h2>
    <p>Puedes pedirnos en cualquier momento que actualicemos o eliminemos tus datos personales. Solo escríbenos por WhatsApp y con gusto te ayudamos.</p>

    <div class="pl-cta">
        <a href="https://wa.me/{{ config('babyconfort.whatsapp') }}?text={{ rawurlencode('Hola, tengo una consulta sobre mis datos') }}" target="_blank" rel="noopener" class="sg-wa">💬 Escríbenos por WhatsApp</a>
    </div>
</main>
@include('store.partials.pagina-estilo')
@endsection
