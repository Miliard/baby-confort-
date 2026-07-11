@if(isset($relacionados) && $relacionados->count())
<section class="relacionados">
    <h2 class="rel-titulo">También te puede interesar 🛍️</h2>
    <div class="rel-grid">
        @foreach($relacionados as $r)
            <a class="pcard" href="{{ route('store.show', $r) }}">
                <div class="img">@if($r->oferta)<span class="oferta-bubble">{{ $r->oferta }}</span>@endif<img src="{{ $r->imageUrl() }}" alt="{{ $r->name }}" loading="lazy"></div>
                <div class="body">
                    <div class="marca">{{ $r->brand }}</div>
                    <div class="nom">{{ $r->name }}</div>
                    <div class="precio">desde ${{ number_format($r->precioDesde(), 2) }}</div>
                    <div class="ver">Ver producto →</div>
                </div>
            </a>
        @endforeach
    </div>
</section>
<style>
    .relacionados{margin:8px 0 40px}
    .rel-titulo{font-size:22px;margin:0 0 18px}
    .rel-grid{display:grid;gap:18px;grid-template-columns:repeat(4,1fr)}
    @media(max-width:820px){.rel-grid{grid-template-columns:repeat(2,1fr);gap:12px}}
</style>
@endif
