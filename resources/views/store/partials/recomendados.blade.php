@if(!empty($recomendados) && count($recomendados))
<section style="max-width:720px;margin:24px auto 0">
    <h3 style="color:var(--azul-osc);font-size:18px;margin:0 0 2px">✨ Aprovecha y llévate más</h3>
    <p style="color:var(--gris);font-size:13px;margin:0 0 12px">Desliza y descubre más 👉</p>
    <div class="reco-carousel">
        @foreach($recomendados as $rp)
            <a class="reco-card" href="{{ route('store.show', $rp) }}">
                <div class="reco-img">@if($rp->oferta)<span class="oferta-bubble" style="font-size:10px;padding:3px 7px">{{ $rp->oferta }}</span>@endif<img src="{{ $rp->imageUrl() }}" alt="{{ $rp->name }}" loading="lazy"></div>
                <div class="reco-nom">{{ $rp->name }}</div>
                <div class="reco-precio">desde ${{ number_format($rp->precioDesde(), 2) }}</div>
            </a>
        @endforeach
    </div>
</section>
<style>
    .reco-carousel{display:flex;gap:12px;overflow-x:auto;padding:4px 2px 12px;scroll-snap-type:x mandatory;-webkit-overflow-scrolling:touch}
    .reco-carousel::-webkit-scrollbar{height:6px}
    .reco-carousel::-webkit-scrollbar-thumb{background:#d7ddef;border-radius:99px}
    .reco-card{flex:0 0 152px;scroll-snap-align:start;background:#fff;border:1px solid var(--borde);border-radius:14px;overflow:hidden;text-decoration:none;color:inherit;box-shadow:var(--sombra);transition:transform .1s}
    .reco-card:hover{transform:translateY(-3px)}
    .reco-img{position:relative;height:132px;background:var(--azul-claro);display:grid;place-items:center;padding:8px}
    .reco-img img{max-width:100%;max-height:100%;object-fit:contain}
    .reco-nom{font-size:12.5px;font-weight:700;line-height:1.25;color:var(--texto);padding:9px 10px 0;height:46px;overflow:hidden}
    .reco-precio{font-size:14px;font-weight:800;color:var(--azul-osc);padding:2px 10px 11px}
</style>
@endif
