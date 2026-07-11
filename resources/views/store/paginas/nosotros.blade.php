@extends('layouts.store')
@section('title', 'Nosotros | Baby-Confort')

@section('content')
<main class="contenedor pagina-legal">
    <a href="/" class="volver" style="color:var(--azul-osc);font-weight:700;text-decoration:none">← Volver a la tienda</a>
    <h1>Sobre Baby-Confort 👶</h1>

    <p>Baby-Confort es una tienda salvadoreña dedicada al bienestar de los más pequeños de la casa. Nacimos con una idea simple: que cada familia pueda encontrar pañales y productos de calidad, suaves y de alta absorción, sin complicaciones y a un precio justo.</p>

    <p>Seleccionamos productos pensados para cuidar la piel delicada del bebé, con diseño hipoalergénico y materiales que dan comodidad durante el día y protección durante la noche. Sabemos que cada bebé es diferente, por eso te ayudamos a elegir la talla ideal según su peso.</p>

    <h2>¿Por qué comprar con nosotros?</h2>
    <p>Atendemos de forma personalizada por WhatsApp, coordinamos la entrega en todo El Salvador y te acompañamos en cada paso, desde que eliges tu producto hasta que llega a tu puerta. Además, puedes seguir tu pedido en vivo con nuestro enlace de rastreo.</p>

    <h2>Nuestro compromiso</h2>
    <p>Queremos que compres con confianza. Si algo no está bien con tu pedido, escríbenos y lo resolvemos. Tu tranquilidad y la comodidad de tu bebé son lo más importante para nosotros.</p>

    <div class="pl-cta">
        <a href="https://wa.me/{{ config('babyconfort.whatsapp') }}?text={{ rawurlencode('Hola, quiero más información 👶') }}" target="_blank" rel="noopener" class="sg-wa">💬 Escríbenos por WhatsApp</a>
    </div>
</main>
@include('store.partials.pagina-estilo')
@endsection
