@php
    $rangos = [
        'S' => '4 – 8 kg&nbsp; / &nbsp;9 – 18 lb',
        'M' => '6 – 11 kg&nbsp; / &nbsp;13 – 24 lb',
        'L' => '9 – 14 kg&nbsp; / &nbsp;20 – 31 lb',
        'XL' => '12 – 17 kg&nbsp; / &nbsp;26 – 37 lb',
        'XXL' => '15 – 21 kg&nbsp; / &nbsp;33 – 46 lb',
        'XXXL' => '18 – 25 kg&nbsp; / &nbsp;39 – 55 lb',
    ];
@endphp
<section class="sg-wrap">
    <h3>Compra por talla 👶</h3>
    <p style="margin:-6px 0 14px;color:var(--gris);font-size:13.5px">Toca tu talla y verás todos los productos disponibles en ella.</p>

    <div class="sg-list">
        @foreach ($rangos as $t => $rango)
            <a class="sg-row" href="{{ route('store.talla', $t) }}">
                <span class="sg-pill">Talla {{ $t }}</span>
                <span class="sg-range">{!! $rango !!}</span>
                <span class="sg-more">Ver ›</span>
            </a>
        @endforeach
    </div>

    <div class="sg-esp-title">Tallas especiales</div>
    <div class="sg-list">
        <div class="sg-row" style="cursor:default"><span class="sg-pill alt">4 – 7 años</span><span class="sg-range">37 – 64 lb</span></div>
        <div class="sg-row" style="cursor:default"><span class="sg-pill alt">8 – 15 años</span><span class="sg-range">60 – 125 lb</span></div>
    </div>

    <p class="sg-msg">Indícanos el peso del bebé o niño y con gusto te recomendamos la talla ideal 💙</p>
    <a class="sg-wa" target="_blank" rel="noopener"
       href="https://wa.me/{{ config('babyconfort.whatsapp') }}?text=Hola%2C%20quiero%20saber%20qu%C3%A9%20talla%20le%20queda%20a%20mi%20beb%C3%A9%20%F0%9F%91%B6">
        💬 Pregúntanos tu talla por WhatsApp
    </a>
</section>
