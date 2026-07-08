@if(!empty($recomendados) && count($recomendados))
<section style="max-width:720px;margin:30px auto 0">
    <h3 style="color:var(--azul-osc);font-size:19px;margin:0 0 2px">✨ Aprovecha y llévate más</h3>
    <p style="color:var(--gris);font-size:13px;margin:0 0 14px">Productos que le encantan a otras mamás 💙</p>
    <div class="grid">
        @foreach($recomendados as $rp)
            <a class="pcard" href="{{ route('store.show', $rp) }}" style="text-decoration:none;color:inherit">
                <div class="img">@if($rp->oferta)<span class="oferta-bubble">{{ $rp->oferta }}</span>@endif<img src="{{ $rp->imageUrl() }}" alt="{{ $rp->name }}" loading="lazy"></div>
                <div class="body">
                    <div class="marca">{{ $rp->brand }}</div>
                    <div class="nom">{{ $rp->name }}</div>
                    <div class="precio">desde ${{ number_format($rp->precioDesde(), 2) }}</div>
                    <span class="btn btn-azul" style="text-align:center;margin-top:8px">Ver producto →</span>
                </div>
            </a>
        @endforeach
    </div>
</section>
@endif
