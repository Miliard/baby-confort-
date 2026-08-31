<x-filament-panels::page>

<style>
    .bc-b{max-width:1000px}
    .bc-caja{background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:16px;margin-bottom:14px}
    html.dark .bc-caja{background:#16202f;border-color:rgba(255,255,255,.10)}
    .bc-caja h3{margin:0 0 2px;font-size:14px;font-weight:800}
    .bc-caja .desc{font-size:12px;color:#94a3b8;margin:0 0 12px}
    .bc-fila{display:flex;flex-wrap:wrap;gap:10px;align-items:baseline;
             padding:10px 0;border-bottom:1px solid rgba(120,140,170,.16)}
    .bc-fila:last-child{border-bottom:none}
    .bc-nom{font-weight:700;font-size:14.5px;flex:1;min-width:180px}
    .bc-dato{font-size:12.5px;color:#94a3b8}
    .bc-guia{font-family:ui-monospace,Consolas,monospace;font-size:13px;color:#4aa3df;font-weight:700}
    .bc-monto{font-weight:800;font-size:15px;white-space:nowrap}
    .bc-cobro{color:#1c7a4d}
    .bc-nocobro{color:#b45309}
    .bc-nota{width:100%;font-size:12px;color:#94a3b8;margin-top:-4px}
    .bc-eti{display:inline-block;font-size:11px;font-weight:700;border-radius:6px;padding:2px 7px}
    .bc-eti-dev{background:rgba(229,105,95,.16);color:#b91c1c}
    .bc-eti-typ{background:rgba(224,163,59,.16);color:#92400e}
    .bc-vacio{font-size:13px;color:#94a3b8;padding:6px 0}
    .bc-ayuda{font-size:12.5px;color:#94a3b8;line-height:1.7;margin-top:10px}
</style>

<div class="bc-b">

    <div class="bc-caja">
        <x-filament::input.wrapper prefix-icon="heroicon-m-magnifying-glass">
            <x-filament::input type="text" wire:model.live.debounce.500ms="q"
                placeholder="Nombre, teléfono o número de guía" autofocus />
        </x-filament::input.wrapper>

        @if(! $this->buscando())
            <p class="bc-ayuda">
                Escribí al menos 3 letras o 4 números. Busca en <b>las entregas de Express</b>
                (donde se ve si cobró), <b>las guías</b> y <b>la libreta de clientes</b>, todo a la vez.<br>
                El teléfono da igual cómo lo escribas: <b>7723-2515</b> o <b>77232515</b>.
            </p>
        @else
            <p class="bc-ayuda">{{ $this->cuantos() }} resultado(s) para “{{ trim($q) }}”.</p>
        @endif
    </div>

    @if($this->buscando())

        {{-- ENTREGAS: es lo primero que se busca, porque ahí está el cobro --}}
        @php $entregas = $this->entregas(); @endphp
        <div class="bc-caja">
            <h3>💵 Entregas de Express</h3>
            <p class="desc">Lo que Express cobró al entregar. Acá se ve si el cliente pagó.</p>

            @forelse($entregas as $e)
                <div class="bc-fila">
                    <span class="bc-nom">{{ $e->nombre }}</span>
                    <span class="bc-guia">{{ $e->orden }}</span>
                    <span class="bc-dato">{{ $e->fecha?->format('d/m/Y') }} · {{ $e->zona }}</span>

                    @if($e->caso === 'devolucion')
                        <span class="bc-eti bc-eti-dev">Devuelto</span>
                    @endif
                    @if($e->duplicado)
                        <span class="bc-eti bc-eti-typ">TYP</span>
                    @endif

                    @if((float) $e->monto > 0)
                        <span class="bc-monto bc-cobro">Cobró ${{ number_format($e->monto, 2) }}</span>
                    @else
                        <span class="bc-monto bc-nocobro">Sin cobro</span>
                    @endif

                    @if($e->nota)
                        <span class="bc-nota">Nota de Express: {{ $e->nota }}</span>
                    @endif
                </div>
            @empty
                <p class="bc-vacio">Nada en las liquidaciones pegadas.</p>
            @endforelse
        </div>

        {{-- GUÍAS --}}
        @php $guias = $this->guias(); @endphp
        <div class="bc-caja">
            <h3>📦 Guías</h3>
            <p class="desc">Qué llevaba el paquete y su enlace de seguimiento.</p>

            @forelse($guias as $g)
                <div class="bc-fila">
                    <span class="bc-nom">{{ $g->nombre ?: 'Sin nombre' }}</span>
                    <span class="bc-guia">{{ $g->guia ?: 'sin guía aún' }}</span>
                    <span class="bc-dato">{{ $g->telefono ?: 'sin teléfono' }}</span>

                    @if($g->guia)
                        <a class="bc-dato" style="color:#4aa3df;font-weight:700"
                           href="{{ $g->enlaceRastreo() }}" target="_blank" rel="noopener">Ver rastreo ↗</a>
                    @endif

                    @if($g->contenido)
                        <span class="bc-nota">{{ $g->contenido }}</span>
                    @endif
                </div>
            @empty
                <p class="bc-vacio">Ninguna guía con ese dato.</p>
            @endforelse
        </div>

        {{-- CLIENTES --}}
        @php $clientes = $this->clientes(); @endphp
        <div class="bc-caja">
            <h3>👥 Libreta de clientes</h3>
            <p class="desc">Para no volver a escribir la dirección.</p>

            @forelse($clientes as $c)
                <div class="bc-fila">
                    <span class="bc-nom">{{ $c->nombre ?: 'Sin nombre' }}</span>
                    <span class="bc-dato">{{ $c->telefono }}</span>
                    <span class="bc-dato">{{ $c->municipio }}{{ $c->departamento ? ', ' . $c->departamento : '' }}</span>
                    @if($c->direccion)
                        <span class="bc-nota">{{ $c->direccion }}</span>
                    @endif
                </div>
            @empty
                <p class="bc-vacio">No está en la libreta.</p>
            @endforelse
        </div>

    @endif
</div>

</x-filament-panels::page>
