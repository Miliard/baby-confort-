@php
    $bebe = [
        'S'    => '4–8 kg · 9–18 lb',
        'M'    => '6–11 kg · 13–24 lb',
        'L'    => '9–14 kg · 20–31 lb',
        'XL'   => '12–17 kg · 26–37 lb',
        'XXL'  => '15–21 kg · 33–46 lb',
        'XXXL' => '18–25 kg · 39–55 lb',
    ];
    $especiales = \App\Models\ProductSize::query()
        ->whereHas('product', fn ($q) => $q->where('active', true))
        ->where('size', 'like', '%año%')
        ->where('quantity', '>', 0)
        ->get()
        ->unique(fn ($s) => \Illuminate\Support\Str::slug($s->size))
        ->values();
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

    @if ($especiales->count())
    <div class="sg-group-title alt">Para niños grandes</div>
    <div class="sg-grid sg-grid-2">
        @foreach ($especiales as $s)
            <a class="sg-card nino" href="{{ route('store.talla', \Illuminate\Support\Str::slug($s->size)) }}">
                <span class="sg-cpill alt">{{ $s->size }}</span>
                <span class="sg-crango">{{ $s->weight }}</span>
            </a>
        @endforeach
    </div>
    @endif

    <div class="sg-help">¿No sabes la talla? Dinos el peso del bebé y te recomendamos la ideal 💙</div>
    <a class="sg-wa" target="_blank" rel="noopener"
       href="https://wa.me/{{ config('babyconfort.whatsapp') }}?text=Hola%2C%20quiero%20saber%20qu%C3%A9%20talla%20le%20queda%20a%20mi%20beb%C3%A9%20%F0%9F%91%B6">
        💬 Pregúntanos tu talla por WhatsApp
    </a>
</section>
