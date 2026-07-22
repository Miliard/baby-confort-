@if(!empty($historial))
<div class="trk-hist">
    <div class="trk-hist-h">🕒 Actualizaciones del paquete</div>
    @foreach($historial as $i => $ev)
        <div class="trk-hist-row">
            <div class="trk-hist-dot {{ $i === 0 ? 'now' : '' }}"></div>
            <div class="trk-hist-body">
                <div class="trk-hist-estado">{{ $ev['estado'] }} <span class="trk-hist-desc">{{ $ev['desc'] }}</span></div>
                <div class="trk-hist-fecha">{{ $ev['fecha'] }}</div>
            </div>
        </div>
    @endforeach
    <div class="trk-hist-nota">Actualizaciones en vivo desde Express El Salvador.</div>
</div>
<style>
    .trk-hist{background:#fff;border:1px solid var(--borde);border-radius:14px;padding:16px 18px;margin-top:16px}
    html.dark .trk-hist{background:#16202f}
    .trk-hist-h{font-weight:800;color:var(--azul-osc);margin-bottom:12px;font-size:15px}
    .trk-hist-row{display:flex;gap:12px;padding:9px 0;border-bottom:1px dashed var(--borde)}
    .trk-hist-row:last-of-type{border-bottom:none}
    .trk-hist-dot{width:11px;height:11px;border-radius:999px;background:#c7d2dd;margin-top:5px;flex:none}
    .trk-hist-dot.now{background:var(--ok);box-shadow:0 0 0 4px rgba(46,158,107,.18)}
    .trk-hist-estado{font-weight:700;font-size:14px;color:var(--texto)}
    .trk-hist-desc{font-weight:500;color:var(--gris)}
    .trk-hist-fecha{font-size:12.5px;color:var(--gris);margin-top:2px}
    .trk-hist-nota{font-size:11.5px;color:var(--gris);margin-top:10px}
</style>
@endif
