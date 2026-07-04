@php
    $bebe = [
        'S'    => '4–8 kg · 9–18 lb',
        'M'    => '6–11 kg · 13–24 lb',
        'L'    => '9–14 kg · 20–31 lb',
        'XL'   => '12–17 kg · 26–37 lb',
        'XXL'  => '15–21 kg · 33–46 lb',
        'XXXL' => '18–25 kg · 39–55 lb',
    ];
    $ninos = [
        '4–7 años'  => '37–64 lb',
        '8–15 años' => '60–125 lb',
    ];
    $comfynite = \App\Models\Product::where('active', true)->where('name', 'like', '%ComfyNite%')->first();
    $ninoUrl   = $comfynite ? route('store.show', $comfynite) : route('store.index');
@endphp
<section class="sg-wrap">
    <h3>Compra por talla 👶</h3>
    <p class="sg-sub">Toca tu talla y verás todos los productos disponibles en ella.</p>

    <div class="sg-group-title">Tallas de bebé</div>
    <div class="sg-grid sg-grid-3">
        @foreach ($bebe as $t => $rango)
            <a class="sg-card" href="{{ route('store.talla', $t) }}">
                <span class="sg-cpill">Talla {{ $t }}</span>
                <span class="sg-crango">{{ $rango }}</span>
            </a>
        @endforeach
    </div>

    <div class="sg-group-title alt">Para niños grandes</div>
    <div class="sg-grid sg-grid-2">
        @foreach ($ninos as $t => $rango)
            <a class="sg-card nino" href="{{ $ninoUrl }}">
                <span class="sg-cpill alt">{{ $t }}</span>
                <span class="sg-crango">{{ $rango }}</span>
            </a>
        @endforeach
    </div>

    <div class="sg-help">¿No sabes la talla? Dinos el peso del bebé y te recomendamos la ideal 💙</div>
    <a class="sg-wa" target="_blank" rel="noopener"
       href="https://wa.me/{{ config('babyconfort.whatsapp') }}?text=Hola%2C%20quiero%20saber%20qu%C3%A9%20talla%20le%20queda%20a%20mi%20beb%C3%A9%20%F0%9F%91%B6">
        💬 Pregúntanos tu talla por WhatsApp
    </a>
</section>
