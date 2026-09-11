<x-filament-panels::page>

<style>
    .wa{display:grid;grid-template-columns:320px 1fr;gap:14px;align-items:start;
        height:calc(100vh - 210px);min-height:460px}
    @media(max-width:900px){ .wa{grid-template-columns:1fr;height:auto} }

    .wa-col{background:#fff;border:1px solid #e5e7eb;border-radius:14px;
            display:flex;flex-direction:column;overflow:hidden;height:100%}
    html.dark .wa-col{background:#16202f;border-color:rgba(255,255,255,.10)}

    .wa-top{padding:11px 13px;border-bottom:1px solid #e5e7eb;flex:none}
    html.dark .wa-top{border-color:rgba(255,255,255,.10)}

    .wa-lista{overflow-y:auto;flex:1}
    .wa-item{width:100%;text-align:left;padding:11px 13px;border:none;background:none;
             cursor:pointer;border-bottom:1px solid rgba(120,140,170,.14);display:block}
    .wa-item:hover{background:rgba(120,140,170,.10)}
    .wa-item.on{background:rgba(74,163,223,.14)}
    .wa-nom{font-weight:700;font-size:14px;display:flex;gap:7px;align-items:center}
    .wa-prev{font-size:12.5px;color:#94a3b8;margin-top:2px;overflow:hidden;
             text-overflow:ellipsis;white-space:nowrap}
    .wa-hora{font-size:11px;color:#94a3b8;float:right;font-weight:400}
    .wa-pin{background:#e5695f;color:#fff;font-size:10.5px;font-weight:800;
            border-radius:999px;padding:1px 7px;flex:none}
    .wa-quien{font-size:10.5px;color:#4aa3df;font-weight:700}

    .wa-cab{padding:11px 14px;border-bottom:1px solid #e5e7eb;flex:none;
            display:flex;flex-wrap:wrap;gap:10px;align-items:center}
    html.dark .wa-cab{border-color:rgba(255,255,255,.10)}

    .wa-chat{flex:1;overflow-y:auto;padding:14px;display:flex;flex-direction:column;gap:8px;
             background:#f6f8fa}
    html.dark .wa-chat{background:#0f1828}
    .wa-glo{max-width:74%;padding:8px 12px;border-radius:13px;font-size:14px;line-height:1.45;
            white-space:pre-wrap;word-wrap:break-word}
    .wa-mio{align-self:flex-end;background:#d6f2c8;color:#12300a;border-bottom-right-radius:4px}
    .wa-suyo{align-self:flex-start;background:#fff;color:#16202f;border:1px solid #e5e7eb;
             border-bottom-left-radius:4px}
    html.dark .wa-suyo{background:#1c2739;color:#eef2f7;border-color:rgba(255,255,255,.10)}
    .wa-auto{align-self:flex-end;background:#e6eefc;color:#1c3b63;border-bottom-right-radius:4px}
    .wa-mal{align-self:flex-end;background:#fdeaea;color:#8a1c1c;border:1px solid #e5695f}
    .wa-pie{font-size:10.5px;opacity:.65;margin-top:3px;text-align:right}
    .wa-glo img{max-width:100%;border-radius:9px;display:block;margin-bottom:5px}

    .wa-abajo{padding:11px;border-top:1px solid #e5e7eb;flex:none}
    html.dark .wa-abajo{border-color:rgba(255,255,255,.10)}
    .wa-escribir{width:100%;border:1px solid #d1d5db;border-radius:11px;padding:10px 12px;
                 font-size:14px;font-family:inherit;resize:vertical;background:transparent;color:inherit}
    .wa-btns{display:flex;gap:7px;flex-wrap:wrap;margin-top:8px;align-items:center}

    .wa-aviso{border-radius:11px;padding:10px 13px;font-size:13px;margin-bottom:12px;line-height:1.55}
    .wa-aviso-mal{background:rgba(229,105,95,.14);border:1px solid #e5695f;color:#b91c1c}
    .wa-aviso-ok{background:rgba(46,158,107,.13);border:1px solid #2e9e6b;color:#15603f}
    html.dark .wa-aviso-mal{color:#f5c4b3} html.dark .wa-aviso-ok{color:#9fe1cb}

    .wa-vacio{flex:1;display:grid;place-items:center;color:#94a3b8;font-size:14px;text-align:center;padding:30px}
    .wa-eti{font-size:11px;font-weight:700;border-radius:7px;padding:2px 8px}
    .wa-eti-ok{background:rgba(46,158,107,.16);color:#15603f}
    .wa-eti-mal{background:rgba(229,105,95,.16);color:#b91c1c}
    html.dark .wa-eti-ok{color:#9fe1cb} html.dark .wa-eti-mal{color:#f5c4b3}
</style>

@if(! $this->configurado())
    <div class="wa-aviso wa-aviso-mal">
        <b>Falta conectar con Meta.</b> El panel funciona y guarda todo, pero todavía no puede
        enviar ni recibir. Hay que cargar <code>WHATSAPP_TOKEN</code> y <code>WHATSAPP_PHONE_ID</code>
        en las variables de Railway.
    </div>
@endif

<div class="wa" wire:poll.3s>

    {{-- ═══ IZQUIERDA: las conversaciones ═══ --}}
    <div class="wa-col">
        <div class="wa-top">
            <x-filament::input.wrapper prefix-icon="heroicon-m-magnifying-glass">
                <x-filament::input type="text" wire:model.live.debounce.400ms="buscar"
                    placeholder="Buscar nombre o número" />
            </x-filament::input.wrapper>

            <label style="display:flex;gap:7px;align-items:center;font-size:12.5px;margin-top:8px;cursor:pointer">
                <input type="checkbox" wire:model.live="soloSinTomar">
                Solo las que nadie tomó
            </label>
        </div>

        <div class="wa-lista">
            @forelse($this->conversaciones() as $c)
                <button type="button" wire:click="abrir({{ $c->id }})" wire:key="conv-{{ $c->id }}"
                        class="wa-item {{ $abierta === $c->id ? 'on' : '' }}">
                    <span class="wa-hora">{{ $c->ultimo_mensaje_at?->format('H:i') }}</span>

                    <span class="wa-nom">
                        {{ $c->comoSeLlama() }}
                        @if($c->sin_leer > 0)<span class="wa-pin">{{ $c->sin_leer }}</span>@endif
                    </span>

                    <div class="wa-prev">{{ $c->ultimo_texto ?: '—' }}</div>

                    @if($c->agente)
                        <div class="wa-quien">● {{ $c->agente->name }}</div>
                    @endif
                </button>
            @empty
                <div style="padding:24px 14px;color:#94a3b8;font-size:13px;text-align:center">
                    No hay conversaciones todavía.<br>
                    Van a aparecer solas cuando alguien escriba.
                </div>
            @endforelse
        </div>
    </div>

    {{-- ═══ DERECHA: el chat ═══ --}}
    <div class="wa-col">
        @php $conv = $this->conversacion(); @endphp

        @if(! $conv)
            <div class="wa-vacio">Elegí una conversación de la izquierda para empezar.</div>
        @else
            <div class="wa-cab">
                <div style="flex:1;min-width:150px">
                    <div style="font-weight:800;font-size:15px">{{ $conv->comoSeLlama() }}</div>
                    <div style="font-size:12px;color:#94a3b8">
                        {{ $conv->telefono }}
                        @if($conv->ventanaAbierta())
                            · <span class="wa-eti wa-eti-ok">Puede responder · {{ $conv->ventanaLegible() }}</span>
                        @else
                            · <span class="wa-eti wa-eti-mal">Ventana cerrada</span>
                        @endif
                    </div>
                </div>

                @if($conv->agente_id && $conv->agente_id !== auth()->id())
                    <span class="wa-eti wa-eti-mal">La está atendiendo {{ $conv->agente?->name }}</span>
                @endif

                @if($conv->agente_id === auth()->id())
                    <x-filament::button size="xs" color="gray" wire:click="soltar">Soltar</x-filament::button>
                @else
                    <x-filament::button size="xs" wire:click="tomar">Tomar</x-filament::button>
                @endif

                <x-filament::button size="xs" color="success" tag="a" target="_blank"
                    href="{{ $this->enlaceProcesar() }}" icon="heroicon-m-clipboard-document-check">
                    Procesar orden
                </x-filament::button>

                <x-filament::button size="xs" color="gray" wire:click="archivar"
                    wire:confirm="¿Archivar esta conversación?">Archivar</x-filament::button>
            </div>

            <div class="wa-chat">
                @forelse($this->mensajes() as $m)
                    @php
                        $clase = $m->esDelCliente() ? 'wa-suyo'
                               : ($m->estado === 'fallido' ? 'wa-mal'
                               : ($m->automatico ? 'wa-auto' : 'wa-mio'));
                    @endphp

                    <div class="wa-glo {{ $clase }}" wire:key="msg-{{ $m->id }}">
                        @if($m->url())
                            <a href="{{ $m->url() }}" target="_blank" rel="noopener">
                                <img src="{{ $m->url() }}" alt="Imagen del cliente">
                            </a>
                        @elseif($m->tipo !== 'text' && ! $m->url())
                            <i style="opacity:.7">[{{ $m->tipo }} — no se pudo mostrar]</i>
                        @endif

                        {{ $m->texto }}

                        <div class="wa-pie">
                            {{ $m->created_at?->format('H:i') }}
                            @if(! $m->esDelCliente())
                                · {{ $m->firma() }} {{ $m->marcaEstado() }}
                            @endif
                        </div>

                        @if($m->estado === 'fallido' && $m->error)
                            <div class="wa-pie" style="opacity:1">⚠ {{ $m->error }}</div>
                        @endif
                    </div>
                @empty
                    <div class="wa-vacio">Todavía no hay mensajes en esta conversación.</div>
                @endforelse
            </div>

            <div class="wa-abajo">
                @if($conv->ventanaAbierta())
                    <textarea class="wa-escribir" rows="2" wire:model="texto"
                              placeholder="Escribí tu respuesta…"
                              wire:keydown.enter.prevent="enviar"></textarea>

                    <div class="wa-btns">
                        <x-filament::button size="sm" wire:click="enviar" icon="heroicon-m-paper-airplane">
                            Enviar
                        </x-filament::button>

                        <x-filament::button size="sm" color="gray" wire:click="mandarTallas">
                            📏 Tabla de tallas
                        </x-filament::button>

                        <span style="font-size:11.5px;color:#94a3b8">Enter envía · Shift+Enter salta línea</span>
                    </div>
                @else
                    <div class="wa-aviso wa-aviso-mal" style="margin:0">
                        <b>Pasaron más de 24 horas desde su último mensaje.</b>
                        WhatsApp no deja escribir libremente fuera de esa ventana: solo plantillas
                        aprobadas por Meta. Lo más simple es esperar a que el cliente escriba de
                        nuevo, o llamarlo.
                    </div>
                @endif
            </div>
        @endif
    </div>
</div>

</x-filament-panels::page>
