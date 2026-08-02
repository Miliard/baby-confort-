@php $fotosPaquete = \App\Models\GuiaFoto::deGuia($guiaFoto ?? null); @endphp

@if($fotosPaquete->count())
<div class="fp-wrap">
    <div class="fp-titulo">📦 Así va tu paquete</div>
    <div class="fp-grid">
        @foreach($fotosPaquete as $f)
            <a href="{{ $f->url() }}" target="_blank" rel="noopener">
                <img src="{{ $f->url() }}" alt="Foto de tu paquete" loading="lazy">
            </a>
        @endforeach
    </div>
    <div class="fp-nota">Foto real de tu pedido antes de enviarlo. 💙</div>
</div>
<style>
    .fp-wrap{background:#fff;border:1px solid var(--borde);border-radius:14px;padding:16px 18px;margin-top:16px;text-align:center}
    html.dark .fp-wrap{background:#16202f}
    .fp-titulo{font-weight:800;color:var(--azul-osc);margin-bottom:12px;font-size:15px}
    .fp-grid{display:grid;gap:10px;grid-template-columns:repeat(auto-fit,minmax(140px,1fr))}
    .fp-grid img{width:100%;border-radius:10px;border:1px solid var(--borde);object-fit:cover;max-height:320px}
    .fp-nota{font-size:12.5px;color:var(--gris);margin-top:10px}
</style>
@endif
