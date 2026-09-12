<x-filament-panels::page>

<style>
    .wa{display:grid;grid-template-columns:320px 1fr;gap:14px;align-items:start;
        height:calc(100vh - 210px);min-height:460px}

    /* En el teléfono se ve una cosa a la vez, como WhatsApp: la lista, o el
       chat abierto ocupando toda la pantalla. Antes se apilaban las dos y
       había que bajar media pantalla para llegar al cuadro de escribir. */
    @media(max-width:900px){
        .wa{grid-template-columns:1fr;gap:0;height:calc(100dvh - 130px);min-height:0}
        .wa--abierta .wa-izq{display:none}
        .wa:not(.wa--abierta) .wa-der{display:none}
        .wa-col{border-radius:11px}
        .wa-glo{max-width:88%}
        .wa-cab{padding:9px 11px;gap:7px}
        .wa-tabs{padding:0 6px}
        .wa-tab{padding:9px;font-size:12.5px}
        .wa-volver{display:inline-flex !important}
        .wa-fila2{grid-template-columns:1fr}
    }

    /* Solo aparece en pantallas chicas: en la computadora estorba. */
    .wa-volver{display:none;border:none;background:rgba(120,140,170,.16);cursor:pointer;
               border-radius:9px;width:34px;height:34px;font-size:17px;align-items:center;
               justify-content:center;font-family:inherit;color:inherit;flex:none}

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
    /* Nada de white-space aquí: iría contra toda la sangría de la plantilla y
       llenaría el globo de aire. Los saltos de línea los respeta .wa-txt. */
    .wa-glo{max-width:74%;padding:8px 12px;border-radius:13px;font-size:14px;line-height:1.45;
            word-wrap:break-word;text-align:left}
    .wa-txt{white-space:pre-wrap;overflow-wrap:anywhere}
    .wa-bajar{border:1px dashed currentColor;background:none;color:inherit;opacity:.75;
              border-radius:9px;padding:6px 11px;font-size:12.5px;cursor:pointer;
              font-family:inherit;margin-bottom:5px;display:block}
    .wa-bajar:hover{opacity:1}
    .wa-bajar:disabled{opacity:.4;cursor:wait}
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
    /* ── Pestañas de la derecha ── */
    .wa-tabs{display:flex;gap:4px;padding:0 12px;border-bottom:1px solid #e5e7eb;flex:none;
             background:#fff}
    html.dark .wa-tabs{background:#16202f;border-color:rgba(255,255,255,.10)}
    .wa-tab{border:none;background:none;cursor:pointer;font-family:inherit;font-size:13.5px;
            font-weight:600;color:#94a3b8;padding:10px 13px;border-bottom:2.5px solid transparent;
            display:flex;gap:6px;align-items:center}
    .wa-tab:hover{color:#64748b}
    .wa-tab.on{color:#2e9e6b;border-bottom-color:#2e9e6b}
    .wa-tab-pin{background:#2e9e6b;color:#fff;font-size:10px;font-weight:800;
                border-radius:999px;padding:1px 6px}

    /* ── Formulario de pedido ── */
    .wa-panel{flex:1;overflow-y:auto;padding:16px}
    .wa-campo{margin-bottom:11px}
    .wa-lab{font-size:11.5px;font-weight:700;color:#94a3b8;display:block;margin-bottom:4px;
            text-transform:uppercase;letter-spacing:.03em}
    .wa-in{width:100%;border:1px solid #d1d5db;border-radius:9px;padding:8px 11px;
           font-size:14px;font-family:inherit;background:transparent;color:inherit}
    html.dark .wa-in{border-color:rgba(255,255,255,.16)}
    .wa-fila2{display:grid;grid-template-columns:1fr 1fr;gap:10px}
    .wa-linea{display:grid;grid-template-columns:1fr 78px 34px;gap:8px;align-items:center;
              margin-bottom:8px}
    .wa-x{border:none;background:rgba(229,105,95,.14);color:#b91c1c;border-radius:8px;
          cursor:pointer;font-size:15px;font-weight:700;height:36px;font-family:inherit}
    .wa-x:hover{background:rgba(229,105,95,.26)}
    .wa-sep{border-top:1px solid rgba(120,140,170,.18);margin:14px 0}
    .wa-tot{display:flex;justify-content:space-between;font-size:13.5px;padding:4px 0}
    .wa-tot-grande{font-size:17px;font-weight:800;padding-top:8px}

    /* ── Respuestas rápidas ── */
    .wa-resp{width:100%;text-align:left;border:1px solid #e5e7eb;background:#fff;
             border-radius:11px;padding:11px 13px;cursor:pointer;margin-bottom:8px;
             font-family:inherit;display:block}
    html.dark .wa-resp{background:#1c2739;border-color:rgba(255,255,255,.10)}
    .wa-resp:hover{border-color:#2e9e6b}
    .wa-resp-t{font-weight:700;font-size:13.5px}
    .wa-resp-p{font-size:12px;color:#94a3b8;margin-top:3px;overflow:hidden;
               text-overflow:ellipsis;white-space:nowrap}

    /* La orden pegada arriba del formulario, para comparar sin cambiar de ventana */
    .wa-origen{border:1px solid #d4a017;background:rgba(234,179,8,.10);border-radius:11px;
               padding:11px 13px;margin-bottom:16px}
    .wa-origen-t{display:flex;justify-content:space-between;align-items:center;
                 font-size:12px;font-weight:700;color:#7a5600;margin-bottom:7px}
    html.dark .wa-origen-t{color:#f0d79a}
    .wa-origen-x{white-space:pre-wrap;font-size:12.5px;line-height:1.55;max-height:190px;
                 overflow-y:auto;font-family:ui-monospace,Menlo,Consolas,monospace}
    .wa-mini{border:none;background:rgba(120,140,170,.18);border-radius:7px;padding:3px 9px;
             font-size:11px;cursor:pointer;font-family:inherit;color:inherit;font-weight:700}

    /* Sólido y no translúcido a propósito: el globo propio ya es verde claro,
       así que un verde transparente encima se volvía invisible. */
    .wa-procesar{border:none;background:#15603f;color:#fff;border-radius:9px;
                 padding:7px 12px;font-size:12px;font-weight:700;cursor:pointer;
                 font-family:inherit;margin-top:8px;display:block;width:100%;
                 box-shadow:0 1px 3px rgba(0,0,0,.18)}
    .wa-procesar:hover{background:#0f4730}

    .wa-eti{font-size:11px;font-weight:700;border-radius:7px;padding:2px 8px}
    .wa-eti-ok{background:rgba(46,158,107,.16);color:#15603f}
    .wa-eti-mal{background:rgba(229,105,95,.16);color:#b91c1c}
    html.dark .wa-eti-ok{color:#9fe1cb} html.dark .wa-eti-mal{color:#f5c4b3}
</style>

@if(! $this->configurado())
    <div class="wa-aviso wa-aviso-mal">
        <b>Falta conectar el número.</b> El panel funciona y guarda todo, pero todavía no
        puede enviar ni recibir.
        @if(! (auth()->user()?->solo_chat ?? false))
            Se arregla en
            <a href="{{ \App\Filament\Pages\WhatsappConectar::getUrl() }}"
               style="text-decoration:underline;font-weight:700">Conectar WhatsApp</a>,
            en el menú de la izquierda. Es una sola vez.
        @else
            Avisale a Wil para que conecte el número; es cosa de un minuto.
        @endif
    </div>
@endif

<div class="wa {{ $abierta ? 'wa--abierta' : '' }}" wire:poll.3s>

    {{-- ═══ IZQUIERDA: las conversaciones ═══ --}}
    <div class="wa-col wa-izq">
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
    <div class="wa-col wa-der">
        @php $conv = $this->conversacion(); @endphp

        @if(! $conv)
            <div class="wa-vacio">Elegí una conversación de la izquierda para empezar.</div>
        @else
            <div class="wa-cab">
                <button type="button" class="wa-volver" wire:click="cerrarChat"
                        title="Volver a la lista">←</button>

                <div style="flex:1;min-width:120px">
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

            {{-- ═══ Las tres pestañas ═══ --}}
            @php $resp = $this->respuestas(); @endphp
            <div class="wa-tabs">
                <button type="button" class="wa-tab {{ $pestana === 'chat' ? 'on' : '' }}"
                        wire:click="verPestana('chat')">💬 Conversación</button>

                <button type="button" class="wa-tab {{ $pestana === 'pedido' ? 'on' : '' }}"
                        wire:click="verPestana('pedido')">🛒 Tomar pedido</button>

                <button type="button" class="wa-tab {{ $pestana === 'respuestas' ? 'on' : '' }}"
                        wire:click="verPestana('respuestas')">
                    ⚡ Respuestas
                    @if($resp->count())<span class="wa-tab-pin">{{ $resp->count() }}</span>@endif
                </button>
            </div>

            @if($pestana === 'chat')
            <div class="wa-chat">
                @forelse($this->mensajes() as $m)
                    @php
                        $clase = $m->esDelCliente() ? 'wa-suyo'
                               : ($m->estado === 'fallido' ? 'wa-mal'
                               : ($m->automatico ? 'wa-auto' : 'wa-mio'));
                    @endphp

                    <div class="wa-glo {{ $clase }}" wire:key="msg-{{ $m->id }}">
                        @if($m->url())<a href="{{ $m->url() }}" target="_blank" rel="noopener"><img src="{{ $m->url() }}" alt="Imagen del cliente"></a>@elseif($m->tipo === 'image')<button type="button" class="wa-bajar" wire:click="bajarImagen({{ $m->id }})" wire:loading.attr="disabled">🖼️ Ver la imagen</button>@elseif($m->tipo !== 'text')<i style="opacity:.7">[{{ $m->tipo }}]</i>@endif

                        {{-- El texto va en su propio elemento y pegado a las llaves:
                             el globo respeta los saltos de línea, así que cualquier
                             espacio o sangría de la plantilla se dibujaría tal cual. --}}
                        @if(filled($m->texto))<div class="wa-txt">{{ $m->texto }}</div>@endif

                        <div class="wa-pie">
                            {{ $m->created_at?->format('H:i') }}
                            @if(! $m->esDelCliente())
                                · {{ $m->firma() }} {{ $m->marcaEstado() }}
                            @endif
                        </div>

                        @if($m->estado === 'fallido' && $m->error)
                            <div class="wa-pie" style="opacity:1">⚠ {{ $m->error }}</div>
                        @endif

                        {{-- Si el mensaje parece una orden de envío, se puede
                             procesar sin salir de acá. --}}
                        @if(\Illuminate\Support\Str::contains($m->texto ?? '', ['Orden de Envío', 'Orden de Envio', 'Total a pagar']))
                            <button type="button" class="wa-procesar"
                                    wire:click="procesarOrden({{ $m->id }})">
                                📦 Procesar esta orden
                            </button>
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

            {{-- ═══ PESTAÑA: tomar el pedido sin salir del chat ═══ --}}
            @if($pestana === 'pedido')
            <div class="wa-panel">
                @if(filled($pedOrigen))
                    <div class="wa-origen">
                        <div class="wa-origen-t">
                            <span>📦 La orden, tal como la mandaste</span>
                            <button type="button" class="wa-mini" wire:click="limpiarPedido">Descartar</button>
                        </div>
                        <div class="wa-origen-x">{{ $pedOrigen }}</div>
                    </div>
                @endif

                @php $viejo = $this->clienteConocido(); @endphp

                @if($viejo)
                    <div class="wa-aviso wa-aviso-ok">
                        <b>Ya te compró antes.</b>
                        {{ $viejo['veces'] ?? 1 }} {{ ($viejo['veces'] ?? 1) == 1 ? 'vez' : 'veces' }}.
                        Los datos de abajo salen de la última entrega — revisá que sigan buenos.
                    </div>
                @endif

                <div class="wa-campo">
                    <label class="wa-lab">Nombre de quien recibe</label>
                    <input type="text" class="wa-in" wire:model="pedNombre">
                </div>

                <div class="wa-fila2">
                    <div class="wa-campo">
                        <label class="wa-lab">Teléfono</label>
                        <input type="text" class="wa-in" wire:model="pedTelefono">
                    </div>
                    <div class="wa-campo">
                        <label class="wa-lab">Municipio</label>
                        <input type="text" class="wa-in" wire:model="pedMunicipio">
                    </div>
                </div>

                <div class="wa-campo">
                    <label class="wa-lab">Dirección exacta</label>
                    <textarea class="wa-in" rows="2" wire:model="pedDireccion"></textarea>
                </div>

                <div class="wa-campo">
                    <label class="wa-lab">Departamento</label>
                    <input type="text" class="wa-in" wire:model="pedDepartamento">
                </div>

                <div class="wa-sep"></div>

                <div class="wa-campo">
                    <label class="wa-lab">Productos, tal como van en la guía</label>
                    <textarea class="wa-in" rows="2" wire:model="pedProductosTexto"
                              placeholder="Se llena solo al procesar una orden"></textarea>
                </div>

                <label class="wa-lab">O elegilos del catálogo</label>
                @php $opciones = $this->opcionesProductos(); @endphp

                @forelse($pedLineas as $i => $linea)
                    <div class="wa-linea" wire:key="lin-{{ $i }}">
                        <select class="wa-in" wire:model.live="pedLineas.{{ $i }}.size_id">
                            <option value="">Elegí el producto…</option>
                            @foreach($opciones as $id => $etiqueta)
                                <option value="{{ $id }}">{{ $etiqueta }}</option>
                            @endforeach
                        </select>

                        <input type="number" min="1" class="wa-in"
                               wire:model.live="pedLineas.{{ $i }}.cantidad">

                        <button type="button" class="wa-x" wire:click="quitarLinea({{ $i }})"
                                title="Quitar">×</button>
                    </div>
                @empty
                    <div style="font-size:13px;color:#94a3b8;margin-bottom:8px">
                        Todavía no agregaste nada.
                    </div>
                @endforelse

                <x-filament::button size="xs" color="gray" wire:click="agregarLinea"
                                    icon="heroicon-m-plus">
                    Agregar otro
                </x-filament::button>

                <div class="wa-campo" style="margin-top:14px">
                    <label class="wa-lab">Nota para la guía (opcional)</label>
                    <input type="text" class="wa-in" wire:model="pedNota"
                           placeholder="Ej: entregar por la tarde">
                </div>

                <div class="wa-sep"></div>

                @if($this->totalEsManual())
                    <div class="wa-campo">
                        <label class="wa-lab">A cobrar — el total que le pasaste al cliente</label>
                        <input type="text" class="wa-in" wire:model.live="pedCobrarManual">
                        <div style="font-size:11.5px;color:#94a3b8;margin-top:4px">
                            Salió de la orden. Se respeta tal cual: es el número que el cliente ya aceptó.
                        </div>
                    </div>
                @else
                    <div class="wa-tot">
                        <span>Productos</span>
                        <span>${{ number_format($this->subtotalPedido(), 2) }}</span>
                    </div>
                    <div class="wa-tot">
                        <span>Envío</span>
                        <span>{{ $this->envioPedido() > 0 ? '$' . number_format($this->envioPedido(), 2) : 'gratis' }}</span>
                    </div>
                @endif

                <div class="wa-tot wa-tot-grande">
                    <span>A cobrar</span>
                    <span>${{ number_format($this->totalPedido(), 2) }}</span>
                </div>

                <div class="wa-btns" style="margin-top:16px">
                    <x-filament::button wire:click="guardarPedido" icon="heroicon-m-check-circle">
                        Guardar en la cola de guías
                    </x-filament::button>

                    <x-filament::button color="gray" wire:click="pasarPedidoAlChat"
                                        icon="heroicon-m-chat-bubble-left-right">
                        Mandarle el resumen
                    </x-filament::button>
                </div>
            </div>
            @endif

            {{-- ═══ PESTAÑA: respuestas rápidas ═══ --}}
            @if($pestana === 'respuestas')
            <div class="wa-panel">
                @forelse($resp as $r)
                    <button type="button" class="wa-resp" wire:click="usarRespuesta({{ $r->id }})"
                            wire:key="resp-{{ $r->id }}">
                        <div class="wa-resp-t">{{ $r->titulo }}</div>
                        <div class="wa-resp-p">{{ \Illuminate\Support\Str::limit($r->texto, 90) }}</div>
                    </button>
                @empty
                    <div style="font-size:13.5px;color:#94a3b8;line-height:1.6;padding:10px 0">
                        Todavía no hay respuestas guardadas.<br><br>
                        Se crean en el admin, en <b>Respuestas rápidas</b>: le ponés un título corto
                        (el que se ve en el botón) y el texto que se manda. Sirven para lo que
                        escribís diez veces al día — precios, formas de pago, hasta dónde llega el envío.
                    </div>
                @endforelse

                <div style="font-size:12px;color:#94a3b8;margin-top:14px;line-height:1.6">
                    Al tocar una, el texto se copia al cuadro de la conversación. Podés
                    cambiarlo antes de mandarlo.
                </div>
            </div>
            @endif
        @endif
    </div>
</div>

</x-filament-panels::page>
