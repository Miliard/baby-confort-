<x-filament-panels::page>

<style>
    /* Las proporciones salen de medir Wasapi: lista de 400 px y separación de
       24 px. Con 320 el nombre y la vista previa quedaban apretados. */
    .wa{display:grid;grid-template-columns:400px 1fr;gap:20px;align-items:start;
        height:calc(100vh - 132px);min-height:460px}

    @media(max-width:1200px){ .wa{grid-template-columns:340px 1fr;gap:14px} }

    /* El título "WhatsApp" no dice nada que no se sepa por el menú de arriba,
       y se lleva casi 100 px de alto de conversación. */
    .fi-header{display:none !important}

    /* En el teléfono se ve una cosa a la vez, como WhatsApp: la lista, o el
       chat abierto ocupando toda la pantalla. Antes se apilaban las dos y
       había que bajar media pantalla para llegar al cuadro de escribir. */
    @media(max-width:900px){
        /* En el teléfono cada píxel de alto es contexto de la conversación.
           El título "WhatsApp" y los márgenes de Filament se comían un tercio
           de la pantalla para no decir nada que no se sepa. */
        .fi-main{padding-top:.35rem !important;padding-bottom:.35rem !important}
        .fi-main-ctn{padding-top:0 !important;padding-bottom:0 !important}
        .fi-page > *{gap:0 !important}

        /* Esa franja de arriba está casi vacía y mide 74 px. No se le puede
           meter el nombre del contacto (es de Filament, fuera de esta página),
           pero sí se puede achicar a la mitad. */
        .fi-topbar nav{min-height:44px !important;
                       padding-top:.2rem !important;padding-bottom:.2rem !important}
        .fi-topbar{box-shadow:none !important}

        /* La cabecera del chat en una sola línea: antes el nombre, el teléfono
           y la ventana de 24 horas se partían en tres renglones. */
        .wa-cab{flex-wrap:nowrap;overflow-x:auto;scrollbar-width:none}
        .wa-cab::-webkit-scrollbar{display:none}
        .wa-cab > *{flex:none}
        .wa-cab .wa-nombre-col{flex:1 1 auto;min-width:0}
        .wa-cab .wa-nombre-col > div{white-space:nowrap;overflow:hidden;text-overflow:ellipsis}

        /* --wa-alto lo mantiene el JS de abajo con el alto REAL que queda
           libre cuando el teclado está abierto. El calc es el respaldo para
           navegadores que no avisan del teclado. */
        .wa{grid-template-columns:1fr;gap:0;min-height:0;
            height:var(--wa-alto, calc(100dvh - 52px))}

        /* Con el teclado abierto, el chat se clava al pedazo de pantalla que
           queda libre. Es la única forma segura: Android no mueve la página,
           dibuja el teclado encima de ella. */
        .wa--anclada{position:fixed;left:0;right:0;z-index:40;
                     top:var(--wa-arriba, 0px);padding:0 6px}
        .wa--abierta .wa-izq{display:none}
        .wa:not(.wa--abierta) .wa-der{display:none}
        .wa-col{border-radius:11px}
        .wa-glo{max-width:88%}
        .wa-cab{padding:7px 9px;gap:6px}
        .wa-tabs{padding:0 4px}
        .wa-tab{padding:8px 7px;font-size:12px;gap:4px}
        .wa-volver{display:inline-flex !important}
        .wa-fila2{grid-template-columns:1fr}
        .wa-chat{padding:10px}
        .wa-abajo{padding:8px}
        .wa-escribir{padding:8px 10px}

        /* Etiquetas cortas para que las tres pestañas entren en un renglón */
        .wa-t-largo{display:none}
        .wa-t-corto{display:inline}

        /* Las etiquetas en un solo renglón que se desliza, en vez de en dos o
           tres filas comiéndose el alto del chat. */
        .wa-etq-fila{flex-wrap:nowrap;overflow-x:auto;padding:6px 9px;
                     scrollbar-width:none}
        .wa-etq-fila::-webkit-scrollbar{display:none}
        .wa-etq{flex:none}
        .wa-filtros{flex-wrap:nowrap;overflow-x:auto;scrollbar-width:none}
        .wa-filtros::-webkit-scrollbar{display:none}
        .wa-fil{flex:none}
    }

    /* En pantalla completa ya no hay barras del navegador que descontar. */
    :fullscreen .wa{height:calc(100dvh - 60px)}

    .wa-t-corto{display:none}

    /* Solo aparece en pantallas chicas: en la computadora estorba. */
    .wa-volver{display:none;border:none;background:rgba(120,140,170,.16);cursor:pointer;
               border-radius:9px;width:34px;height:34px;font-size:17px;align-items:center;
               justify-content:center;font-family:inherit;color:inherit;flex:none}
    .wa-full{font-size:15px}

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
    /* Tipografía un punto más grande, como la de Wasapi: base de 15 px.
       Buena parte de la sensación de "se ve chiquito" estaba acá. */
    .wa-nom{font-weight:700;font-size:15px;display:flex;gap:7px;align-items:center}
    .wa-prev{font-size:13.5px;color:#94a3b8;margin-top:3px;overflow:hidden;
             text-overflow:ellipsis;white-space:nowrap}
    .wa-hora{font-size:11.5px;color:#94a3b8;float:right;font-weight:400}
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
    .wa-glo{max-width:74%;padding:9px 13px;border-radius:13px;font-size:15px;line-height:1.45;
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
    /* Tamaño de miniatura, como WhatsApp. Antes ocupaban el 74% del ancho del
       chat y una sola foto te tapaba la conversación entera. Se toca y se abre
       grande en otra pestaña. */
    .wa-glo img{max-width:230px;max-height:290px;width:auto;height:auto;object-fit:cover;
                border-radius:9px;display:block;margin-bottom:5px;cursor:zoom-in}
    @media(max-width:900px){ .wa-glo img{max-width:190px;max-height:240px} }

    .wa-abajo{padding:11px;border-top:1px solid #e5e7eb;flex:none}
    html.dark .wa-abajo{border-color:rgba(255,255,255,.10)}
    .wa-escribir{width:100%;border:1px solid #d1d5db;border-radius:11px;padding:11px 13px;
                 font-size:15px;font-family:inherit;resize:vertical;background:transparent;color:inherit}
    .wa-btns{display:flex;gap:7px;flex-wrap:wrap;margin-top:8px;align-items:center}

    /* Los botones de respuesta al lado de Enviar. Se crean desde el admin. */
    .wa-chip{border:1px solid #d1d5db;background:#fff;border-radius:999px;
             padding:6px 13px;font-size:12.5px;font-weight:600;cursor:pointer;
             font-family:inherit;color:inherit;white-space:nowrap}
    .wa-chip:hover{border-color:#2e9e6b;color:#15603f}
    html.dark .wa-chip{background:#1c2739;border-color:rgba(255,255,255,.16)}
    html.dark .wa-chip:hover{color:#9fe1cb}

    /* Las tallas se distinguen de las respuestas: estas mandan de una, sin
       pasar por el cuadro de texto, así que conviene que no se confundan. */
    .wa-chip-talla{border-color:#4aa3df;color:#2b7fb8;font-weight:800}
    .wa-chip-talla:hover{background:#4aa3df;color:#fff;border-color:#4aa3df}
    html.dark .wa-chip-talla{color:#8ecbf0}
    .wa-chip-n{opacity:.6;font-weight:600;font-size:10.5px}

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
    .wa-origen-x{white-space:pre-wrap;font-size:13px;line-height:1.6;max-height:300px;
                 overflow-y:auto;font-family:ui-monospace,Menlo,Consolas,monospace}

    /* En pantalla ancha, la orden a la izquierda y la guía a la derecha, las
       dos a la vista: comparar es el trabajo, no un paso extra. */
    @media(min-width:1100px){
        .wa-comparar{display:grid;grid-template-columns:1fr 1fr;gap:18px;align-items:start}
        .wa-comparar .wa-origen{margin-bottom:0;position:sticky;top:0}
    }

    .wa-cola{margin-top:14px;font-size:12.5px;line-height:1.6;color:#94a3b8;
             border-top:1px solid rgba(120,140,170,.18);padding-top:12px}
    .wa-cola b{color:inherit;font-weight:800}

    .wa-previa{border:1px solid #2e9e6b;background:rgba(46,158,107,.07);border-radius:11px;
               padding:12px 14px;margin-top:14px}
    .wa-previa-t{font-size:12px;font-weight:800;color:#15603f;margin-bottom:9px}
    html.dark .wa-previa-t{color:#9fe1cb}
    .wa-pv{display:flex;gap:10px;padding:5px 0;font-size:13px;
           border-bottom:1px solid rgba(46,158,107,.16);align-items:baseline}
    .wa-pv:last-child{border-bottom:none}
    .wa-pv span{color:#94a3b8;min-width:104px;flex:none}
    .wa-pv b{word-break:break-word}
    .wa-mini{border:none;background:rgba(120,140,170,.18);border-radius:7px;padding:3px 9px;
             font-size:11px;cursor:pointer;font-family:inherit;color:inherit;font-weight:700}

    /* Sólido y no translúcido a propósito: el globo propio ya es verde claro,
       así que un verde transparente encima se volvía invisible. */
    .wa-procesar{border:none;background:#15603f;color:#fff;border-radius:9px;
                 padding:7px 12px;font-size:12px;font-weight:700;cursor:pointer;
                 font-family:inherit;margin-top:8px;display:block;width:100%;
                 box-shadow:0 1px 3px rgba(0,0,0,.18)}
    .wa-procesar:hover{background:#0f4730}

    /* ── Etiquetas ── */
    .wa-filtros{display:flex;gap:5px;flex-wrap:wrap;margin-top:9px}
    .wa-fil{border:1.5px solid var(--c);background:none;color:var(--c);border-radius:999px;
            padding:3px 9px;font-size:11px;font-weight:700;cursor:pointer;font-family:inherit;
            display:inline-flex;gap:5px;align-items:center}
    .wa-fil.on{background:var(--c);color:#fff}
    .wa-fil-n{opacity:.8;font-weight:600}

    .wa-marcas{display:flex;gap:4px;flex-wrap:wrap;margin-top:5px}
    .wa-marca{font-size:9.5px;font-weight:800;color:#fff;border-radius:5px;padding:1px 6px;
              letter-spacing:.02em}

    .wa-plegar{border:none;background:rgba(120,140,170,.16);cursor:pointer;border-radius:9px;
               width:32px;height:30px;font-size:15px;font-family:inherit;color:inherit;flex:none}
    .wa-plegar:hover{background:rgba(120,140,170,.30)}

    .wa-etq-btn{border:none;background:rgba(120,140,170,.16);cursor:pointer;border-radius:9px;
                height:30px;padding:0 9px;font-size:14px;font-family:inherit;color:inherit;
                display:inline-flex;align-items:center;gap:4px;flex:none}
    .wa-etq-btn.on{background:rgba(46,158,107,.24)}
    .wa-etq-n{font-size:10.5px;font-weight:800;background:#2e9e6b;color:#fff;
              border-radius:999px;padding:0 5px;line-height:15px}

    .wa-etq-fila{display:flex;gap:5px;flex-wrap:wrap;padding:8px 13px;flex:none;
                 border-bottom:1px solid #e5e7eb;background:#fff}
    html.dark .wa-etq-fila{background:#16202f;border-color:rgba(255,255,255,.10)}
    .wa-etq{border:1px dashed var(--c);background:none;color:var(--c);border-radius:999px;
            padding:3px 10px;font-size:11px;font-weight:700;cursor:pointer;font-family:inherit;
            opacity:.7}
    .wa-etq:hover{opacity:1}
    .wa-etq.on{background:var(--c);color:#fff;border-style:solid;opacity:1}

    /* ── La ventana del catálogo ── */
    .wa-modal-fondo{position:fixed;inset:0;background:rgba(10,16,26,.62);z-index:60;
                    display:grid;place-items:center;padding:18px}
    .wa-modal{background:#fff;border-radius:16px;width:100%;max-width:460px;
              max-height:86vh;display:flex;flex-direction:column;overflow:hidden;
              box-shadow:0 18px 50px rgba(0,0,0,.32)}
    html.dark .wa-modal{background:#16202f}

    .wa-modal-cab{display:flex;align-items:center;gap:10px;padding:14px 16px;
                  border-bottom:1px solid #e5e7eb;font-size:15px;flex:none}
    html.dark .wa-modal-cab{border-color:rgba(255,255,255,.10)}
    .wa-modal-cab b{flex:1}
    .wa-modal-x,.wa-modal-atras{border:none;background:rgba(120,140,170,.16);cursor:pointer;
                border-radius:9px;width:30px;height:30px;font-size:14px;font-family:inherit;
                color:inherit;flex:none}
    .wa-modal-cuerpo{padding:16px;overflow-y:auto;flex:1}
    .wa-modal-pie{padding:13px 16px;border-top:1px solid #e5e7eb;display:flex;gap:12px;
                  align-items:center;flex-wrap:wrap;flex:none}
    html.dark .wa-modal-pie{border-color:rgba(255,255,255,.10)}

    .wa-tallas{display:grid;grid-template-columns:repeat(auto-fill,minmax(108px,1fr));gap:9px}
    .wa-talla-btn{border:1.5px solid #d1d5db;background:none;border-radius:12px;padding:13px 9px;
                  cursor:pointer;font-family:inherit;color:inherit;display:flex;
                  flex-direction:column;gap:3px;align-items:center}
    .wa-talla-btn:hover{border-color:#2e9e6b;background:rgba(46,158,107,.08)}
    html.dark .wa-talla-btn{border-color:rgba(255,255,255,.16)}
    .wa-talla-n{font-size:19px;font-weight:800}
    .wa-talla-c{font-size:11px;color:#94a3b8}

    .wa-pres{width:100%;border:1.5px solid #e5e7eb;background:none;border-radius:11px;
             padding:11px 13px;margin-bottom:8px;cursor:pointer;font-family:inherit;
             color:inherit;display:flex;gap:11px;align-items:center;text-align:left}
    html.dark .wa-pres{border-color:rgba(255,255,255,.12)}
    .wa-pres.on{border-color:#2e9e6b;background:rgba(46,158,107,.09)}
    .wa-pres-check{width:22px;height:22px;border-radius:6px;border:1.5px solid #cbd5e1;
                   display:grid;place-items:center;font-size:13px;font-weight:800;flex:none}
    .wa-pres.on .wa-pres-check{background:#2e9e6b;border-color:#2e9e6b;color:#fff}
    .wa-pres-t{display:block;font-weight:700;font-size:14px}
    .wa-pres-p{display:block;font-size:12.5px;color:#94a3b8;margin-top:2px}

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

            @php $etqs = $this->etiquetas(); $cuentas = $this->cuentaEtiquetas(); @endphp
            @if($etqs->count())
                <div class="wa-filtros">
                    @foreach($etqs as $e)
                        <button type="button" wire:click="filtrarPor({{ $e->id }})"
                                wire:key="filtro-{{ $e->id }}"
                                class="wa-fil {{ $filtroEtiqueta === $e->id ? 'on' : '' }}"
                                style="--c:{{ $e->hex() }}">
                            {{ $e->nombre }}
                            @if(($cuentas[$e->id] ?? 0) > 0)
                                <span class="wa-fil-n">{{ $cuentas[$e->id] }}</span>
                            @endif
                        </button>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="wa-lista">
            @forelse($this->conversaciones() as $c)
                <button type="button" wire:click="abrir({{ $c->id }})" wire:key="conv-{{ $c->id }}"
                        class="wa-item {{ $abierta === $c->id ? 'on' : '' }}">
                    <span class="wa-hora">{{ $c->horaUltimo() }}</span>

                    <span class="wa-nom">
                        {{ $c->comoSeLlama() }}
                        @if($c->sin_leer > 0)<span class="wa-pin">{{ $c->sin_leer }}</span>@endif
                    </span>

                    <div class="wa-prev">{{ $c->ultimo_texto ?: '—' }}</div>

                    @if($c->etiquetas->count())
                        <div class="wa-marcas">
                            @foreach($c->etiquetas as $e)
                                <span class="wa-marca" style="background:{{ $e->hex() }}">{{ $e->nombre }}</span>
                            @endforeach
                        </div>
                    @endif

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

                @if($cabeceraAbierta)
                    <button type="button" class="wa-volver wa-full" onclick="waPantallaCompleta()"
                            title="Pantalla completa">⛶</button>
                @endif

                <div class="wa-nombre-col" style="flex:1;min-width:110px">
                    <div style="font-weight:800;font-size:15px">{{ $conv->comoSeLlama() }}</div>

                    @if($cabeceraAbierta)
                        <div style="font-size:12px;color:#94a3b8">
                            {{ $conv->telefono }}
                            @if($pestana === 'chat')
                                @if($conv->ventanaAbierta())
                                    · <span class="wa-eti wa-eti-ok">Puede responder · {{ $conv->ventanaLegible() }}</span>
                                @else
                                    · <span class="wa-eti wa-eti-mal">Ventana cerrada</span>
                                @endif
                            @endif
                        </div>
                    @endif
                </div>

                {{-- Los botones de atender solo hacen falta cuando se está
                     conversando. Armando el pedido estorban y se llevan
                     justo el espacio donde hay que comparar. --}}
                @if($pestana === 'chat' && $cabeceraAbierta)
                    @if($conv->agente_id && $conv->agente_id !== auth()->id())
                        <span class="wa-eti wa-eti-mal">La está atendiendo {{ $conv->agente?->name }}</span>
                    @endif

                    @if($conv->agente_id === auth()->id())
                        <x-filament::button size="xs" color="gray" wire:click="soltar">Soltar</x-filament::button>
                    @else
                        <x-filament::button size="xs" wire:click="tomar">Tomar</x-filament::button>
                    @endif

                    <x-filament::button size="xs" color="success" wire:click="plantillaOrden"
                        icon="heroicon-m-clipboard-document-list">
                        Orden de envío
                    </x-filament::button>

                    <x-filament::button size="xs" color="gray" wire:click="archivar"
                        wire:confirm="¿Archivar esta conversación?">Archivar</x-filament::button>
                @endif

                {{-- El botón de etiquetas vive en la cabecera y muestra cuántas
                     tiene puestas, así no hace falta abrir para saberlo. --}}
                @if($etqs->count() && $pestana !== 'pedido' && $cabeceraAbierta)
                    @php $puestas = $conv->etiquetas->count(); @endphp
                    <button type="button" class="wa-etq-btn {{ $etiquetasAbiertas ? 'on' : '' }}"
                            wire:click="verEtiquetas"
                            title="{{ $puestas ? $puestas . ' etiqueta(s) puesta(s)' : 'Poner una etiqueta' }}">
                        🏷️@if($puestas)<span class="wa-etq-n">{{ $puestas }}</span>@endif
                    </button>
                @endif

                {{-- Pliega toda la cabecera y le deja el alto al chat. --}}
                <button type="button" class="wa-plegar" wire:click="verCabecera"
                        title="{{ $cabeceraAbierta ? 'Esconder los datos y los botones' : 'Mostrar los datos y los botones' }}">
                    {{ $cabeceraAbierta ? '☰' : '⌄' }}
                </button>
            </div>

            {{-- La fila solo se dibuja si se pidió: son 45 px de conversación. --}}
            @if($etqs->count() && $pestana !== 'pedido' && $etiquetasAbiertas)
                <div class="wa-etq-fila">
                    @foreach($etqs as $e)
                        @php $puesta = $conv->etiquetas->contains($e->id); @endphp
                        <button type="button" wire:click="alternarEtiqueta({{ $e->id }})"
                                wire:key="etq-{{ $conv->id }}-{{ $e->id }}"
                                class="wa-etq {{ $puesta ? 'on' : '' }}"
                                style="--c:{{ $e->hex() }}"
                                title="{{ $puesta ? 'Quitar' : 'Poner' }} «{{ $e->nombre }}»">
                            {{ $puesta ? '✓' : '+' }} {{ $e->nombre }}
                        </button>
                    @endforeach
                </div>
            @endif

            {{-- ═══ Las tres pestañas ═══ --}}
            @php $resp = $this->respuestas(); @endphp
            <div class="wa-tabs">
                <button type="button" class="wa-tab {{ $pestana === 'chat' ? 'on' : '' }}"
                        wire:click="verPestana('chat')">💬<span class="wa-t-largo">Conversación</span><span class="wa-t-corto">Chat</span></button>

                <button type="button" class="wa-tab {{ $pestana === 'pedido' ? 'on' : '' }}"
                        wire:click="verPestana('pedido')">🛒<span class="wa-t-largo">Tomar pedido</span><span class="wa-t-corto">Pedido</span></button>

                <button type="button" class="wa-tab {{ $pestana === 'respuestas' ? 'on' : '' }}"
                        wire:click="verPestana('respuestas')">
                    ⚡<span class="wa-t-largo">Respuestas</span><span class="wa-t-corto">Rápidas</span>
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
                        @if($m->url())<a href="{{ $m->url() }}" target="_blank" rel="noopener"><img src="{{ $m->url() }}" alt="Imagen del cliente"></a>@elseif($m->tipo === 'image' && filled($m->media_id))<button type="button" class="wa-bajar" wire:click="bajarImagen({{ $m->id }})" wire:loading.attr="disabled">🖼️ Ver la imagen</button>@elseif($m->tipo !== 'text')<i style="opacity:.7">[{{ $m->tipo }}]</i>@endif

                        {{-- El texto va en su propio elemento y pegado a las llaves:
                             el globo respeta los saltos de línea, así que cualquier
                             espacio o sangría de la plantilla se dibujaría tal cual. --}}
                        @if(filled($m->texto))<div class="wa-txt">{{ $m->texto }}</div>@endif

                        <div class="wa-pie">
                            {{ $m->hora() }}
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

                        <button type="button" class="wa-chip" wire:click="mandarTallas">
                            📏 Tabla de tallas
                        </button>

                        <button type="button" class="wa-chip wa-chip-talla" wire:click="abrirCatalogo"
                                title="Elegir talla y productos para mandarle">
                            🛒 Catálogo
                        </button>

                        {{-- Los botones que Wil crea solos, desde el admin.
                             Van acá y no escondidos en la pestaña: la gracia es
                             que estén a un toque mientras se escribe. --}}
                        @foreach($resp as $r)
                            <button type="button" class="wa-chip" wire:click="usarRespuesta({{ $r->id }})"
                                    wire:key="chip-{{ $r->id }}" title="{{ \Illuminate\Support\Str::limit($r->texto, 120) }}">
                                {{ $r->titulo }}
                            </button>
                        @endforeach

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
            <div class="wa-panel {{ filled($pedOrigen) ? 'wa-comparar' : '' }}">
                @if(filled($pedOrigen))
                    <div class="wa-origen">
                        <div class="wa-origen-t">
                            <span>📦 La orden, tal como se la mandaste</span>
                            <button type="button" class="wa-mini" wire:click="limpiarPedido">Descartar</button>
                        </div>
                        <div class="wa-origen-x">{{ $pedOrigen }}</div>
                    </div>
                @endif

                <div>
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

                <div class="wa-campo">
                    <label class="wa-lab">Productos, tal como van en la guía</label>
                    <textarea class="wa-in" rows="3" wire:model.live="pedProductosTexto"
                              placeholder="Se llena solo al procesar una orden"></textarea>
                </div>

                <div class="wa-sep"></div>

                <div class="wa-campo">
                    <label class="wa-lab">A cobrar</label>
                    <input type="text" class="wa-in" wire:model.live="pedCobrarManual"
                           placeholder="Sale de la orden">
                </div>

                {{-- Así, exactamente, va a quedar la fila en el Excel. Es el
                     renglón que hay que comparar contra la orden de arriba. --}}
                <div class="wa-previa">
                    <div class="wa-previa-t">📄 Como va a quedar en la guía</div>

                    <div class="wa-pv"><span>Nombre</span><b>{{ $pedNombre ?: '—' }}</b></div>
                    <div class="wa-pv"><span>Teléfono</span><b>{{ $pedTelefono ?: '—' }}</b></div>
                    <div class="wa-pv"><span>Dirección</span><b>{{ $pedDireccion ?: '—' }}</b></div>
                    <div class="wa-pv"><span>Municipio</span><b>{{ $pedMunicipio ?: '—' }}</b></div>
                    <div class="wa-pv"><span>Departamento</span><b>{{ $pedDepartamento ?: '—' }}</b></div>
                    <div class="wa-pv"><span>Contenido</span><b>{{ $this->descripcionPedido() ?: '—' }}</b></div>
                    <div class="wa-pv"><span>Cobrar</span><b>${{ number_format($this->totalPedido(), 2) }}</b></div>
                </div>

                <div class="wa-btns" style="margin-top:16px">
                    <x-filament::button wire:click="guardarPedido" icon="heroicon-m-check-circle">
                        Guardar en la cola de guías
                    </x-filament::button>
                </div>

                {{-- Que se vea adónde va lo que se guarda, sin tener que
                     adivinar ni ir a comprobarlo a otra pantalla. --}}
                @php $enCola = $this->enCola(); @endphp
                <div class="wa-cola">
                    <b>¿Y después?</b>
                    Al guardar, esta guía se suma a la cola. Hoy hay
                    <b>{{ $enCola }} {{ $enCola == 1 ? 'guía esperando' : 'guías esperando' }}</b>.
                    Cuando las tengas todas, entrás a <b>Crear guías</b> y bajás el Excel
                    para subirlo al sistema.

                    @if($this->enlaceCola())
                        <div style="margin-top:9px">
                            <x-filament::button tag="a" size="xs" color="gray"
                                href="{{ $this->enlaceCola() }}" icon="heroicon-m-table-cells">
                                Ir a la cola y bajar el Excel
                            </x-filament::button>
                        </div>
                    @endif
                </div>
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

                @if(! (auth()->user()?->solo_chat ?? false))
                    <div style="margin-top:14px">
                        <x-filament::button tag="a" size="sm" color="gray" icon="heroicon-m-plus"
                            href="{{ \App\Filament\Resources\RespuestaRapidaResource::getUrl('create') }}">
                            Crear una respuesta
                        </x-filament::button>

                        @if($resp->count())
                            <x-filament::button tag="a" size="sm" color="gray" icon="heroicon-m-pencil"
                                href="{{ \App\Filament\Resources\RespuestaRapidaResource::getUrl('index') }}"
                                style="margin-left:7px">
                                Editar las que hay
                            </x-filament::button>
                        @endif
                    </div>
                @endif

                <div style="font-size:12px;color:#94a3b8;margin-top:14px;line-height:1.6">
                    Al tocar una, el texto se copia al cuadro de la conversación. Podés
                    cambiarlo antes de mandarlo. También aparecen como botones al lado
                    de "Enviar", para no tener que entrar acá.
                </div>
            </div>
            @endif
        @endif
    </div>
</div>

{{-- ═══ La ventana del catálogo ═══════════════════════════════════════════ --}}
@if($catalogoAbierto)
<div class="wa-modal-fondo" wire:click="cerrarCatalogo">
    <div class="wa-modal" wire:click.stop>

        <div class="wa-modal-cab">
            @if($tallaElegida)
                <button type="button" class="wa-modal-atras" wire:click="volverATallas">←</button>
                <b>Talla {{ $tallaElegida }}</b>
            @else
                <b>🛒 Catálogo</b>
            @endif
            <button type="button" class="wa-modal-x" wire:click="cerrarCatalogo">✕</button>
        </div>

        <div class="wa-modal-cuerpo">
            @if(! $tallaElegida)
                @php $tallas = $this->tallasDisponibles(); @endphp

                @if(count($tallas))
                    <div style="font-size:13px;color:#94a3b8;margin-bottom:12px">
                        Elegí la talla que te pidió. Solo aparecen las que tienen existencia.
                    </div>

                    <div class="wa-tallas">
                        @foreach($tallas as $talla => $cuantos)
                            <button type="button" class="wa-talla-btn"
                                    wire:key="mt-{{ $talla }}"
                                    wire:click="elegirTalla(@js($talla))">
                                <span class="wa-talla-n">{{ $talla }}</span>
                                <span class="wa-talla-c">{{ $cuantos }} {{ $cuantos == 1 ? 'producto' : 'productos' }}</span>
                            </button>
                        @endforeach
                    </div>
                @else
                    <div style="font-size:13.5px;color:#94a3b8;line-height:1.6">
                        No hay presentaciones con existencia. Revisá las cantidades en el admin.
                    </div>
                @endif

                <div class="wa-sep"></div>

                <button type="button" class="wa-chip" wire:click="mandarCatalogo"
                        style="width:100%;text-align:center">
                    📋 Mandar solo la lista de precios, sin fotos
                </button>
            @else
                @php $pres = $this->presentacionesDe($tallaElegida); @endphp

                <div style="font-size:13px;color:#94a3b8;margin-bottom:12px">
                    Vienen todos marcados. Desmarcá lo que no quieras mandar.
                </div>

                @foreach($pres as $s)
                    @php $marcado = in_array((string) $s->id, $elegidas, true); @endphp
                    <button type="button" class="wa-pres {{ $marcado ? 'on' : '' }}"
                            wire:key="pres-{{ $s->id }}"
                            wire:click="alternarProducto(@js((string) $s->id))">
                        <span class="wa-pres-check">{{ $marcado ? '✓' : '' }}</span>
                        <span style="flex:1">
                            <span class="wa-pres-t">{{ $s->product?->name }}</span>
                            <span class="wa-pres-p">
                                ${{ number_format((float) $s->price, 2) }}
                                @if((int) ($s->unidades ?? 0) > 0) · {{ (int) $s->unidades }} uds @endif
                                @if(! $this->tieneFoto($s)) · <i>sin foto</i> @endif
                            </span>
                        </span>
                    </button>
                @endforeach
            @endif
        </div>

        @if($tallaElegida)
            <div class="wa-modal-pie">
                <x-filament::button wire:click="enviarElegidas" icon="heroicon-m-paper-airplane">
                    Mandar {{ count($elegidas) }} {{ count($elegidas) == 1 ? 'producto' : 'productos' }}
                </x-filament::button>

                <span style="font-size:11.5px;color:#94a3b8">
                    Se manda una foto por producto, con su precio.
                </span>
            </div>
        @endif
    </div>
</div>
@endif

<script>
    // ── El teclado del teléfono ──────────────────────────────────────────────
    // Android avisa del teclado achicando la "ventana visual", no la página.
    // Acá se escucha ese aviso y se le da al chat el alto que realmente queda,
    // para que el cuadro de escribir nunca quede debajo del teclado.
    (function () {
        // Filament ya escribe su propia etiqueta de viewport. Agregar otra no
        // sirve: el navegador se queda con la primera. Hay que modificar esa.
        var meta = document.querySelector('meta[name="viewport"]');
        if (meta && meta.content.indexOf('interactive-widget') === -1) {
            meta.setAttribute(
                'content',
                'width=device-width, initial-scale=1, viewport-fit=cover, interactive-widget=resizes-content'
            );
        }

        var vv = window.visualViewport;
        if (!vv) return;

        var caja = document.querySelector('.wa');

        function ajustar() {
            if (!caja) caja = document.querySelector('.wa');

            if (window.innerWidth > 900) {
                document.documentElement.style.removeProperty('--wa-alto');
                if (caja) caja.classList.remove('wa--anclada');
                return;
            }

            // ¿Está el teclado abierto? Si la ventana visual es bastante más
            // chica que la de la página, sí.
            var teclado = (window.innerHeight - vv.height) > 120;

            if (teclado) {
                // Con el teclado abierto no alcanza con achicar el alto: hay
                // que clavar el chat al pedazo de pantalla que queda visible,
                // porque el navegador no mueve la página, solo dibuja encima.
                caja && caja.classList.add('wa--anclada');
                document.documentElement.style.setProperty('--wa-arriba', Math.round(vv.offsetTop) + 'px');
                document.documentElement.style.setProperty('--wa-alto', Math.round(vv.height) + 'px');
            } else {
                caja && caja.classList.remove('wa--anclada');
                document.documentElement.style.removeProperty('--wa-arriba');
                document.documentElement.style.setProperty(
                    '--wa-alto',
                    Math.max(Math.round(vv.height - 52), 240) + 'px'
                );
            }
        }

        vv.addEventListener('resize', ajustar);
        vv.addEventListener('scroll', ajustar);
        window.addEventListener('resize', ajustar);
        document.addEventListener('livewire:navigated', ajustar);

        // El chat se refresca solo cada 3 segundos y Livewire vuelve a dibujar
        // el HTML del servidor, que no sabe nada de esta clase. Hay que
        // volver a ponerla después de cada refresco.
        function engancharLivewire() {
            if (!window.Livewire || !window.Livewire.hook) return false;
            window.Livewire.hook('morph.updated', function () { ajustar(); });
            return true;
        }

        if (!engancharLivewire()) {
            document.addEventListener('livewire:init', engancharLivewire);
        }
        window.addEventListener('orientationchange', function () { setTimeout(ajustar, 250); });
        ajustar();

        // Al tocar el cuadro de texto, el teclado tarda un momento en abrirse:
        // recién después tiene sentido acomodar la vista.
        document.addEventListener('focusin', function (e) {
            if (!e.target.closest || !e.target.closest('.wa-abajo')) return;

            setTimeout(function () {
                ajustar();
                e.target.scrollIntoView({ block: 'nearest' });

                // Y que el último mensaje quede justo encima del teclado, no
                // perdido arriba: es el que uno está contestando.
                var chat = document.querySelector('.wa-chat');
                if (chat) chat.scrollTop = chat.scrollHeight;
            }, 320);
        });
    })();

    // Pantalla completa de verdad: esconde las barras del navegador.
    // No sobrevive a recargar la página — eso solo lo da instalar la app
    // desde "Agregar a pantalla de inicio".
    function waPantallaCompleta() {
        var d = document;

        if (d.fullscreenElement || d.webkitFullscreenElement) {
            (d.exitFullscreen || d.webkitExitFullscreen).call(d);
            return;
        }

        var e = d.documentElement;
        var pedir = e.requestFullscreen || e.webkitRequestFullscreen;

        if (!pedir) {
            alert('Este navegador no permite pantalla completa. '
                + 'Probá con el menú del navegador: "Agregar a pantalla de inicio".');
            return;
        }

        pedir.call(e).catch(function () {});
    }
</script>

</x-filament-panels::page>
