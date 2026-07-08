@php
    $resenas = \App\Models\Review::where('active', true)->orderBy('orden')->orderBy('id')->get();
@endphp
@if($resenas->count())
<section class="contenedor" style="padding:26px 16px 6px">
    <h2 class="seccion-titulo">Lo que dicen nuestras clientas 💬</h2>
    <p class="seccion-sub">Reseñas reales de familias que ya confían en Baby-Confort.</p>
    <div class="resenas-grid">
        @foreach($resenas as $r)
            <div class="resena-card">
                @if($r->imageUrl())
                    <img src="{{ $r->imageUrl() }}" alt="Reseña de cliente" loading="lazy" style="width:100%;border-radius:10px;display:block">
                @else
                    <div class="resena-stars">{{ str_repeat('★', $r->rating) }}</div>
                    <p class="resena-text">{{ $r->text }}</p>
                    <div class="resena-name">
                        <span class="resena-avatar">{{ mb_strtoupper(mb_substr($r->name ?? '?', 0, 1)) }}</span>
                        <b>{{ $r->name }}</b>
                    </div>
                @endif
            </div>
        @endforeach
    </div>
</section>
<style>
    .resenas-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(250px,1fr));gap:14px;margin-top:14px}
    @media(max-width:600px){.resenas-grid{grid-template-columns:repeat(2,1fr);gap:10px}}
    .resena-card{background:#fff;border:1px solid var(--borde);border-radius:14px;padding:14px;box-shadow:var(--sombra)}
    .resena-stars{color:#f5a623;font-size:16px;letter-spacing:2px;margin-bottom:6px}
    .resena-text{font-size:13.5px;color:var(--texto);line-height:1.5;margin:0 0 10px}
    .resena-name{display:flex;align-items:center;gap:8px;font-size:13px;color:var(--texto)}
    .resena-avatar{width:30px;height:30px;border-radius:50%;background:linear-gradient(135deg,var(--azul),var(--azul-osc));color:#fff;display:grid;place-items:center;font-weight:800;font-size:13px;flex:none}
</style>
@endif
