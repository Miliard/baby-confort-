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

        .wa-cab .wa-nombre-col{min-width:70px}

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
        .wa-tab{padding:0 7px;font-size:14px}
        .wa-volver{display:inline-flex !important}
        .wa-fila2{grid-template-columns:1fr}
        .wa-chat{padding:10px}
        .wa-abajo{padding:8px}
        .wa-escribir{padding:8px 10px}

        /* Etiquetas cortas para que las tres pestañas entren en un renglón */
        .wa-t-largo{display:none}
        .wa-t-corto{display:inline}

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

    /* Sin palomitas y con la barrita: el último mensaje es del cliente y está
       esperando respuesta. Es el estado que hay que poder ver de lejos. */
    .wa-prev-debo{color:inherit;font-weight:600;
                  border-left:3px solid #e5695f;padding-left:7px;margin-left:-1px}
    /* El renglón chico de abajo: solo el teléfono, y solo cuando arriba va un
       nombre que vos pusiste. El nombre del perfil de WhatsApp no se muestra. */
    .wa-apodo{font-size:11.5px;color:#94a3b8;opacity:.8;margin-top:1px;overflow:hidden;
              text-overflow:ellipsis;white-space:nowrap}

    /* El chinche de la lista: chiquito, solo para reconocer de un vistazo cuáles
       están clavadas. El que fija y suelta vive en la cabecera del chat. */
    .wa-clavo{font-size:11px;opacity:.85;margin-right:3px}
    /* Apagado mientras no está fijado, para que no parezca que ya lo está.
       El display va acá porque .wa-volver se esconde en la computadora, y este
       botón sirve en los dos lados. */
    .wa-fijar{display:inline-flex;filter:grayscale(1);opacity:.5}
    .wa-fijar.on{filter:none;opacity:1;background:rgba(46,158,107,.18)}

    .wa-alias-btn{border:none;background:none;cursor:pointer;font-family:inherit;
                  color:inherit;font-size:12px;padding:0;text-decoration:underline;
                  text-decoration-style:dotted;text-underline-offset:3px}
    .wa-alias-btn.on{color:inherit;font-weight:700;text-decoration:none}
    .wa-alias-btn:hover{color:#2e9e6b}

    .wa-alias-edit{display:flex;gap:6px;align-items:center;flex:1;min-width:180px}
    .wa-alias-edit .wa-in{flex:1;min-width:120px}
    .wa-hora{font-size:11.5px;color:#94a3b8;float:right;font-weight:400}
    .wa-pin{background:#e5695f;color:#fff;font-size:10.5px;font-weight:800;
            border-radius:999px;padding:1px 7px;flex:none}
    .wa-quien{font-size:10.5px;color:#4aa3df;font-weight:700}

    /* Una sola fila con todo. Si no entra, se desliza de derecha a izquierda
       en vez de partirse en varios renglones y comerse el chat. */
    .wa-cab{padding:9px 12px;border-bottom:1px solid #e5e7eb;flex:none;
            display:flex;flex-wrap:nowrap;gap:8px;align-items:center;
            overflow-x:auto;scrollbar-width:none}
    .wa-cab::-webkit-scrollbar{display:none}
    .wa-cab > *{flex:none}
    .wa-cab .wa-nombre-col{flex:1 1 auto;min-width:80px}
    .wa-cab .wa-nombre-col > div{white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
    html.dark .wa-cab{border-color:rgba(255,255,255,.10)}

    .wa-chat{flex:1;overflow-y:auto;padding:14px;display:flex;flex-direction:column;gap:8px;
             background:#f6f8fa}
    html.dark .wa-chat{background:#0f1828}
    /* Nada de white-space aquí: iría contra toda la sangría de la plantilla y
       llenaría el globo de aire. Los saltos de línea los respeta .wa-txt. */
    .wa-glo{max-width:74%;padding:9px 13px;border-radius:13px;font-size:15px;line-height:1.45;
            word-wrap:break-word;text-align:left}
    .wa-txt{white-space:pre-wrap;overflow-wrap:anywhere}

    /* Notas de voz: el reproductor arriba y abajo lo que dijo. */
    .wa-audio{width:100%;max-width:260px;height:38px;display:block;margin-bottom:6px}
    .wa-dicho{font-size:10.5px;font-weight:800;opacity:.6;margin-bottom:3px;
              text-transform:uppercase;letter-spacing:.03em}
    .wa-bajar{border:1px dashed currentColor;background:none;color:inherit;opacity:.75;
              border-radius:9px;padding:6px 11px;font-size:12.5px;cursor:pointer;
              font-family:inherit;margin-bottom:5px;display:block}
    .wa-bajar:hover{opacity:1}
    .wa-bajar:disabled{opacity:.4;cursor:wait}
    /* Verde apagado en vez del verde manzana de antes: sobre fondo oscuro
       aquel brillaba tanto que cansaba leerlo. */
    .wa-mio{align-self:flex-end;background:#dfeee0;color:#1b3a24;border-bottom-right-radius:4px}
    html.dark .wa-mio{background:#20443a;color:#e4f2ea}
    .wa-suyo{align-self:flex-start;background:#fff;color:#16202f;border:1px solid #e5e7eb;
             border-bottom-left-radius:4px}
    html.dark .wa-suyo{background:#1c2739;color:#eef2f7;border-color:rgba(255,255,255,.10)}
    .wa-auto{align-self:flex-end;background:#e6eefc;color:#1c3b63;border-bottom-right-radius:4px}
    html.dark .wa-auto{background:#26374f;color:#d6e4f7}
    .wa-mal{align-self:flex-end;background:#fdeaea;color:#8a1c1c;border:1px solid #e5695f}
    /* El pie se apaga con color y no con opacity: opacity en el padre apagaría
       también las palomitas, y un hijo no puede recuperarla. */
    .wa-pie{font-size:10.5px;margin-top:3px;text-align:right;color:rgba(0,0,0,.45)}
    html.dark .wa-pie{color:rgba(255,255,255,.5)}

    /* Palomitas más grandes y con color propio: gris salió, amarillo le llegó
       al teléfono, verde lo leyó. */
    .wa-check{font-size:15px;font-weight:800;letter-spacing:-2px;
              margin-left:3px;vertical-align:-1px}

    /* ── Responder a un mensaje puntual ── */
    .wa-resp-btn{border:none;background:none;cursor:pointer;font-family:inherit;color:inherit;
                 font-size:12px;padding:0 5px 0 0;opacity:.55}
    .wa-resp-btn:hover{opacity:1}

    /* El globo se corre con el dedo, así que no debe quedar seleccionado
       mientras se arrastra. */
    .wa-glo{touch-action:pan-y;-webkit-user-select:none;user-select:none}
    .wa-txt{-webkit-user-select:text;user-select:text}

    @media(max-width:900px){
        /* Más grande para el dedo, aunque el camino rápido sea deslizar. */
        .wa-resp-btn{font-size:16px;padding:2px 9px 2px 0;opacity:.7}
    }

    .wa-cita{border-left:3px solid currentColor;padding:4px 0 4px 8px;margin-bottom:6px;
             font-size:12.5px;line-height:1.4;opacity:.72}
    .wa-cita-q{font-weight:800;font-size:11px;margin-bottom:2px}

    .wa-citando{display:flex;gap:10px;align-items:center;margin-bottom:8px;
                background:rgba(120,140,170,.12);border-left:3px solid #2e9e6b;
                border-radius:0 9px 9px 0;padding:7px 10px}
    .wa-citando-x{font-size:12.5px;color:#94a3b8;overflow:hidden;
                  text-overflow:ellipsis;white-space:nowrap}
    /* Tamaño de miniatura, como WhatsApp. Antes ocupaban el 74% del ancho del
       chat y una sola foto te tapaba la conversación entera. Se toca y se abre
       grande en otra pestaña. */
    .wa-glo img{max-width:230px;max-height:290px;width:auto;height:auto;object-fit:cover;
                border-radius:9px;display:block;margin-bottom:5px;cursor:zoom-in}
    @media(max-width:900px){ .wa-glo img{max-width:190px;max-height:240px} }

    .wa-abajo{padding:11px;border-top:1px solid #e5e7eb;flex:none}
    html.dark .wa-abajo{border-color:rgba(255,255,255,.10)}
    /* Crece sola con lo que se escribe, hasta cierto punto: después hace
       scroll adentro en lugar de comerse el chat entero. */
    .wa-escribir{width:100%;border:1px solid #d1d5db;border-radius:11px;padding:11px 13px;
                 font-size:15px;font-family:inherit;background:transparent;color:inherit;
                 resize:none;min-height:46px;max-height:38vh;overflow-y:auto;line-height:1.45}
    @media(max-width:900px){ .wa-escribir{max-height:30vh} }
    .wa-btns{display:flex;gap:7px;flex-wrap:wrap;margin-top:8px;align-items:center}

    /* ── Con el teclado abierto en el teléfono ──────────────────────────────
       Acá el espacio es poquísimo: entre el teclado y un cuadro de texto lleno
       (una orden de envío ocupa seis renglones) no quedaba sitio para leer lo
       que escribió el cliente, que es justo lo que uno necesita ver mientras
       contesta. Así que mientras el teclado está arriba:

         · el cuadro de escribir no pasa de TRES RENGLONES, y de ahí en
           adelante hace scroll adentro;
         · los botones se van a un solo renglón que se desliza, en vez de
           apilarse en tres;
         · al chat se le garantiza un pedazo mínimo, que es lo que se estaba
           comiendo todo lo demás.

       El tope va en renglones y no en porcentaje de pantalla, que fue el
       primer intento: un tercio de la pantalla daba tres renglones en un
       teléfono chico y siete en uno grande. Tres renglones son tres renglones
       en cualquiera. Para cambiar cuántos, se toca --wa-renglones y nada más.

       Al cerrar el teclado vuelve todo como estaba. */
    html.wa--teclado .wa-escribir{--wa-renglones:3;
                                  max-height:calc(var(--wa-renglones) * 1.45em + 18px);
                                  min-height:40px;padding:8px 11px;font-size:15px}
    html.wa--teclado .wa-abajo{padding:7px 9px}
    html.wa--teclado .wa-btns{flex-wrap:nowrap;overflow-x:auto;margin-top:6px;
                              scrollbar-width:none;-ms-overflow-style:none;
                              padding-bottom:2px}
    html.wa--teclado .wa-btns::-webkit-scrollbar{display:none}
    html.wa--teclado .wa-btns > *{flex:none}
    html.wa--teclado .wa-chat{min-height:calc(var(--wa-alto, 100vh) * .26)}
    /* La cabecera del contacto también cede un poco: con el teclado abierto
       el número ya lo tenés a la vista, no hace falta tanto aire. */
    html.wa--teclado .wa-cab{padding:5px 9px}

    /* Los botones de respuesta al lado de Enviar. Se crean desde el admin. */
    .wa-chip{border:1px solid #d1d5db;background:#fff;border-radius:999px;
             padding:6px 13px;font-size:12.5px;font-weight:600;cursor:pointer;
             font-family:inherit;color:inherit;white-space:nowrap}
    .wa-chip:hover{border-color:#2e9e6b;color:#15603f}
    html.dark .wa-chip{background:#1c2739;border-color:rgba(255,255,255,.16)}
    html.dark .wa-chip:hover{color:#9fe1cb}

    /* Las tallas se distinguen de las respuestas: estas mandan de una, sin
       pasar por el cuadro de texto, así que conviene que no se confundan. */
    .wa-chip-ia{border-color:#b06cd6;color:#8b4fb8;font-weight:700}
    .wa-chip-ia:hover{background:#b06cd6;color:#fff;border-color:#b06cd6}
    html.dark .wa-chip-ia{color:#cfa6e8}
    .wa-chip-ia:disabled{opacity:.55;cursor:wait}

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
    /* Las pestañas ahora son botones dentro de la misma fila de la cabecera. */
    .wa-tab{border:1.5px solid transparent;background:rgba(120,140,170,.14);cursor:pointer;
            font-family:inherit;font-size:15px;border-radius:9px;height:30px;padding:0 9px;
            color:inherit;display:inline-flex;gap:4px;align-items:center;flex:none}
    .wa-tab:hover{background:rgba(120,140,170,.26)}
    .wa-tab.on{background:rgba(46,158,107,.20);border-color:#2e9e6b}
    .wa-tab-pin{background:#2e9e6b;color:#fff;font-size:10px;font-weight:800;
                border-radius:999px;padding:1px 6px}

    /* ── Formulario de pedido ── */
    .wa-panel{flex:1;overflow-y:auto;padding:16px}
    .wa-campo{margin-bottom:11px}
    .wa-lab{font-size:11.5px;font-weight:700;color:#94a3b8;display:block;margin-bottom:4px;
            text-transform:uppercase;letter-spacing:.03em}
    /* Ojo con esto: acá va "background-color" y no "background" a secas. El
       atajo reinicia también el fondo repetido, y Filament le pone a los
       desplegables una flechita como imagen de fondo: al reiniciarse, esa
       flechita se repetía en mosaico a lo ancho de todo el campo. */
    .wa-in{width:100%;border:1px solid #d1d5db;border-radius:9px;padding:8px 11px;
           font-size:14px;font-family:inherit;background-color:transparent;color:inherit}
    html.dark .wa-in{border-color:rgba(255,255,255,.16)}

    /* El desplegable: le quitamos la flecha de Filament y dibujamos una sola,
       nuestra, con el triangulito de abajo. */
    select.wa-in{appearance:none;-webkit-appearance:none;background-image:none;
                 padding-right:32px;cursor:pointer}
    .wa-sel{position:relative}
    .wa-sel::after{content:'';position:absolute;right:13px;top:50%;margin-top:-6px;
                   width:8px;height:8px;pointer-events:none;transform:rotate(45deg);
                   border-right:2px solid #94a3b8;border-bottom:2px solid #94a3b8}
    /* La lista que se abre la dibuja el sistema: sin esto sale en blanco
       encima del panel oscuro y no se lee. */
    select.wa-in option{background:#ffffff;color:#111827}
    html.dark select.wa-in{color-scheme:dark}
    html.dark select.wa-in option{background:#1f2937;color:#e5e7eb}
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

    /* El aviso de municipio/departamento, justo debajo de los dos campos. */
    .wa-zona{border-radius:9px;padding:8px 11px;font-size:12.5px;line-height:1.5;
             margin:-4px 0 12px;font-weight:600}
    .wa-zona-ok{background:rgba(46,158,107,.13);color:#15603f}
    .wa-zona-mal{background:rgba(229,105,95,.16);color:#b91c1c}
    .wa-zona-ojo{background:rgba(234,179,8,.16);color:#7a5600}
    html.dark .wa-zona-ok{color:#9fe1cb}
    html.dark .wa-zona-mal{color:#f5c4b3}
    html.dark .wa-zona-ojo{color:#f0d79a}

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
    /* Un solo renglón deslizable, en cualquier pantalla. */
    .wa-filtros{display:flex;gap:5px;flex-wrap:nowrap;margin-top:9px;
                overflow-x:auto;scrollbar-width:none;padding-bottom:2px}
    .wa-filtros::-webkit-scrollbar{display:none}
    .wa-filtros > *{flex:none}

    /* La manito de arrastrar solo cuando de verdad hay algo que arrastrar.
       La clase la pone el JavaScript midiendo si el contenido se desborda. */
    .wa-desliza{cursor:grab}
    .wa-desliza:active{cursor:grabbing}
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

    /* Las etiquetas viven en la misma fila deslizable de la cabecera. */
    .wa-etq{border:1px dashed var(--c);background:none;color:var(--c);border-radius:999px;
            padding:5px 11px;font-size:11.5px;font-weight:700;cursor:pointer;
            font-family:inherit;opacity:.75;white-space:nowrap;flex:none}
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
    .wa-foto{width:100%;border:1.5px solid #e5e7eb;background:none;border-radius:11px;
             padding:9px;margin-bottom:8px;cursor:pointer;font-family:inherit;color:inherit;
             display:flex;gap:11px;align-items:center;text-align:left}
    html.dark .wa-foto{border-color:rgba(255,255,255,.12)}
    .wa-foto:hover{border-color:#2e9e6b;background:rgba(46,158,107,.07)}
    .wa-foto.on{border-color:#2e9e6b;background:rgba(46,158,107,.11)}
    .wa-foto.on .wa-pres-check{background:#2e9e6b;border-color:#2e9e6b;color:#fff}
    .wa-foto img{width:56px;height:56px;object-fit:cover;border-radius:8px;flex:none}
    .wa-foto b{display:block;font-size:14px}
    .wa-foto-p{display:block;font-size:12px;color:#94a3b8;margin-top:2px}

    .wa-uso{display:flex;gap:9px;align-items:center;font-size:12.5px;cursor:pointer;
            background:rgba(120,140,170,.10);border-radius:9px;padding:9px 11px}
    .wa-uso-no{opacity:.5;cursor:not-allowed}

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

            {{-- Solo aparece si todavía no se dieron los permisos. Una vez
                 activados, se esconde solo. --}}
            <button type="button" id="wa-permiso" onclick="waPedirPermiso()"
                    class="wa-chip" style="display:none;margin-top:8px;width:100%">
                🔔 Activar avisos con sonido
            </button>

            {{-- Todos los filtros en un solo renglón que se desliza de derecha
                 a izquierda, en vez de apilarse hacia abajo. Los sin leer van
                 primero: es lo que uno busca al abrir el panel. --}}
            @php
                $etqs = $this->etiquetas();
                $cuentas = $this->cuentaEtiquetas();
                $sinLeer = $this->cuantasSinLeer();
                $sinResponder = $this->cuantasSinResponder();
            @endphp

            {{-- El contador que vigila el JavaScript para avisar. Va acá
                 adentro porque esta parte se refresca sola cada 3 segundos. --}}
            <span id="wa-sinleer" data-n="{{ $sinLeer }}" style="display:none"></span>

            <div class="wa-filtros">
                <button type="button" wire:click="alternarSinLeer"
                        class="wa-fil {{ $soloSinLeer ? 'on' : '' }}" style="--c:#e5695f">
                    Sin leer
                    @if($sinLeer > 0)<span class="wa-fil-n">{{ $sinLeer }}</span>@endif
                </button>

                <button type="button" wire:click="alternarSinResponder"
                        class="wa-fil {{ $soloSinResponder ? 'on' : '' }}" style="--c:#e5a23f"
                        title="Las que esperan que vos contestes">
                    Sin responder
                    @if($sinResponder > 0)<span class="wa-fil-n">{{ $sinResponder }}</span>@endif
                </button>

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
        </div>

        <div class="wa-lista">
            @forelse($this->conversaciones() as $c)
                <button type="button" wire:click="abrir({{ $c->id }})" wire:key="conv-{{ $c->id }}"
                        class="wa-item {{ $abierta === $c->id ? 'on' : '' }}">
                    <span class="wa-hora">{{ $c->horaUltimo() }}</span>

                    <span class="wa-nom">
                        @if($c->fijada())<span class="wa-clavo" title="Fijado">📌</span>@endif
                        {{ $c->titulo() }}
                        @if($c->sin_leer > 0)<span class="wa-pin">{{ $c->sin_leer }}</span>@endif
                    </span>

                    @if($c->subtitulo())
                        <div class="wa-apodo">{{ $c->subtitulo() }}</div>
                    @endif

                    {{-- Las palomitas delante de la vista previa cuentan la
                         historia de un vistazo: si están, ya contestaste. --}}
                    <div class="wa-prev {{ $c->sinResponder() ? 'wa-prev-debo' : '' }}">
                        @if($c->marcaUltimo())
                            <span class="wa-check" style="color:{{ $c->colorUltimo() }}">{{ $c->marcaUltimo() }}</span>
                        @endif
                        {{ $c->ultimo_texto ?: '—' }}
                    </div>

                    @if($c->etiquetas->count())
                        <div class="wa-marcas">
                            @foreach($c->etiquetas as $e)
                                <span class="wa-marca" style="background:{{ $e->hex() }}">{{ $e->nombre }}</span>
                            @endforeach
                        </div>
                    @endif

                    @if($c->agente)
                        <div class="wa-quien" style="color:{{ $c->agente->colorAgente() }}">
                            ● {{ $c->agente->name }}
                        </div>
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
            @php $resp = $this->respuestas(); @endphp

            {{-- Una sola fila con todo, que se desliza de derecha a izquierda.
                 Antes eran tres renglones: datos, botones y pestañas. --}}
            <div class="wa-cab">
                <button type="button" class="wa-volver" wire:click="cerrarChat"
                        title="Volver a la lista">←</button>

                {{-- Sin botón para entrar al pedido: se llega procesando una
                     orden del chat, que es el único camino que tiene sentido.
                     El de volver solo aparece cuando estás adentro. --}}
                @if($pestana === 'pedido')
                    <button type="button" class="wa-tab on" wire:click="verPestana('chat')"
                            title="Volver a la conversación">💬</button>
                @endif

                <button type="button" class="wa-volver wa-full" onclick="waPantallaCompleta()"
                        title="Pantalla completa">⛶</button>

                {{-- Fijar va acá y no en la lista: cada fila de la lista ya es
                     un botón entero, y un botón adentro de otro no se puede.
                     Acá además se fija el chat que estás leyendo, que es el
                     momento en que uno se da cuenta de que lo quiere a mano. --}}
                <button type="button" class="wa-volver wa-fijar {{ $conv->fijada() ? 'on' : '' }}"
                        wire:click="fijar({{ $conv->id }})"
                        title="{{ $conv->fijada() ? 'Soltar este chat de arriba' : 'Fijar este chat arriba de la lista' }}">📌</button>

                @if($editandoAlias)
                    <div class="wa-alias-edit">
                        <input type="text" class="wa-in" wire:model="aliasTexto"
                               placeholder="Ej: Marta San Miguel" autofocus
                               wire:keydown.enter.prevent="guardarAlias">
                        <x-filament::button size="xs" wire:click="guardarAlias">Guardar</x-filament::button>
                        <button type="button" class="wa-mini" wire:click="cancelarAlias">✕</button>
                    </div>
                @else
                <div class="wa-nombre-col" style="flex:1;min-width:110px">
                    <div style="font-weight:800;font-size:15px">{{ $conv->titulo() }}</div>

                    <div style="font-size:12px;color:#94a3b8">
                        <button type="button" class="wa-alias-btn {{ $conv->nombrePropio() ? 'on' : '' }}"
                                wire:click="editarAlias"
                                title="{{ $conv->nombrePropio() ? 'Cambiar el nombre' : 'Ponerle un nombre a este contacto' }}">
                            {{ $conv->subtitulo() ?: '✏️ Ponerle nombre' }}
                        </button>
                        @if($pestana === 'chat')
                            @if($conv->ventanaAbierta())
                                · <span class="wa-eti wa-eti-ok">Puede responder · {{ $conv->ventanaLegible() }}</span>
                            @else
                                · <span class="wa-eti wa-eti-mal">Ventana cerrada</span>
                            @endif
                        @endif
                    </div>
                </div>
                @endif

                {{-- El chat se toma solo al contestar, así que no hay botones
                     para eso. Pero el aviso de que ya lo está atendiendo otro
                     se ve siempre: enterarse tarde es justo lo que hay que
                     evitar cuando son tres personas en el mismo número. --}}
                @if($conv->agente_id && $conv->agente_id !== auth()->id())
                    <span class="wa-eti wa-eti-mal">
                        Atiende {{ $conv->agente?->name }}
                    </span>
                @endif

                @if($pestana === 'chat')
                    <x-filament::button size="xs" color="success" wire:click="plantillaOrden"
                        icon="heroicon-m-clipboard-document-list">
                        Orden de envío
                    </x-filament::button>
                @endif

                {{-- Las etiquetas van acá mismo, en la fila que ya se desliza.
                     Antes estaban detrás de un botón: eran dos toques para
                     algo que se hace veinte veces al día. --}}
                @if($pestana !== 'pedido')
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
                @endif
            </div>

            @if($pestana === 'chat')
            <div class="wa-chat" data-conv="{{ $abierta }}">
                @forelse($this->mensajes() as $m)
                    @php
                        $clase = $m->esDelCliente() ? 'wa-suyo'
                               : ($m->estado === 'fallido' ? 'wa-mal'
                               : ($m->automatico ? 'wa-auto' : 'wa-mio'));
                    @endphp

                    <div class="wa-glo {{ $clase }}" wire:key="msg-{{ $m->id }}">
                        @php $cita = $m->citado(); @endphp
                        @if($cita)
                            <div class="wa-cita">
                                <div class="wa-cita-q">{{ $cita->esDelCliente() ? $conv->titulo() : 'Vos' }}</div>
                                {{ $cita->resumen() }}
                            </div>
                        @endif

                        @if($m->esAudio())
                            @if($m->url())
                                <audio class="wa-audio" controls preload="none" src="{{ $m->url() }}"></audio>
                            @endif
                            @if(filled($m->texto))
                                <div class="wa-dicho">🎙️ Lo que dijo</div>
                            @else
                                <i style="opacity:.7">🎙️ Nota de voz — no se pudo pasar a texto</i>
                            @endif
                        @elseif($m->url())<a href="{{ $m->url() }}" target="_blank" rel="noopener"><img src="{{ $m->url() }}" alt="Imagen del cliente"></a>@elseif($m->tipo === 'image' && filled($m->media_id))<button type="button" class="wa-bajar" wire:click="bajarImagen({{ $m->id }})" wire:loading.attr="disabled">🖼️ Ver la imagen</button>@elseif($m->tipo !== 'text')<i style="opacity:.7">[{{ $m->tipo }}]</i>@endif

                        {{-- El texto va en su propio elemento y pegado a las llaves:
                             el globo respeta los saltos de línea, así que cualquier
                             espacio o sangría de la plantilla se dibujaría tal cual. --}}
                        @if(filled($m->texto))<div class="wa-txt">{{ $m->texto }}</div>@endif

                        <div class="wa-pie">
                            @if(filled($m->wa_message_id))
                                <button type="button" class="wa-resp-btn"
                                        wire:click="responderA({{ $m->id }})"
                                        title="Responder a este mensaje">↩</button>
                            @endif
                            {{ $m->hora() }}
                            @if(! $m->esDelCliente())
                                · <b style="color:{{ $m->colorFirma() }}">{{ $m->firma() }}</b>
                                <span class="wa-check" style="color:{{ $m->colorEstado() }}"
                                      title="{{ $m->queSignifica() }}">{{ $m->marcaEstado() }}</span>
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
                    @php $citando = $this->mensajeCitado(); @endphp
                    @if($citando)
                        <div class="wa-citando">
                            <div style="flex:1;min-width:0">
                                <div class="wa-cita-q">
                                    Respondiendo a {{ $citando->esDelCliente() ? $conv->titulo() : 'vos mismo' }}
                                </div>
                                <div class="wa-citando-x">{{ $citando->resumen(120) }}</div>
                            </div>
                            <button type="button" class="wa-mini" wire:click="cancelarRespuesta">✕</button>
                        </div>
                    @endif

                    {{-- El atajo mira si hay Shift: sin él manda, con él deja
                         saltar de línea. Antes se bloqueaba cualquier Enter y
                         no se podía escribir un mensaje de dos renglones. --}}
                    <textarea class="wa-escribir" rows="2" wire:model="texto"
                              placeholder="Escribí tu respuesta…" spellcheck="true" lang="es"
                              x-on:keydown.enter="
                                  if (! $event.shiftKey && window.innerWidth > 900) {
                                      $event.preventDefault();
                                      $wire.enviar();
                                  }
                              "></textarea>

                    <div class="wa-btns">
                        <x-filament::button size="sm" wire:click="enviar" icon="heroicon-m-paper-airplane">
                            Enviar
                        </x-filament::button>

                        @if($this->puedeMejorar())
                            <button type="button" class="wa-chip wa-chip-ia" wire:click="mejorar"
                                    wire:loading.attr="disabled" wire:target="mejorar"
                                    title="Corrige ortografía, tildes y redacción sin inventar nada">
                                <span wire:loading.remove wire:target="mejorar">✨ Mejorar</span>
                                <span wire:loading wire:target="mejorar">Corrigiendo…</span>
                            </button>
                        @endif

                        @if($textoAntes !== '')
                            <button type="button" class="wa-chip" wire:click="deshacerMejora"
                                    title="Volver a como lo escribiste vos">↶ Deshacer</button>
                        @endif

                        <button type="button" class="wa-chip" wire:click="mandarTallas">
                            📏 Tabla de tallas
                        </button>

                        <button type="button" class="wa-chip wa-chip-talla" wire:click="abrirCatalogo"
                                title="Elegir talla y productos para mandarle">
                            🛒 Catálogo
                        </button>

                        <button type="button" class="wa-chip" wire:click="abrirFotos"
                                title="Mandar una foto guardada">
                            📷 Fotos
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

                        {{-- En el teléfono el Enter salta línea siempre, así que
                             esta ayuda solo aplica en la computadora. --}}
                        <span class="wa-t-largo" style="font-size:11.5px;color:#94a3b8">
                            Enter envía · Shift+Enter salta línea
                        </span>
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
                        <input type="text" class="wa-in" wire:model.live.debounce.500ms="pedMunicipio"
                               list="wa-municipios">
                    </div>
                </div>

                <datalist id="wa-municipios">
                    @foreach(array_keys(config('municipios', [])) as $m)
                        <option value="{{ $m }}"></option>
                    @endforeach
                </datalist>

                <div class="wa-campo">
                    <label class="wa-lab">Dirección exacta</label>
                    <textarea class="wa-in" rows="2" wire:model="pedDireccion"></textarea>
                </div>

                <div class="wa-campo">
                    <label class="wa-lab">Departamento</label>
                    <div class="wa-sel">
                        <select class="wa-in" wire:model.live="pedDepartamento">
                            <option value="">Elegí el departamento…</option>
                            @foreach($this->departamentos() as $d)
                                <option value="{{ $d }}">{{ $d }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                @php $zona = $this->revisionZona(); @endphp
                @if($zona['estado'] === 'error')
                    <div class="wa-zona wa-zona-mal">
                        ⚠ {{ $zona['mensaje'] }}
                    </div>
                @elseif($zona['estado'] === 'ambiguo')
                    <div class="wa-zona wa-zona-ojo">
                        {{ $zona['mensaje'] }}
                    </div>
                @elseif($zona['estado'] === 'desconocido')
                    <div class="wa-zona wa-zona-ojo">
                        {{ $zona['mensaje'] }}
                    </div>
                @elseif($zona['estado'] === 'ok')
                    <div class="wa-zona wa-zona-ok">
                        ✓ {{ $pedMunicipio }} pertenece a {{ $pedDepartamento }}
                    </div>
                @endif

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

        @endif
    </div>
</div>

{{-- ═══ La ventana de las fotos guardadas ═════════════════════════════════ --}}
@if($fotosAbiertas)
<div class="wa-modal-fondo" wire:click="cerrarFotos">
    <div class="wa-modal" wire:click.stop>

        @php $fotos = $this->fotosGuardadas(); @endphp

        <div class="wa-modal-cab">
            <b>📷 Fotos para mandar</b>
            @if($fotos->count() > 1)
                <button type="button" class="wa-mini" wire:click="marcarTodasLasFotos">
                    {{ count($fotosElegidas) === $fotos->count() ? 'Ninguna' : 'Todas' }}
                </button>
            @endif
            <button type="button" class="wa-modal-x" wire:click="cerrarFotos">✕</button>
        </div>

        <div class="wa-modal-cuerpo">
            @if($fotos->count())
                <div style="font-size:13px;color:#94a3b8;margin-bottom:12px">
                    Tocá las que querés mandar. Se van todas juntas, una detrás de otra.
                </div>
            @endif

            @forelse($fotos as $f)
                @php $marcada = in_array((string) $f->id, $fotosElegidas, true); @endphp
                <button type="button" class="wa-foto {{ $marcada ? 'on' : '' }}"
                        wire:key="foto-{{ $f->id }}"
                        wire:click="alternarFoto(@js((string) $f->id))">
                    <span class="wa-pres-check">{{ $marcada ? '✓' : '' }}</span>
                    <img src="{{ $f->url() }}" alt="{{ $f->titulo }}">
                    <span style="flex:1;min-width:0">
                        <b>{{ $f->titulo }}</b>
                        @if(filled($f->pie))
                            <span class="wa-foto-p">{{ \Illuminate\Support\Str::limit($f->pie, 70) }}</span>
                        @endif
                    </span>
                </button>
            @empty
                <div style="font-size:13.5px;color:#94a3b8;line-height:1.6;padding:10px 0">
                    Todavía no hay fotos guardadas.<br><br>
                    Se suben en el menú, en <b>Fotos para mandar</b>. Son las que mandás
                    seguido y no son del catálogo: el producto ya puesto, el empaque
                    abierto, lo que te pidan ver antes de comprar.
                </div>
            @endforelse

            @if(! (auth()->user()?->solo_chat ?? false))
                <div style="margin-top:14px">
                    <x-filament::button tag="a" size="sm" color="gray" icon="heroicon-m-plus"
                        href="{{ \App\Filament\Resources\WaFotoResource::getUrl('create') }}">
                        Subir fotos
                    </x-filament::button>
                </div>
            @endif
        </div>

        @if(count($fotosElegidas))
            <div class="wa-modal-pie">
                <x-filament::button wire:click="mandarFotosElegidas" icon="heroicon-m-paper-airplane">
                    Mandar {{ count($fotosElegidas) }}
                    {{ count($fotosElegidas) == 1 ? 'foto' : 'fotos' }}
                </x-filament::button>

                <span style="font-size:11.5px;color:#94a3b8">
                    Salen seguidas, en el orden de la lista.
                </span>
            </div>
        @endif
    </div>
</div>
@endif

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
            @php $usos = $this->cuantasFotosUso(); @endphp

            <div class="wa-modal-pie" style="flex-direction:column;align-items:stretch;gap:9px">
                <label class="wa-uso {{ $usos ? '' : 'wa-uso-no' }}">
                    <input type="checkbox" wire:model.live="conFotosUso" @disabled(! $usos)>
                    <span>
                        <b>También las fotos del producto puesto</b>
                        @if($usos)
                            <span style="color:#94a3b8">· {{ $usos }} {{ $usos == 1 ? 'foto' : 'fotos' }} más</span>
                        @else
                            <span style="color:#94a3b8">· no hay cargadas todavía</span>
                        @endif
                    </span>
                </label>

                <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap">
                    <x-filament::button wire:click="enviarElegidas" icon="heroicon-m-paper-airplane">
                        Mandar {{ count($elegidas) + ($conFotosUso ? $usos : 0) }}
                        {{ (count($elegidas) + ($conFotosUso ? $usos : 0)) == 1 ? 'foto' : 'fotos' }}
                    </x-filament::button>

                    <span style="font-size:11.5px;color:#94a3b8">
                        Una foto por producto, con su precio y su enlace.
                    </span>
                </div>
            </div>
        @endif
    </div>
</div>
@endif

<script>
    // ── Deslizar un mensaje para responderlo ─────────────────────────────────
    // Como en WhatsApp: se arrastra el globo hacia la derecha y suelta. Mucho
    // más cómodo que buscar la flechita, sobre todo en el teléfono.
    (function () {
        var globo = null;      // el que se está arrastrando
        var desdeX = 0, desdeY = 0;
        var corrido = 0;
        var horizontal = null; // todavía no se sabe si es gesto lateral

        var UMBRAL = 55;       // cuánto hay que correrlo para que cuente

        function pista(g, x) {
            g.style.transform = x ? 'translateX(' + x + 'px)' : '';
            g.style.transition = x ? 'none' : 'transform .18s ease-out';
        }

        document.addEventListener('touchstart', function (e) {
            if (e.touches.length !== 1) return;

            var g = e.target.closest && e.target.closest('.wa-glo');
            if (!g || !g.querySelector('.wa-resp-btn')) return;

            globo = g;
            desdeX = e.touches[0].clientX;
            desdeY = e.touches[0].clientY;
            corrido = 0;
            horizontal = null;
        }, { passive: true });

        document.addEventListener('touchmove', function (e) {
            if (!globo) return;

            var dx = e.touches[0].clientX - desdeX;
            var dy = e.touches[0].clientY - desdeY;

            // La primera vez que se mueve de verdad se decide qué gesto es.
            // Si arrancó vertical, es scroll y no hay que estorbarlo.
            if (horizontal === null) {
                if (Math.abs(dx) < 8 && Math.abs(dy) < 8) return;
                horizontal = Math.abs(dx) > Math.abs(dy) * 1.4;

                if (!horizontal) { globo = null; return; }
            }

            // Solo hacia la derecha, y con freno al final para que se sienta.
            corrido = Math.max(0, Math.min(dx, 90));
            if (corrido > 70) corrido = 70 + (corrido - 70) * 0.35;

            pista(globo, corrido);
        }, { passive: true });

        function soltar() {
            if (!globo) return;

            var g = globo;
            globo = null;

            pista(g, 0);

            if (corrido >= UMBRAL) {
                var boton = g.querySelector('.wa-resp-btn');
                if (boton) boton.click();

                // Un golpecito para confirmar, si el teléfono lo permite.
                try { if (navigator.vibrate) navigator.vibrate(18); } catch (err) {}
            }

            corrido = 0;
        }

        document.addEventListener('touchend', soltar, { passive: true });
        document.addEventListener('touchcancel', soltar, { passive: true });
    })();

    // ── Avisar cuando cae un mensaje ─────────────────────────────────────────
    // Suena, avisa el navegador y pone el número en el título de la pestaña.
    // Todo esto funciona con el panel abierto. Con el panel cerrado, el aviso
    // te lo sigue dando tu WhatsApp Business del teléfono.
    var waPedirPermiso;

    (function () {
        var ultimo = null;          // cuántos sin leer había la vez anterior
        var tituloBase = document.title;
        var audio = null;

        // El navegador no deja sonar nada hasta que la persona toca algo.
        // Se prepara en el primer toque, sea cual sea.
        function prepararAudio() {
            if (audio) return;
            try {
                var AC = window.AudioContext || window.webkitAudioContext;
                if (AC) audio = new AC();
            } catch (e) {}
        }

        document.addEventListener('click', prepararAudio, { once: true });
        document.addEventListener('touchstart', prepararAudio, { once: true });

        function sonar() {
            if (!audio) return;

            try {
                if (audio.state === 'suspended') audio.resume();

                // Dos notas cortas, como un timbre discreto.
                [0, 0.16].forEach(function (retraso, i) {
                    var osc = audio.createOscillator();
                    var vol = audio.createGain();

                    osc.type = 'sine';
                    osc.frequency.value = i === 0 ? 880 : 1180;

                    vol.gain.setValueAtTime(0.0001, audio.currentTime + retraso);
                    vol.gain.exponentialRampToValueAtTime(0.25, audio.currentTime + retraso + 0.02);
                    vol.gain.exponentialRampToValueAtTime(0.0001, audio.currentTime + retraso + 0.14);

                    osc.connect(vol); vol.connect(audio.destination);
                    osc.start(audio.currentTime + retraso);
                    osc.stop(audio.currentTime + retraso + 0.16);
                });
            } catch (e) {}
        }

        function avisar(cuantos) {
            sonar();

            if (!('Notification' in window) || Notification.permission !== 'granted') return;

            try {
                var n = new Notification('Baby-Confort · mensajes', {
                    body: cuantos === 1
                        ? 'Tenés 1 conversación sin leer'
                        : 'Tenés ' + cuantos + ' conversaciones sin leer',
                    icon: '/favicon-192.png',
                    tag: 'baby-confort-wa',   // no apila veinte avisos
                    renotify: true
                });

                n.onclick = function () { window.focus(); n.close(); };
            } catch (e) {}
        }

        function revisar() {
            var marca = document.getElementById('wa-sinleer');
            if (!marca) return;

            var ahora = parseInt(marca.getAttribute('data-n') || '0', 10);

            // El título de la pestaña, para verlo sin cambiar de ventana.
            document.title = ahora > 0 ? '(' + ahora + ') ' + tituloBase : tituloBase;

            // La primera vuelta solo toma nota: no suena al abrir el panel.
            if (ultimo === null) { ultimo = ahora; return; }

            if (ahora > ultimo) avisar(ahora);

            ultimo = ahora;
        }

        function verBotonPermiso() {
            var b = document.getElementById('wa-permiso');
            if (!b) return;

            var falta = ('Notification' in window) && Notification.permission === 'default';
            b.style.display = falta ? 'block' : 'none';
        }

        /**
         * Deja el teléfono listo para recibir avisos con la aplicación cerrada.
         *
         * Son tres pasos: permiso, instalar el trabajador en segundo plano, y
         * registrar este dispositivo en el servidor. Si algo falla, al menos
         * quedan el sonido y el aviso con el panel abierto.
         */
        function registrarEnSegundoPlano() {
            if (!('serviceWorker' in navigator) || !('PushManager' in window)) return;

            navigator.serviceWorker.register('/sw.js')
                .then(function (reg) {
                    return fetch('{{ route('push.clave') }}', { credentials: 'include' })
                        .then(function (r) { return r.json(); })
                        .then(function (d) {
                            if (!d.clave) throw new Error('sin clave');

                            return reg.pushManager.getSubscription().then(function (vieja) {
                                if (vieja) return vieja;

                                return reg.pushManager.subscribe({
                                    userVisibleOnly: true,
                                    applicationServerKey: enBytes(d.clave)
                                });
                            });
                        });
                })
                .then(function (sus) {
                    var j = sus.toJSON();

                    return fetch('{{ route('push.suscribir') }}', {
                        method: 'POST',
                        credentials: 'include',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify({
                            endpoint: sus.endpoint,
                            p256dh: j.keys ? j.keys.p256dh : null,
                            auth:   j.keys ? j.keys.auth   : null
                        })
                    });
                })
                .catch(function (e) {
                    console.log('Avisos en segundo plano no disponibles:', e);
                });
        }

        // La clave viene en texto y el navegador la quiere en bytes.
        function enBytes(base64) {
            var relleno = '='.repeat((4 - base64.length % 4) % 4);
            var limpio = (base64 + relleno).replace(/-/g, '+').replace(/_/g, '/');
            var crudo = atob(limpio);
            var bytes = new Uint8Array(crudo.length);

            for (var i = 0; i < crudo.length; i++) bytes[i] = crudo.charCodeAt(i);
            return bytes;
        }

        waPedirPermiso = function () {
            prepararAudio();
            sonar();   // para que se escuche cómo suena

            if (!('Notification' in window)) {
                alert('Este navegador no permite avisos. Igual vas a escuchar el sonido.');
                verBotonPermiso();
                return;
            }

            Notification.requestPermission().then(function (r) {
                verBotonPermiso();
                if (r === 'granted') registrarEnSegundoPlano();
            });
        };

        // Si el permiso ya estaba dado de antes, se registra sin preguntar.
        if (('Notification' in window) && Notification.permission === 'granted') {
            setTimeout(registrarEnSegundoPlano, 1200);
        }

        function enganchar() {
            if (!window.Livewire || !window.Livewire.hook) return false;
            window.Livewire.hook('morph.updated', function () {
                setTimeout(function () { revisar(); verBotonPermiso(); }, 0);
            });
            return true;
        }

        if (!enganchar()) document.addEventListener('livewire:init', enganchar);

        setTimeout(function () { revisar(); verBotonPermiso(); }, 400);
    })();

    // ── Las filas que se deslizan, también con el mouse ──────────────────────
    // En el teléfono se arrastran con el dedo. En la computadora no había con
    // qué: la barra de desplazamiento está escondida a propósito. Así que la
    // rueda del mouse mueve de lado, y además se puede arrastrar.
    (function () {
        var FILAS = '.wa-cab, .wa-filtros';

        document.addEventListener('wheel', function (e) {
            var fila = e.target.closest && e.target.closest(FILAS);
            if (!fila) return;
            if (fila.scrollWidth <= fila.clientWidth) return;   // entra entera

            // Si el gesto ya es horizontal (trackpad), se deja como está.
            if (Math.abs(e.deltaX) > Math.abs(e.deltaY)) return;

            fila.scrollLeft += e.deltaY;
            e.preventDefault();
        }, { passive: false });

        // Arrastrar con el botón izquierdo, como si fuera el dedo.
        var arrastrando = null, desdeX = 0, desdeScroll = 0, movio = false;

        document.addEventListener('mousedown', function (e) {
            var fila = e.target.closest && e.target.closest(FILAS);
            if (!fila || fila.scrollWidth <= fila.clientWidth) return;

            arrastrando = fila;
            desdeX = e.clientX;
            desdeScroll = fila.scrollLeft;
            movio = false;
        });

        document.addEventListener('mousemove', function (e) {
            if (!arrastrando) return;

            var dx = e.clientX - desdeX;
            if (Math.abs(dx) > 3) movio = true;

            arrastrando.scrollLeft = desdeScroll - dx;
            if (movio) e.preventDefault();
        });

        document.addEventListener('click', function (e) {
            // Si venía de arrastrar, no cuenta como clic en el botón de abajo.
            if (movio) { e.stopPropagation(); e.preventDefault(); movio = false; }
        }, true);

        document.addEventListener('mouseup', function () { arrastrando = null; });
        document.addEventListener('mouseleave', function () { arrastrando = null; });

        // La manito solo donde de verdad sobra contenido.
        function marcarDeslizables() {
            document.querySelectorAll(FILAS).forEach(function (f) {
                f.classList.toggle('wa-desliza', f.scrollWidth > f.clientWidth + 2);
            });
        }

        function engancharMarca() {
            if (!window.Livewire || !window.Livewire.hook) return false;
            window.Livewire.hook('morph.updated', function () {
                setTimeout(marcarDeslizables, 0);
            });
            return true;
        }

        if (!engancharMarca()) document.addEventListener('livewire:init', engancharMarca);
        window.addEventListener('resize', marcarDeslizables);
        setTimeout(marcarDeslizables, 300);
    })();

    // ── Que el chat siga la lectura ──────────────────────────────────────────
    // Baja solo cuando llega o se manda un mensaje, PERO solo si ya estabas
    // mirando el final. Si estás leyendo algo de más arriba, no te arrastra.
    (function () {
        var ultimaConv = null;
        var ultimoAlto = 0;

        function alFondo(forzar) {
            var chat = document.querySelector('.wa-chat');
            if (!chat) return;

            var conv = chat.getAttribute('data-conv');

            // Conversación recién abierta: siempre al último mensaje.
            if (conv !== ultimaConv) {
                ultimaConv = conv;
                ultimoAlto = chat.scrollHeight;
                chat.scrollTop = chat.scrollHeight;
                return;
            }

            var crecio = chat.scrollHeight > ultimoAlto;
            ultimoAlto = chat.scrollHeight;

            // 140 px de tolerancia: si estabas cerca del final, se considera
            // que querés seguir la conversación.
            var cerca = (chat.scrollHeight - chat.scrollTop - chat.clientHeight) < 140;

            if (forzar || (crecio && cerca)) chat.scrollTop = chat.scrollHeight;
        }

        function engancharFondo() {
            if (!window.Livewire || !window.Livewire.hook) return false;
            window.Livewire.hook('morph.updated', function () {
                setTimeout(function () { alFondo(false); }, 0);
            });
            return true;
        }

        if (!engancharFondo()) document.addEventListener('livewire:init', engancharFondo);

        // Las fotos cargan después y cambian el alto: hay que volver a bajar.
        document.addEventListener('load', function (e) {
            if (e.target && e.target.tagName === 'IMG' && e.target.closest('.wa-chat')) {
                alFondo(false);
            }
        }, true);

        setTimeout(function () { alFondo(true); }, 250);
    })();

    // ── El cuadro de escribir crece solo ─────────────────────────────────────
    (function () {
        // El último alto calculado. Se guarda para poder devolvérselo al
        // cuadro apenas Livewire lo redibuja, sin esperar a otro fotograma.
        var altoActual = 0;

        function medirYAplicar(caja) {
            if (!caja) return;

            var previo = caja.style.height;

            // Para poder achicar cuando se borra texto hay que soltar el alto
            // antes de medir. Se hace y se vuelve a poner en la misma vuelta,
            // sin devolverle el control al navegador: así no parpadea.
            caja.style.height = 'auto';
            var nuevo = caja.scrollHeight;
            caja.style.height = previo;

            if (!nuevo) return;

            altoActual = nuevo;
            caja.style.height = nuevo + 'px';
        }

        function elCuadro() {
            return document.querySelector('.wa-escribir');
        }

        document.addEventListener('input', function (e) {
            if (e.target && e.target.classList.contains('wa-escribir')) medirYAplicar(e.target);
        });

        /*
         * Cada 3 segundos el chat se refresca y Livewire vuelve a dibujar el
         * cuadro desde el HTML del servidor, que no trae el alto. Si se espera
         * al siguiente fotograma para devolvérselo, se ve achicarse y crecer:
         * eso era el temblor.
         *
         * Por eso se hace sincrónico dentro del propio enganche de Livewire,
         * antes de que el navegador llegue a dibujar nada.
         */
        function engancharAlto() {
            if (!window.Livewire || !window.Livewire.hook) return false;

            window.Livewire.hook('morph.updated', function () {
                var caja = elCuadro();
                if (!caja) return;

                // Si el texto no cambió, se le devuelve el alto que ya tenía
                // sin volver a medir: medir es lo que produce el salto.
                if (altoActual && caja.value === ultimoTexto) {
                    caja.style.height = altoActual + 'px';
                    return;
                }

                ultimoTexto = caja.value;
                medirYAplicar(caja);
            });

            return true;
        }

        var ultimoTexto = null;

        if (!engancharAlto()) document.addEventListener('livewire:init', engancharAlto);

        setTimeout(function () {
            var caja = elCuadro();
            if (caja) { ultimoTexto = caja.value; medirYAplicar(caja); }
        }, 300);
    })();

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
                document.documentElement.classList.remove('wa--teclado');
                if (caja) caja.classList.remove('wa--anclada');
                return;
            }

            // ¿Está el teclado abierto? Si la ventana visual es bastante más
            // chica que la de la página, sí.
            var teclado = (window.innerHeight - vv.height) > 120;

            // Con el teclado abierto el espacio es de verdad poco, y el CSS
            // necesita saberlo para achicar el cuadro de escribir y guardar
            // los botones en un solo renglón. Si no, entre el teclado y el
            // cuadro lleno no queda sitio para leer lo que dijo el cliente.
            document.documentElement.classList.toggle('wa--teclado', teclado);

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
