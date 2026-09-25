<x-filament-panels::page>

<style>
    /* Las proporciones salen de medir Wasapi: lista de 400 px y separación de
       24 px. Con 320 el nombre y la vista previa quedaban apretados. */
    /* 132 px descontaba la barra de arriba MÁS los 64 de relleno de la sección.
       Ese relleno ya no existe, así que descontar de más dejaba espacio
       muerto abajo. Ahora solo se descuenta la barra. */
    .wa{display:grid;grid-template-columns:400px 1fr;gap:20px;align-items:start;
        height:calc(100dvh - 72px);min-height:460px}

    @media(max-width:1200px){ .wa{grid-template-columns:340px 1fr;gap:14px} }

    /* El título "WhatsApp" no dice nada que no se sepa por el menú de arriba,
       y se lleva casi 100 px de alto de conversación. */
    .fi-header{display:none !important}

    /* ── LA FRANJA NEGRA, POR FIN ───────────────────────────────────────────
       Filament envuelve la página en:

           <section class="flex flex-col gap-y-8 py-8">

       py-8 son 32 píxeles arriba y 32 abajo. Eso era la franja.

       Lo que me costó encontrarlo: esa sección NO lleva la clase "fi-page".
       Son utilidades de Tailwind y nada más. Estuve apagando .fi-page,
       .fi-main y .fi-main-ctn, que existen pero no eran las que ponían el
       espacio. Y midiendo tampoco se cerraba, porque el relleno está DENTRO
       del contenedor: no es un hueco entre dos elementos.

       :has() la agarra por lo único estable que tiene — contener el panel —
       en vez de por un nombre de clase que puede cambiar en la próxima
       versión. Va sin media query: el espacio sobra igual en la computadora. */
    section:has(.wa){padding-top:0 !important;padding-bottom:0 !important;
                     gap:0 !important}

    /* ── Y LA FRANJA DE LOS COSTADOS ────────────────────────────────────────
       Misma historia, otro elemento. El <main> es:

           <main class="fi-main mx-auto h-full w-full px-4 md:px-6 lg:px-8">

       px-4 md:px-6 lg:px-8 = 16 píxeles a cada lado, 24 en pantalla mediana,
       32 en la grande. Eso es lo que se ve como franja negra a izquierda y
       derecha.

       Ese relleno estaba apagado SOLO dentro del @media de 900. En la
       computadora seguía puesto, y es justo donde más molesta: son 64 píxeles
       de ancho que no son ni lista ni conversación.

       Mismo criterio que arriba: se agarra por contener el panel, no por el
       nombre de la clase. Así solo se apaga en esta página; el resto del admin
       conserva su aire. */
    main:has(.wa){padding-left:0 !important;padding-right:0 !important;
                  padding-top:0 !important;padding-bottom:0 !important;
                  max-width:100% !important}
    div:has(> main .wa){padding:0 !important}

    /* ── Gris secundario, uno por tema ──────────────────────────────────────
       Estaba escrito a mano, el mismo gris, en todos lados. Sobre el fondo
       oscuro da 5.6 de contraste y está bien; sobre blanco da 2.8, y el mínimo
       para texto es 4.5. O sea: en modo claro las horas, las ayudas y las
       vistas previas se leían mal. Un valor por tema lo arregla de una vez. */
    :root{--wa-suave:#5b6b7f}
    html.dark{--wa-suave:#94a3b8}

    /* ── Los dos colores de "quién habló" ───────────────────────────────────
       Uno por tema, no un valor fijo: sobre fondo oscuro hacen falta tonos
       claros, y sobre blanco hacen falta oscuros. Los cuatro pasan el mínimo
       de contraste para texto (4.5), así que se leen de verdad, no solo se
       distinguen de lejos.

       Verdes y rojos apagados a propósito. Un verde o un rojo fuertes, en una
       lista entera y repetidos cincuenta veces, cansan a los diez minutos —
       y esta pantalla se mira todo el día. */
    :root{--wa-mio-txt:#0f7a4d;--wa-suyo-txt:#c0322a}
    html.dark{--wa-mio-txt:#6fcfa0;--wa-suyo-txt:#f59289}

    /* ── Respuesta al toque ─────────────────────────────────────────────────
       En el teléfono no hay "hover": si un botón no responde al apretarlo, se
       siente muerto y uno lo toca de nuevo. Un cambio inmediato al presionar
       es lo que hace que la interfaz se sienta viva. */
    .wa-chip:active,.wa-fil:active,.wa-pend:active,.wa-volver:active,
    .wa-clavo:active,.wa-resp-btn:active,.wa-opcion:active,.wa-slash-op:active,
    .wa-foto:active,.wa-pres:active,.wa-procesar:active{
        transform:scale(.97);filter:brightness(.92)
    }
    .wa-item:active{background:rgba(120,140,170,.18)}

    /* ── Quien pidió menos movimiento, menos movimiento ─────────────────────
       Es un ajuste del sistema operativo, y hay gente que lo activa porque el
       movimiento le marea de verdad. Se respeta. */
    @media(prefers-reduced-motion:reduce){
        *,*::before,*::after{
            animation-duration:.01ms !important;animation-iteration-count:1 !important;
            transition-duration:.01ms !important;scroll-behavior:auto !important
        }
        .wa-chip:active,.wa-fil:active,.wa-pend:active,.wa-volver:active,
        .wa-clavo:active,.wa-resp-btn:active{transform:none}
    }

    /* Etiquetas largas y cortas, según el ancho.
       OJO CON EL ORDEN: estos dos valores por defecto tienen que ir ANTES del
       @media. Una consulta de medios no suma especificidad, así que si la regla
       general va después, gana igual y anula la del teléfono. Eso dejó al botón
       "Orden de envío" sin texto: las dos versiones escondidas a la vez y solo
       el ícono a la vista. */
    .wa-t-largo{display:inline}
    .wa-t-corto{display:none}

    /* En el teléfono se ve una cosa a la vez, como WhatsApp: la lista, o el
       chat abierto ocupando toda la pantalla. Antes se apilaban las dos y
       había que bajar media pantalla para llegar al cuadro de escribir. */
    @media(max-width:900px){
        /* En el teléfono cada píxel de alto es contexto de la conversación.
           El título "WhatsApp" y los márgenes de Filament se comían un tercio
           de la pantalla para no decir nada que no se sepa. */
        /* Cero arriba: el chat arranca pegado a la barra gris de Filament.
           Antes quedaba una franja negra entre las dos —el aire que Filament le
           deja a cualquier página— y en un teléfono eso son píxeles de
           conversación tirados para no mostrar nada. */
        /* Y a los costados también. Filament deja aire a izquierda y derecha
           para que el contenido no toque el borde; en una página normal está
           bien, pero acá ese aire son dos franjas negras y menos ancho para los
           globos y para la cabecera, que es la que se estaba cortando. */
        .fi-main{padding:0 !important;max-width:100% !important}
        .fi-main-ctn{padding:0 !important}
        .fi-page{gap:0 !important}
        .fi-page > *{gap:0 !important;margin-top:0 !important}
        /* Filament separa las secciones de la página entre sí; acá hay una sola. */
        .fi-page > * + *{margin-top:0 !important}

        /* Esa franja de arriba está casi vacía. No se le puede meter el nombre
           del contacto (es de Filament, fuera de esta página), pero sí se puede
           achicar.

           OJO: hay que achicar la barra Y el nav de adentro. Antes solo tocaba
           el nav, y la barra seguía midiendo lo mismo: lo que sobraba se veía
           como una franja negra debajo del menú. Y no había forma de cerrarla
           midiendo, porque no era un hueco entre dos elementos — era el fondo
           de la barra asomando por dentro. */
        .fi-topbar{box-shadow:none !important;
                   min-height:0 !important;height:auto !important;
                   padding-top:0 !important;padding-bottom:0 !important}
        .fi-topbar > *{min-height:0 !important}
        .fi-topbar nav{min-height:44px !important;height:44px !important;
                       padding-top:.2rem !important;padding-bottom:.2rem !important}

        .wa-cab .wa-nombre-col{min-width:70px}

        /* --wa-alto lo mantiene el JS de abajo con el alto REAL que queda
           libre cuando el teclado está abierto. El calc es el respaldo para
           navegadores que no avisan del teclado. */
        /* 46 px = lo que mide la barra gris de Filament ya achicada. Antes se
           descontaban 52 por el aire que había encima del panel; ese aire ya no
           existe, así que descontar de más dejaba una franja negra abajo. */
        .wa{grid-template-columns:1fr;gap:0;min-height:0;
            height:var(--wa-alto, calc(100dvh - 46px))}

        /* Con el teclado abierto, la página se traba.
           Antes el panel se clavaba con position:fixed y se le corregía el
           "top" en cada evento; eso generaba un ida y vuelta —mover el panel
           desplazaba la página, y el desplazamiento volvía a mover el panel—
           que se veía como un sube y baja.
           Trabando el desplazamiento no hay adónde rebotar: el panel mide
           exactamente lo que se ve y se queda quieto. */
        html.wa--teclado,
        html.wa--teclado body{overflow:hidden;overscroll-behavior:none}
        .wa--abierta .wa-izq{display:none}
        .wa:not(.wa--abierta) .wa-der{display:none}
        /* De borde a borde, sin esquinas ni marco. Cualquier curva o borde deja
           ver el fondo negro y vuelve a parecer que hay una franja. En el
           teléfono el panel ES la pantalla: no tiene que parecer una tarjeta
           puesta encima de algo. */
        .wa-col{border-radius:0;border:none}
        .wa-glo{max-width:88%}
        /* min-height porque la cabecera se desliza de lado, y una caja que se
           desliza recorta también hacia arriba: con el número y "Ponerle
           nombre" en dos renglones, al número se le cortaba la mitad de arriba.
           Con alto suficiente no hay nada que recortar. */
        .wa-cab{padding:7px 9px;gap:6px;min-height:54px}
        .wa-cab .wa-nombre-col > div{line-height:1.25}
        .wa-tab{padding:0 7px;font-size:14px}
        .wa-volver{display:inline-flex !important}
        .wa-fila2{grid-template-columns:1fr}
        .wa-chat{padding:10px}
        /* El área segura de abajo: en los teléfonos con barra de gestos, sin
           esto el botón de Enviar queda debajo de la barra del sistema y al
           tocarlo se sale de la aplicación en vez de mandar el mensaje. */
        .wa-abajo{padding:8px 8px calc(8px + env(safe-area-inset-bottom))}
        .wa-escribir{padding:8px 10px}

        /* Etiquetas cortas para que las tres pestañas entren en un renglón */
        .wa-t-largo{display:none}
        .wa-t-corto{display:inline}

        .wa-filtros{flex-wrap:nowrap;overflow-x:auto;scrollbar-width:none}
        .wa-filtros::-webkit-scrollbar{display:none}
        /* Los filtros y los chips de pendientes, a altura de dedo.
           En la computadora se aciertan con el puntero; en el teléfono, con
           22 px de alto, se falla y se termina abriendo una conversación. */
        .wa-fil{flex:none;min-height:38px;padding:7px 13px;font-size:12.5px}
        .wa-pend{min-height:38px;padding:8px 13px;font-size:12.5px}
        .wa-volver{width:42px;height:42px}
        .wa-clavo{font-size:16px;padding:9px 10px;margin:-9px 0 -9px -10px}
    }

    /* Solo aparece en pantallas chicas: en la computadora estorba. */
    .wa-volver{display:none;border:none;background:rgba(120,140,170,.16);cursor:pointer;
               border-radius:9px;width:34px;height:34px;font-size:17px;align-items:center;
               justify-content:center;font-family:inherit;color:inherit;flex:none}

    /* ── El de volver, clavado ──────────────────────────────────────────────
       La cabecera se desliza de lado para que entren las pestañas y las
       etiquetas sin partir el renglón. Efecto no buscado: el botón de salir
       se iba con ellas. Y es el único del que dependés para volver a la
       lista — quedarte sin él mientras buscás una pestaña es quedarte
       encerrado.

       position:sticky dentro de un contenedor que se desliza a lo ancho lo
       deja quieto en el borde izquierdo mientras el resto pasa por detrás.

       Dos detalles para que se vea bien:

       · El fondo TIENE que ser opaco. El de los otros botones es translúcido
         y se vería el texto de la cabecera moviéndose por debajo.
       · La primera sombra no es sombra: es un bloque sólido de 7 px del color
         del panel, para tapar el hueco que la fila deja entre botones. La
         segunda sí es sombra, y es la que cuenta que ahí hay algo pasando
         por detrás. */
    :root{--wa-cab-bg:#fff}
    html.dark{--wa-cab-bg:#16202f}

    .wa-atras{position:sticky;left:0;z-index:4;background:#e9edf3;
              box-shadow:7px 0 0 var(--wa-cab-bg),
                         14px 0 11px -8px rgba(10,16,26,.30)}
    html.dark .wa-atras{background:#26344a;
                        box-shadow:7px 0 0 var(--wa-cab-bg),
                                   14px 0 11px -8px rgba(0,0,0,.55)}

    .wa-col{background:#fff;border:1px solid #e5e7eb;border-radius:14px;
            display:flex;flex-direction:column;overflow:hidden;height:100%}
    html.dark .wa-col{background:#16202f;border-color:rgba(255,255,255,.10)}

    /* ── Lo que quedó a medias ──
       Se desliza si no entra, como el carrusel de filtros: en el teléfono es
       mejor que se corra de lado a que parta el renglón en tres. */
    .wa-pendientes{display:flex;gap:6px;margin-bottom:9px;overflow-x:auto;
                   scrollbar-width:none;-ms-overflow-style:none;padding-bottom:2px}
    .wa-pendientes::-webkit-scrollbar{display:none}
    .wa-pend{flex:none;border:1px solid var(--c);background:transparent;color:inherit;
             border-radius:999px;padding:5px 11px;font-size:11.5px;font-weight:600;
             font-family:inherit;cursor:pointer;white-space:nowrap;
             text-decoration:none;display:inline-flex;align-items:center;gap:4px}
    .wa-pend b{color:var(--c);font-weight:800;font-size:13px}
    .wa-pend:hover{background:rgba(120,140,170,.12)}
    /* Encendido = ese filtro está puesto. Sin esto no habría cómo saber por qué
       la lista se ve corta, ni cómo volver a verla entera. */
    .wa-pend.on{background:var(--c);border-color:var(--c);color:#fff}
    .wa-pend.on b{color:#fff}
    /* Rojo lo que espera a un cliente; ámbar lo que espera a vos. */
    .wa-pend-rojo{--c:#e5695f}
    .wa-pend-ambar{--c:#d4a017}
    .wa-pend-azul{--c:#4aa3df}

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
    /* ── De quién fue el último mensaje, por color ──────────────────────────
       Verde lo escribimos nosotros, rojo lo escribió el cliente.

       Antes la diferencia era gris contra blanco, y a media pantalla de chats
       todo se veía del mismo tono claro: había que fijarse en si estaban los
       puntitos del semáforo para saber si ya habías contestado. Eso es leer,
       no ver.

       El color se lee sin leer. Y usa el mismo idioma que el resto del panel:
       rojo es lo que espera a alguien, verde es lo que ya salió. */
    .wa-prev{font-size:13.5px;color:var(--wa-mio-txt);margin-top:3px;overflow:hidden;
             text-overflow:ellipsis;white-space:nowrap}

    /* Sin palomitas y con la barrita: el último mensaje es del cliente y está
       esperando respuesta. Es el estado que hay que poder ver de lejos. */
    .wa-prev-debo{color:var(--wa-suyo-txt);font-weight:600;
                  border-left:3px solid #e5695f;padding-left:7px;margin-left:-1px}
    /* El renglón chico de abajo: solo el teléfono, y solo cuando arriba va un
       nombre que vos pusiste. El nombre del perfil de WhatsApp no se muestra. */
    .wa-apodo{font-size:11.5px;color:var(--wa-suave);opacity:.8;margin-top:1px;overflow:hidden;
              text-overflow:ellipsis;white-space:nowrap}

    /* El chinche de la lista. Fija y suelta desde acá, sin entrar al chat:
       cuando uno quiere fijar una conversación, la está viendo en la lista.
       Apagado en gris mientras no está fijada, para que no parezca que sí.
       El área de toque es más grande que el dibujo: en el teléfono, un ícono
       de 11 px no se acierta. */
    .wa-clavo{border:none;background:none;cursor:pointer;font-family:inherit;
              font-size:13px;line-height:1;padding:4px 5px;margin:-4px 0 -4px -5px;
              border-radius:7px;flex:none;filter:grayscale(1);opacity:.35}
    .wa-clavo:hover{opacity:.8;background:rgba(120,140,170,.18)}
    .wa-clavo.on{filter:none;opacity:1}

    /* El de copiar el número. Como el de fijar: sirve en el teléfono y en la
       computadora, así que se muestra en los dos. */
    .wa-copia{display:inline-flex;font-size:15px}
    @media(max-width:900px){ .wa-clavo{font-size:15px;padding:6px 7px;margin:-6px 0 -6px -7px} }
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
    .wa-hora{font-size:11.5px;color:var(--wa-suave);float:right;font-weight:400}
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

    /* ── La ficha del cliente ──
       Un renglón, no una tarjeta: el alto que ocupe se lo quita a la
       conversación, y la conversación es el trabajo. Abierta puede crecer;
       cerrada tiene que costar casi nada. */
    .wa-ficha{flex:none;border-bottom:1px solid #e5e7eb;background:rgba(74,163,223,.06)}
    html.dark .wa-ficha{border-color:rgba(255,255,255,.10);background:rgba(74,163,223,.08)}
    .wa-ficha-top{width:100%;display:flex;align-items:center;justify-content:space-between;
                  gap:10px;border:none;background:none;cursor:pointer;font-family:inherit;
                  color:inherit;text-align:left;padding:9px 12px;min-height:44px}
    .wa-ficha-res{display:flex;align-items:center;gap:9px;flex-wrap:wrap;min-width:0;
                  font-size:12.5px}
    .wa-ficha-res b{font-weight:700;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
    .wa-ficha-veces{background:rgba(46,158,107,.16);color:#15603f;font-weight:800;
                    font-size:11px;border-radius:999px;padding:2px 9px;flex:none}
    html.dark .wa-ficha-veces{color:#9fe1cb}
    .wa-ficha-flecha{color:var(--wa-suave);font-size:14px;flex:none}

    .wa-ficha-mas{padding:0 12px 11px;font-size:13px}
    .wa-ficha-fila{display:flex;gap:10px;padding:5px 0;align-items:baseline}
    .wa-ficha-fila span{flex:none;width:74px;font-size:11.5px;color:var(--wa-suave);
                        text-transform:uppercase;letter-spacing:.03em;font-weight:700}
    .wa-ficha-fila b{font-weight:600;line-height:1.45}
    .wa-ficha-copiar{margin-top:7px;border:1px solid #d1d5db;background:none;color:inherit;
                     font-family:inherit;font-size:12.5px;font-weight:700;cursor:pointer;
                     border-radius:9px;padding:9px 13px;min-height:44px;width:100%}
    html.dark .wa-ficha-copiar{border-color:rgba(255,255,255,.16)}
    .wa-ficha-ojo{margin-top:9px;font-size:11.5px;color:var(--wa-suave);line-height:1.45}

    .wa-chat{flex:1;overflow-y:auto;padding:14px;display:flex;flex-direction:column;gap:8px;
             background:#f6f8fa}
    html.dark .wa-chat{background:#0f1828}
    /* Nada de white-space aquí: iría contra toda la sangría de la plantilla y
       llenaría el globo de aire. Los saltos de línea los respeta .wa-txt. */
    .wa-glo{max-width:74%;padding:9px 13px;border-radius:13px;font-size:15px;line-height:1.45;
            word-wrap:break-word;text-align:left}
    .wa-txt{white-space:pre-wrap;overflow-wrap:anywhere}
    /* El globo que ya se ve pero todavía no salió. Apagado, no gris: tiene que
       leerse como "va en camino", no como "falló". Cuando Meta confirma,
       recupera el color solo. */
    .wa-yendo{opacity:.62}
    /* Los enlaces adentro de un globo. Subrayados y de otro color: en un chat,
       si no se ve que se puede tocar, no se toca. */
    .wa-enlace{color:#4aa3df;text-decoration:underline;text-underline-offset:2px;
               /* El globo bloquea la selección para poder deslizarlo; el enlace
                  necesita recibir el toque igual. */
               -webkit-user-select:text;user-select:text;pointer-events:auto}
    .wa-enlace:hover{opacity:.8}
    /* En los mensajes propios el fondo es verde: el celeste no contrasta. */
    .wa-mio .wa-enlace,.wa-auto .wa-enlace{color:#d9f2e4}

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

    /* ── La separación entre días ───────────────────────────────────────────
       Va centrada y en su propia pastilla, no alineada con los globos: no es
       un mensaje de nadie, es una marca en el tiempo, y tiene que leerse como
       otra cosa.

       align-self:center porque el chat es un flex en columna y, sin eso,
       la pastilla se estiraría de lado a lado. */
    .wa-dia{align-self:center;margin:6px 0 2px}
    .wa-dia span{display:inline-block;padding:3px 12px;border-radius:999px;
                 font-size:11.5px;font-weight:700;letter-spacing:.02em;
                 background:rgba(120,140,170,.16);color:var(--wa-suave)}
    html.dark .wa-dia span{background:rgba(255,255,255,.08)}

    /* La luz del semáforo: gris salió, amarillo le llegó al teléfono, verde lo
       leyó, rojo falló. Un punto, no palomitas — una palomita y dos palomitas
       son el mismo dibujo y en el teléfono hay que fijarse para distinguirlas;
       un color se lee sin leer.

       El letter-spacing negativo de antes era para juntar las dos palomitas.
       Ahora es un solo carácter y sobraría. */
    .wa-check{font-size:21px;margin-left:6px;vertical-align:-3px;
              /* line-height a 0 para que el punto crezca sin estirar el
                 renglón de la hora. Sin esto, agrandarlos separa todo. */
              line-height:0;
              /* Los dos puntos, separados: pegados se leen como uno solo
                 gordo y se pierde la mitad de la información. */
              letter-spacing:3px;
              /* Un halo del mismo color: los despega del fondo del globo, que
                 en los mensajes propios es de un tono parecido. */
              text-shadow:0 0 5px currentColor}
    @media(max-width:900px){ .wa-check{font-size:24px;letter-spacing:3.5px;vertical-align:-4px} }

    /* ── Responder a un mensaje puntual ── */
    .wa-resp-btn{border:none;background:none;cursor:pointer;font-family:inherit;color:inherit;
                 font-size:12px;padding:0 5px 0 0;opacity:.55}
    .wa-resp-btn:hover{opacity:1}

    /* El globo se corre con el dedo, así que no debe quedar seleccionado
       mientras se arrastra. */
    .wa-glo{touch-action:pan-y;-webkit-user-select:none;user-select:none}
    .wa-txt{-webkit-user-select:text;user-select:text}

    @media(max-width:900px){
        /* Área de toque de 44 px, que es el mínimo para el dedo.
           El dibujo sigue chico —no queremos tres íconos gigantes adentro del
           globo— pero el área que responde al toque es la que importa: son
           tres botones seguidos y errarle a uno significa copiar cuando
           querías responder.

           El margen negativo compensa el relleno, así el pie del globo no
           crece de alto por esto. */
        .wa-resp-btn{font-size:16px;opacity:.75;
                     min-width:44px;min-height:44px;
                     padding:0 6px;margin:-11px 0;
                     display:inline-flex;align-items:center;justify-content:center}
    }

    .wa-cita{border-left:3px solid currentColor;padding:4px 0 4px 8px;margin-bottom:6px;
             font-size:12.5px;line-height:1.4;opacity:.72}
    .wa-cita-q{font-weight:800;font-size:11px;margin-bottom:2px}

    .wa-citando{display:flex;gap:10px;align-items:center;margin-bottom:8px;
                background:rgba(120,140,170,.12);border-left:3px solid #2e9e6b;
                border-radius:0 9px 9px 0;padding:7px 10px}
    .wa-citando-x{font-size:12.5px;color:var(--wa-suave);overflow:hidden;
                  text-overflow:ellipsis;white-space:nowrap}
    /* La miniatura de la foto que está esperando para salir. */
    .wa-pend-img{width:38px;height:38px;object-fit:cover;border-radius:7px;flex:none}
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
    /* En el teléfono el cuadro de escribir se encoge y se estira solo:
       UN renglón mientras estás leyendo la conversación, TRES mientras
       escribís. Ni un píxel de la pantalla se queda ocupado por algo que no
       estás usando en ese momento.

       El tope va en renglones y no en porcentaje de pantalla: un tercio de la
       pantalla daba tres renglones en un teléfono chico y siete en uno grande.
       Tres renglones son tres en cualquiera. */
    @media(max-width:900px){
        .wa-escribir{--wa-renglones:1;
                     max-height:calc(var(--wa-renglones) * 1.45em + 26px);
                     transition:max-height .18s ease}
        /* wa--teclado la pone el JS cuando el cursor está en el cuadro. */
        html.wa--teclado .wa-escribir{--wa-renglones:3}

        /* Con algo escrito adentro, NUNCA un solo renglón.
           El renglón único es para cuando el cuadro está vacío y no estorba.
           Pero al traer una respuesta rápida el cuadro se llena sin que vos
           toques nada: si ahí se queda en un renglón, tu propio texto queda
           aplastado y hay que abrirlo a mano para verlo. */
        .wa--con-texto .wa-escribir{--wa-renglones:3}

        /* Abierto a lo alto: para LEER un texto largo que acaba de entrar, no
           para escribir. Se limita a la mitad de lo que se ve, así queda algo
           de conversación arriba y uno no pierde el hilo de a qué contesta. */
        .wa--caja-grande .wa-escribir,
        html.wa--teclado .wa--caja-grande .wa-escribir{
            max-height:calc(var(--wa-alto, 100vh) * .48)
        }
    }

    /* El botón de abrir y cerrar. Solo tiene sentido en el teléfono: en la
       computadora el cuadro ya llega a 38vh y no aprieta a nadie. */
    .wa-agrandar{display:none}
    @media(max-width:900px){
        .wa-agrandar{display:block;width:100%;border:none;background:none;
                     font-family:inherit;font-size:11px;font-weight:700;
                     color:var(--wa-suave);cursor:pointer;padding:0 0 5px;text-align:right;
                     letter-spacing:.02em}
        .wa-agrandar:hover{color:inherit}
    }
    /* ── La lista que abre la barra "/" ──
       Va flotando ENCIMA del cuadro de escribir, no debajo: debajo quedaría
       tapada por el teclado del teléfono, que es justo donde más sirve. */
    .wa-slash-wrap{position:relative}
    .wa-slash{position:absolute;left:0;right:0;bottom:calc(100% + 6px);z-index:30;
              max-height:260px;overflow-y:auto;padding:5px;
              background:#fff;border:1px solid #d1d5db;border-radius:12px;
              box-shadow:0 -10px 30px rgba(10,16,26,.20)}
    html.dark .wa-slash{background:#16202f;border-color:rgba(255,255,255,.16)}
    .wa-slash-t{font-size:12px;font-weight:800;color:var(--wa-suave);text-transform:uppercase;
                letter-spacing:.04em;padding:6px 9px 4px}
    .wa-slash-op{display:block;width:100%;text-align:left;border:none;background:none;
                 font-family:inherit;color:inherit;cursor:pointer;padding:8px 9px;
                 border-radius:8px}
    .wa-slash-op:hover{background:rgba(120,140,170,.14)}
    .wa-slash-op b{display:block;font-size:14px}
    .wa-slash-op span{display:block;font-size:12px;color:var(--wa-suave);margin-top:2px;
                      overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
    .wa-slash-nada{padding:9px;font-size:12px;color:var(--wa-suave)}
    @media(max-width:900px){
        .wa-slash{max-height:210px}
        .wa-slash-op{padding:10px 9px}
    }

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
    /* Los botones en UN renglón que se desliza, con teclado y sin teclado.
       Antes solo se apretaban con el teclado abierto, y sin él se partían en
       dos filas que se comían el alto de dos globos de conversación. Al
       deslizarse, caben todos igual: los que más usás quedan a la vista y el
       resto a un empujón. */
    @media(max-width:900px){
        .wa-btns{flex-wrap:nowrap;overflow-x:auto;margin-top:7px;
                 scrollbar-width:none;-ms-overflow-style:none;padding-bottom:2px}
        .wa-btns::-webkit-scrollbar{display:none}
        .wa-btns > *{flex:none}
    }
    html.wa--teclado .wa-btns{margin-top:6px}
    html.wa--teclado .wa-chat{min-height:calc(var(--wa-alto, 100vh) * .26)}
    /* La cabecera del contacto también cede un poco: con el teclado abierto
       el número ya lo tenés a la vista, no hace falta tanto aire. */
    html.wa--teclado .wa-cab{padding:5px 9px}

    /* El de mandar una foto del aparato. Es un <label>, no un <button>, así
       que hay que darle a mano lo que un botón trae solo: el cursor de mano y
       el no partirse en dos renglones dentro del carrusel. */
    .wa-foto-btn{cursor:pointer;white-space:nowrap;display:inline-flex;
                 align-items:center;user-select:none}
    .wa-foto-btn:hover{border-color:#4aa3df;color:#2b7fb8}
    html.dark .wa-foto-btn:hover{color:#8ecbf0}
    .wa-foto-btn:active{transform:scale(.97);filter:brightness(.92)}
    /* Mientras sube, apagado y sin manito: que se note que ya está trabajando
       y que tocarlo de nuevo no sirve. Sin esto se toca tres veces y se
       mandan tres fotos. */
    .wa-foto-btn--yendo{opacity:.55;cursor:wait;pointer-events:none}

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

    .wa-vacio{flex:1;display:grid;place-items:center;color:var(--wa-suave);font-size:14px;text-align:center;padding:30px}
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
    .wa-lab{font-size:11.5px;font-weight:700;color:var(--wa-suave);display:block;margin-bottom:4px;
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
                   border-right:2px solid var(--wa-suave);border-bottom:2px solid var(--wa-suave)}
    /* La lista que se abre la dibuja el sistema: sin esto sale en blanco
       encima del panel oscuro y no se lee. */
    select.wa-in option{background:#ffffff;color:#111827}
    html.dark select.wa-in{color-scheme:dark}
    html.dark select.wa-in option{background:#1f2937;color:#e5e7eb}
    /* 12 px es el piso para que un texto se lea sin esfuerzo. Debajo de eso,
       lo que pasa no es que cueste: es que no se lee y se pasa por alto. */
    .wa-ayuda{font-size:12px;color:var(--wa-suave);margin-top:4px;line-height:1.4}

    /* ── El buscador de municipio ── */
    [x-cloak]{display:none !important}
    .wa-busca{position:relative}
    /* La lista flota sobre lo de abajo: si empujara el formulario, cada vez que
       se abre saltaría todo hacia abajo. */
    .wa-opciones{position:absolute;left:0;right:0;z-index:20;margin-top:4px;
                 max-height:240px;overflow-y:auto;
                 background:#fff;border:1px solid #d1d5db;border-radius:11px;
                 box-shadow:0 12px 30px rgba(10,16,26,.18);padding:4px}
    html.dark .wa-opciones{background:#16202f;border-color:rgba(255,255,255,.16)}
    .wa-opcion{display:block;width:100%;text-align:left;border:none;background:none;
               font-family:inherit;font-size:14px;color:inherit;cursor:pointer;
               padding:9px 11px;border-radius:8px}
    .wa-opcion:hover{background:rgba(120,140,170,.14)}
    .wa-opcion-nada{padding:10px 11px;font-size:12.5px;color:var(--wa-suave)}
    @media(max-width:900px){
        /* Más alto para el dedo, y la lista más corta para que no tape el
           teclado cuando está abierto. */
        .wa-opcion{padding:11px}
        .wa-opciones{max-height:190px}
    }
    .wa-fila2{display:grid;grid-template-columns:1fr 1fr;gap:10px}
    .wa-linea{display:grid;grid-template-columns:1fr 78px 34px;gap:8px;align-items:center;
              margin-bottom:8px}

    /* Cuatro columnas: producto, cantidad, precio y la ✕.
       El producto se lleva lo que sobra; los otros tres son fijos y angostos,
       porque un precio y una cantidad no necesitan más. */
    .wa-linea-4{display:grid;grid-template-columns:1fr 62px 84px 34px;gap:7px;
                align-items:center}
    @media(max-width:900px){
        /* En el teléfono no entran cuatro. El producto se lleva un renglón
           entero para arriba y abajo van los números, que son cortos. */
        .wa-linea-4{grid-template-columns:1fr 84px 34px}
        .wa-linea-4 > :first-child{grid-column:1 / -1}
    }

    /* El renglón chico de abajo: de dónde salió el precio y cuánto suma.
       Sin esto, un número suelto en el campo no dice si lo cotizaste vos en el
       chat o si lo puso el sistema desde el catálogo — y esa diferencia es la
       que decide si hay que corregirlo. */
    .wa-linea-pie{font-size:11.5px;color:var(--wa-suave);margin-top:3px;
                  padding-left:2px;line-height:1.4}
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
    .wa-resp-p{font-size:12px;color:var(--wa-suave);margin-top:3px;overflow:hidden;
               text-overflow:ellipsis;white-space:nowrap}

    /* La orden pegada arriba del formulario, para comparar sin cambiar de ventana */
    .wa-origen{border:1px solid #d4a017;background:rgba(234,179,8,.10);border-radius:11px;
               padding:11px 13px;margin-bottom:16px}
    .wa-origen-t{display:flex;justify-content:space-between;align-items:center;
                 font-size:12px;font-weight:700;color:#7a5600;margin-bottom:7px}
    html.dark .wa-origen-t{color:#f0d79a}
    .wa-origen-x{white-space:pre-wrap;font-size:13px;line-height:1.6;max-height:300px;
                 overflow-y:auto;font-family:ui-monospace,Menlo,Consolas,monospace}

    /* Los dos cuadros de comparar, lado a lado y arriba de todo — también en el
       teléfono, que era donde no se podía. Antes la orden iba arriba y la
       previa al final, con el formulario entero en medio: comparar obligaba a
       subir y bajar, y lo que cuesta se deja de hacer.

       Quedan pegados arriba mientras se llena el formulario, con tope de alto
       y scroll adentro de cada uno, para que nunca se coman la pantalla. */
    .wa-arriba{display:grid;grid-template-columns:1fr 1fr;gap:10px;align-items:start;
               margin-bottom:14px;position:sticky;top:0;z-index:5;
               /* Fondo opaco: los dos cuadros son translúcidos y, sin esto, el
                  formulario se vería pasar por debajo mientras se desplaza. */
               background:#fff;padding-bottom:8px}
    html.dark .wa-arriba{background:#16202f}
    /* Sin orden pegada queda solo la previa: que ocupe todo el ancho. */
    .wa-arriba-sola{grid-template-columns:1fr}
    .wa-arriba .wa-origen{margin-bottom:0}
    .wa-arriba .wa-previa{margin-top:0}

    @media(max-width:900px){
        /* En el teléfono, más chico y más apretado: el objetivo es que los dos
           entren a la vez, no que se lean cómodos. Para leer cómodo está el
           formulario de abajo. */
        /* Los dos cuadros de comparar, uno al lado del otro.
           Acá había letra de 9.5 px. La apreté yo para que los dos entraran, y
           es el peor lugar posible para hacerlo: este recuadro es el que se
           lee para revisar una guía ANTES de guardarla. Si cuesta leerlo, se
           lee por encima, y por ahí se cuela el error que todo esto quería
           evitar.
           Ahora el piso es 12 px, que es el mínimo legible. Entran igual
           porque cada cuadro se desliza por dentro. */
        .wa-arriba{gap:7px;margin-bottom:11px}
        .wa-arriba .wa-origen,
        .wa-arriba .wa-previa{padding:9px 10px;max-height:44vh;overflow-y:auto}
        .wa-arriba .wa-origen-x{font-size:12px;line-height:1.5;max-height:none}
        .wa-arriba .wa-origen-t,
        .wa-arriba .wa-previa-t{font-size:12px;margin-bottom:6px}
        .wa-arriba .wa-pv{font-size:13px;padding:4px 0;gap:2px;
                          flex-direction:column;align-items:flex-start}
        .wa-arriba .wa-pv span{font-size:12px;opacity:.8}
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

    .wa-cola{margin-top:14px;font-size:12.5px;line-height:1.6;color:var(--wa-suave);
             border-top:1px solid rgba(120,140,170,.18);padding-top:12px}
    .wa-cola b{color:inherit;font-weight:800}

    .wa-previa{border:1px solid #2e9e6b;background:rgba(46,158,107,.07);border-radius:11px;
               padding:12px 14px;margin-top:14px}
    .wa-previa-t{font-size:12px;font-weight:800;color:#15603f;margin-bottom:9px}
    html.dark .wa-previa-t{color:#9fe1cb}
    .wa-pv{display:flex;gap:10px;padding:5px 0;font-size:13px;
           border-bottom:1px solid rgba(46,158,107,.16);align-items:baseline}
    .wa-pv:last-child{border-bottom:none}
    .wa-pv span{color:var(--wa-suave);min-width:104px;flex:none}
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
    /* Los días, debajo del buscador. Solo se ven cuando estás buscando. */
    .wa-dias{display:flex;gap:5px;align-items:center;flex-wrap:wrap;margin-top:8px}
    .wa-dias-t{font-size:12px;font-weight:700;color:var(--wa-suave);text-transform:uppercase;
               letter-spacing:.03em;margin-right:2px}

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
    .wa-talla-c{font-size:11px;color:var(--wa-suave)}

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
    .wa-foto-p{display:block;font-size:12px;color:var(--wa-suave);margin-top:2px}

    .wa-uso{display:flex;gap:9px;align-items:center;font-size:12.5px;cursor:pointer;
            background:rgba(120,140,170,.10);border-radius:9px;padding:9px 11px}
    .wa-uso-no{opacity:.5;cursor:not-allowed}

    .wa-pres-t{display:block;font-weight:700;font-size:14px}
    .wa-pres-p{display:block;font-size:12.5px;color:var(--wa-suave);margin-top:2px}

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

{{-- El latido no es un $refresh: primero pregunta si cambió algo y, si no,
     no redibuja nada. Ver el comentario de latir() en la página.

     wa-despachar es la segunda mitad del envío: el servidor anota el mensaje,
     el navegador lo dibuja, y recién entonces se pide la llamada a Meta. Por
     eso el globo aparece al instante en vez de después de la espera. --}}
<div class="wa {{ $abierta ? 'wa--abierta' : '' }}" wire:poll.3s="latir"
     x-on:wa-despachar.window="$wire.despachar($event.detail.id)">

    {{-- ═══ IZQUIERDA: las conversaciones ═══ --}}
    <div class="wa-col wa-izq">
        <div class="wa-top">

            {{-- Lo que quedó a medias, en un renglón.
                 El panel sabía las cuatro cosas por separado, cada una en su
                 pantalla. Juntas se leen de un vistazo, y cada número lleva a
                 su lista. Es para dejar de acordarse y empezar a mirar. --}}
            @php $pend = $this->pendientes(); @endphp

            {{-- Solo lo del circuito de las guías. "Sin responder" y "Sin leer"
                 estaban de más: la barra roja de cada conversación ya marca las
                 que esperan respuesta, y "Sin leer" ya vive en el carrusel de
                 filtros de abajo. Repetirlo acá gastaba un renglón de pantalla
                 para no decir nada nuevo. --}}
            {{-- "Sin guía" se quitó: era la etiqueta Pedidos contada de otra
                 forma, y esa ya está en el carrusel de abajo. Dos botones para
                 lo mismo no ayudan, confunden. --}}
            @if($pend['sin_enlace'])
                <div class="wa-pendientes">
                    @if($pend['sin_enlace'] && $this->enlaceCola())
                        <a href="{{ $this->enlaceCola() }}" class="wa-pend wa-pend-ambar"
                           title="Guías armadas a las que no les mandaste el enlace de rastreo">
                            <b>{{ $pend['sin_enlace'] }}</b> sin enlace
                        </a>
                    @endif

                    {{-- El botón de "revisar entregas" se quitó junto con el
                         vigilante automático: buscaba la guía por teléfono y en
                         un cliente que vuelve a pedir encontraba la anterior.
                         El paso a Entregados se hace a mano. --}}
                </div>
            @endif

            {{-- El buscador, y debajo los días — que solo aparecen al tocarlo.
                 Vivían fijos en el carrusel y ahí estorbaban: buscar por fecha
                 es algo que se hace de vez en cuando, no todo el día. Ahora se
                 muestran cuando ya estás en modo "buscar", que es cuando
                 sirven, y se esconden al salir.

                 focusin en el contenedor y no focus en el campo: así también
                 cuenta el foco que cae adentro de lo que dibuja Filament.
                 Y se cierra al tocar afuera, no al perder el foco — si se
                 cerrara al perderlo, tocar un día lo haría desaparecer antes
                 de que el toque llegue. --}}
            <div x-data="{ abierto: @js($filtroDia !== null) }"
                 x-on:focusin="abierto = true"
                 x-on:click.outside="abierto = @js($filtroDia !== null)">

                <x-filament::input.wrapper prefix-icon="heroicon-m-magnifying-glass">
                    <x-filament::input type="text" wire:model.live.debounce.400ms="buscar"
                        placeholder="Buscar nombre o número" />
                </x-filament::input.wrapper>

                <div class="wa-dias" x-show="abierto" x-cloak>
                    <span class="wa-dias-t">Con movimiento…</span>
                    @foreach(['hoy' => 'Hoy', 'ayer' => 'Ayer', 'anteayer' => 'Anteayer'] as $clave => $texto)
                        <button type="button" wire:click="filtrarDia('{{ $clave }}')"
                                wire:key="dia-{{ $clave }}"
                                class="wa-fil {{ $filtroDia === $clave ? 'on' : '' }}"
                                style="--c:#4aa3df"
                                aria-pressed="{{ $filtroDia === $clave ? 'true' : 'false' }}"
                                aria-label="Ver solo las conversaciones con movimiento {{ mb_strtolower($texto) }}">
                            {{ $texto }}
                        </button>
                    @endforeach
                </div>
            </div>

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
            @endphp

            {{-- Lo que vigila el JavaScript. Va acá adentro porque esta parte
                 se refresca sola cada 3 segundos.

                 data-n es el número que va en la pestaña del navegador.
                 data-ult es el que DISPARA el sonido: el id del último mensaje
                 que entró. Ver ultimoEntranteId() para por qué el contador no
                 servía de disparador. --}}
            <span id="wa-sinleer"
                  data-n="{{ $sinLeer }}"
                  data-ult="{{ $this->ultimoEntranteId() }}"
                  style="display:none"></span>

            {{-- Probar el sonido, siempre a la vista y no solo cuando falta dar
                 permiso. Sin esto no hay cómo saber si el problema es que el
                 sonido no funciona o que el aviso no se está disparando: son
                 dos fallas distintas y se arreglan distinto. --}}
            <button type="button" onclick="waProbarSonido()" class="wa-fil"
                    style="--c:#94a3b8;margin-top:8px"
                    aria-label="Probar el sonido del aviso">
                🔊 Probar sonido
            </button>

            <div class="wa-filtros">
                {{-- aria-pressed en los filtros: sin eso, un lector de pantalla
                     dice "Sin leer, botón" tanto si está puesto como si no. --}}
                <button type="button" wire:click="alternarSinLeer"
                        class="wa-fil {{ $soloSinLeer ? 'on' : '' }}" style="--c:#e5695f"
                        aria-pressed="{{ $soloSinLeer ? 'true' : 'false' }}">
                    Sin leer
                    @if($sinLeer > 0)<span class="wa-fil-n">{{ $sinLeer }}</span>@endif
                </button>

                {{-- Acá iba el filtro "Sin responder". La barra roja al costado
                     de cada conversación ya dice lo mismo sin ocupar lugar en
                     el carrusel. El método alternarSinResponder() sigue vivo. --}}

                @foreach($etqs as $e)
                    <button type="button" wire:click="filtrarPor({{ $e->id }})"
                            wire:key="filtro-{{ $e->id }}"
                            class="wa-fil {{ $filtroEtiqueta === $e->id ? 'on' : '' }}"
                            style="--c:{{ $e->hex() }}"
                            aria-pressed="{{ $filtroEtiqueta === $e->id ? 'true' : 'false' }}">
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
                {{-- Esta fila era un <button>. Dejó de serlo para que el chinche
                     de fijar pueda ser un botón de verdad adentro: un botón
                     dentro de otro botón no es HTML válido y los navegadores
                     hacen cualquier cosa con él. Ahora es un div que se
                     comporta como botón — con rol y con teclado, para que siga
                     funcionando sin ratón. --}}
                <div wire:click="abrir({{ $c->id }})" wire:key="conv-{{ $c->id }}"
                     role="button" tabindex="0"
                     x-on:keydown.enter.prevent="$wire.abrir({{ $c->id }})"
                     x-on:keydown.space.prevent="$wire.abrir({{ $c->id }})"
                     class="wa-item {{ $abierta === $c->id ? 'on' : '' }}">

                    <span class="wa-hora">{{ $c->horaUltimo() }}</span>

                    <span class="wa-nom">
                        {{-- .stop para que fijar no abra también la conversación. --}}
                        <button type="button"
                                class="wa-clavo {{ $c->fijada() ? 'on' : '' }}"
                                wire:click.stop="fijar({{ $c->id }})"
                                aria-pressed="{{ $c->fijada() ? 'true' : 'false' }}"
                                aria-label="{{ $c->fijada() ? 'Soltar' : 'Fijar arriba' }} el chat de {{ $c->titulo() }}"
                                title="{{ $c->fijada() ? 'Soltar este chat de arriba' : 'Fijar este chat arriba' }}"><span aria-hidden="true">📌</span></button>
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
                </div>
            @empty
                <div style="padding:24px 14px;color:var(--wa-suave);font-size:13px;text-align:center">
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
                {{-- El de volver va PRIMERO y se queda clavado a la izquierda
                     (ver .wa-atras en el CSS). La fila se desliza; este no.
                     Era el único botón del que dependés para salir, y se
                     perdía de vista justo cuando estabas corriendo la fila
                     para buscar una pestaña.

                     La flecha ← de antes era el carácter de texto pelado: se
                     ve de un grosor distinto en cada teléfono y queda flaca al
                     lado de los emojis. Esta es dibujada, con las puntas
                     redondeadas, y hereda el color y el grosor que le
                     pongamos. --}}
                <button type="button" class="wa-volver wa-atras" wire:click="cerrarChat"
                        aria-label="Volver a la lista de conversaciones"
                        title="Volver a la lista"><svg viewBox="0 0 24 24" width="21" height="21"
                        fill="none" stroke="currentColor" stroke-width="2.3"
                        stroke-linecap="round" stroke-linejoin="round"
                        aria-hidden="true"><path d="M15 5 8 12l7 7"/></svg></button>

                {{-- Sin botón para entrar al pedido: se llega procesando una
                     orden del chat, que es el único camino que tiene sentido.
                     El de volver solo aparece cuando estás adentro. --}}
                @if($pestana === 'pedido')
                    <button type="button" class="wa-tab on" wire:click="verPestana('chat')"
                            title="Volver a la conversación">💬</button>
                @endif

                {{-- Acá iba un botón de pantalla completa. Se quitó cuando el
                     cuadro de escribir empezó a encogerse solo y el chat a
                     anclarse con el teclado: el espacio que el botón peleaba ya
                     lo tenemos sin pedirlo. --}}

                {{-- Fijar va acá y no en la lista: cada fila de la lista ya es
                     un botón entero, y un botón adentro de otro no se puede.
                     Acá además se fija el chat que estás leyendo, que es el
                     momento en que uno se da cuenta de que lo quiere a mano. --}}
                {{-- aria-label y no solo title: el title lo lee el mouse, no el
                     lector de pantalla ni el teléfono. aria-pressed dice si
                     está puesto, que es media información del botón. --}}
                <button type="button" class="wa-volver wa-fijar {{ $conv->fijada() ? 'on' : '' }}"
                        wire:click="fijar({{ $conv->id }})"
                        aria-pressed="{{ $conv->fijada() ? 'true' : 'false' }}"
                        aria-label="{{ $conv->fijada() ? 'Soltar este chat de arriba de la lista' : 'Fijar este chat arriba de la lista' }}"
                        title="{{ $conv->fijada() ? 'Soltar este chat de arriba' : 'Fijar este chat arriba de la lista' }}"><span aria-hidden="true">📌</span></button>

                {{-- Copiar el número en 4x4, con el espacio en medio.
                     Así es exactamente como quedó guardado en Sistrack —el
                     Excel lo manda así en la columna NOMBRE— y pegándolo con
                     el mismo formato el buscador de ellos lo encuentra de una.
                     Sin el espacio no calza y hay que ponerlo a mano. --}}
                <button type="button" class="wa-volver wa-copia"
                        data-copiar="{{ $conv->telefonoLegible() }}"
                        onclick="waCopiar(this)"
                        aria-label="Copiar el número {{ $conv->telefonoLegible() }}"
                        title="Copiar el número del cliente"><span aria-hidden="true">📋</span></button>

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

                    <div style="font-size:12px;color:var(--wa-suave)">
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
                    {{-- "Orden de envío" no entra en la cabecera del teléfono y
                         quedaba cortado contra el borde. En pantalla chica dice
                         solo "Orden"; el ícono ya cuenta el resto. --}}
                    <x-filament::button size="xs" color="success" wire:click="plantillaOrden"
                        icon="heroicon-m-clipboard-document-list">
                        <span class="wa-t-largo">Orden de envío</span>
                        <span class="wa-t-corto">Orden</span>
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

            {{-- ═══ La ficha del cliente ═══
                 El panel ya sabía la dirección y cuántas veces te compró, pero
                 solo lo mostraba en la pestaña de pedido. Para leerlo había que
                 salir de la conversación, mirar, y volver: dos toques y perder
                 de vista lo que el cliente está escribiendo, decenas de veces
                 al día.

                 Va en un renglón. Lo justo para contestar "sí, a la misma
                 dirección" sin moverse de acá; tocándolo se abre completa.

                 Alpine y no Livewire: abrir y cerrar no necesita ir al
                 servidor, y así responde en el acto. --}}
            @php $ficha = $this->clienteConocido(); @endphp

            @if($pestana === 'chat' && $ficha && (filled($ficha['direccion'] ?? null) || ($ficha['veces'] ?? 0) > 1))
                <div class="wa-ficha" x-data="{ abierta: false }">
                    <button type="button" class="wa-ficha-top"
                            @click="abierta = !abierta"
                            :aria-expanded="abierta"
                            aria-label="Ver los datos guardados de este cliente">
                        <span class="wa-ficha-res">
                            @if(filled($ficha['municipio'] ?? null))
                                <b>📍 {{ $ficha['municipio'] }}@if(filled($ficha['departamento'] ?? null)), {{ $ficha['departamento'] }}@endif</b>
                            @elseif(filled($ficha['direccion'] ?? null))
                                <b>📍 Tiene dirección guardada</b>
                            @endif

                            @if(($ficha['veces'] ?? 0) > 1)
                                <span class="wa-ficha-veces">{{ $ficha['veces'] }}ª compra</span>
                            @endif
                        </span>

                        <span class="wa-ficha-flecha" x-text="abierta ? '⌃' : '⌄'" aria-hidden="true"></span>
                    </button>

                    <div class="wa-ficha-mas" x-show="abierta" x-cloak>
                        @if(filled($ficha['nombre'] ?? null))
                            <div class="wa-ficha-fila">
                                <span>Nombre</span><b>{{ $ficha['nombre'] }}</b>
                            </div>
                        @endif

                        @if(filled($ficha['direccion'] ?? null))
                            <div class="wa-ficha-fila">
                                <span>Dirección</span>
                                <b>{{ $ficha['direccion'] }}</b>
                            </div>

                            {{-- Copiar: casi siempre esto se va a pegar en la
                                 orden de envío o en la guía. --}}
                            <button type="button" class="wa-ficha-copiar"
                                    data-copiar="{{ trim($ficha['direccion'] . ', ' . ($ficha['municipio'] ?? '') . ', ' . ($ficha['departamento'] ?? ''), ' ,') }}"
                                    onclick="waCopiar(this)">
                                📋 Copiar la dirección completa
                            </button>
                        @endif

                        <div class="wa-ficha-ojo">
                            Son los datos de la última entrega. Preguntale si siguen buenos
                            antes de mandar.
                        </div>
                    </div>
                </div>
            @endif

            @if($pestana === 'chat')
            <div class="wa-chat" data-conv="{{ $abierta }}">
                @php $diaAnterior = null; @endphp

                @forelse($this->mensajes() as $m)
                    {{-- La fecha va en una separación entre días, no pegada a
                         cada globo. Repetirla en los veinte mensajes de un
                         mismo día es ruido: lo que uno necesita saber es dónde
                         empieza cada día, y eso se ve una sola vez.

                         Es como lo hace WhatsApp, y no por copiarlo: en una
                         conversación larga la fecha solo importa cuando
                         cambia. --}}
                    @if($m->diaClave() !== $diaAnterior)
                        @php $diaAnterior = $m->diaClave(); @endphp
                        <div class="wa-dia"><span>{{ $m->diaLegible() }}</span></div>
                    @endif

                    @php
                        $clase = $m->esDelCliente() ? 'wa-suyo'
                               : ($m->estado === 'fallido' ? 'wa-mal'
                               : ($m->automatico ? 'wa-auto' : 'wa-mio'));

                        // Todavía en camino: se dibuja apagado hasta que Meta
                        // confirme. Es el globo que aparece al instante.
                        if ($m->estado === 'enviando') $clase .= ' wa-yendo';
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
                        @elseif($m->url())<a href="{{ $m->url() }}" target="_blank" rel="noopener"><img src="{{ $m->url() }}" alt="Imagen del cliente" loading="lazy" decoding="async"></a>@elseif($m->tipo === 'image' && filled($m->media_id))<button type="button" class="wa-bajar" wire:click="bajarImagen({{ $m->id }})" wire:loading.attr="disabled">🖼️ Ver la imagen</button>@elseif($m->tipo !== 'text')<i style="opacity:.7">[{{ $m->tipo }}]</i>@endif

                        {{-- El texto va en su propio elemento y pegado a las llaves:
                             el globo respeta los saltos de línea, así que cualquier
                             espacio o sangría de la plantilla se dibujaría tal cual. --}}
                        {{-- Va sin escapar porque textoHtml() ya escapó todo a
                             mano antes de armar los enlaces. Ver el comentario
                             del método: el orden es escapar y después enlazar. --}}
                        @if(filled($m->texto))<div class="wa-txt">{!! $m->textoHtml() !!}</div>@endif

                        <div class="wa-pie">
                            @php $deQuien = $m->esDelCliente() ? 'del cliente' : 'tuyo'; @endphp

                            @if(filled($m->wa_message_id))
                                <button type="button" class="wa-resp-btn"
                                        wire:click="responderA({{ $m->id }})"
                                        aria-label="Responder a este mensaje {{ $deQuien }} de las {{ $m->hora() }}"
                                        title="Responder a este mensaje"><span aria-hidden="true">↩</span></button>
                            @endif

                            @if(filled($m->texto))
                                {{-- Copiar: el texto viaja en un atributo, no en el
                                     JavaScript, así Livewire lo refresca solo cuando
                                     el mensaje cambia. --}}
                                <button type="button" class="wa-resp-btn"
                                        data-copiar="{{ $m->texto }}"
                                        onclick="waCopiar(this)"
                                        aria-label="Copiar el texto de este mensaje"
                                        title="Copiar este texto"><span aria-hidden="true">📋</span></button>

                                {{-- Traerlo al cuadro de escribir. Es lo que uno
                                     quiere de verdad cuando se le fue una letra:
                                     corregir y mandar de nuevo. --}}
                                <button type="button" class="wa-resp-btn"
                                        wire:click="reusar({{ $m->id }})"
                                        aria-label="Traer este texto al cuadro para corregirlo y mandarlo de nuevo"
                                        title="Traerlo abajo para corregirlo y mandarlo de nuevo"><span aria-hidden="true">✏️</span></button>
                            @endif

                            {{-- La fecha completa en el title: la separación de
                                 arriba dice de qué día es, pero si estás a
                                 mitad de una conversación larga ya la pasaste.
                                 Con esto se consulta sin subir. --}}
                            <span title="{{ $m->fechaYHora() }}">{{ $m->hora() }}</span>
                            @if(! $m->esDelCliente())
                                · <b style="color:{{ $m->colorFirma() }}">{{ $m->firma() }}</b>
                                <span class="wa-check" style="color:{{ $m->colorEstado() }}"
                                      title="{{ $m->queSignifica() }}">{{ $m->marcaEstado() }}</span>
                            @endif
                        </div>

                        {{-- Alineado a la IZQUIERDA y con quiebre de palabra.
                             El pie normal va a la derecha porque ahí solo hay
                             una hora y tres íconos; un motivo de error es una
                             frase, y a la derecha se lee mal y se corta contra
                             el borde del globo. --}}
                        @if($m->estado === 'fallido' && $m->error)
                            <div class="wa-pie" style="opacity:1;text-align:left;
                                        line-height:1.4;margin-top:5px;
                                        white-space:normal;overflow-wrap:anywhere">⚠ {{ $m->error }}</div>
                        @endif

                        {{-- Si el mensaje parece una orden de envío, se puede
                             procesar sin salir de acá. --}}
                        @if(\App\Services\Etiquetado::pareceOrden($m->texto))
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

            <div class="wa-abajo {{ $cajaGrande ? 'wa--caja-grande' : '' }} {{ trim($texto) !== '' ? 'wa--con-texto' : '' }}">
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

                    {{-- La foto que viene con una respuesta rápida. Espera acá
                         hasta que le des a Enviar, para que puedas corregir el
                         texto antes: sale como una sola imagen con ese texto de
                         pie, no como dos mensajes sueltos. --}}
                    @php $pendiente = $this->fotoPendiente(); @endphp
                    @if($pendiente)
                        <div class="wa-citando" style="border-left-color:#4aa3df">
                            <img src="{{ $pendiente->url() }}" alt="" class="wa-pend-img">
                            <div style="flex:1;min-width:0">
                                <div class="wa-cita-q">📷 Va con esta foto</div>
                                <div class="wa-citando-x">
                                    Lo que escribas abajo sale de pie de la imagen
                                </div>
                            </div>
                            <button type="button" class="wa-mini" wire:click="quitarFotoPendiente"
                                    title="Mandar solo el texto, sin la foto">✕</button>
                        </div>
                    @endif

                    {{-- El atajo mira si hay Shift: sin él manda, con él deja
                         saltar de línea. Antes se bloqueaba cualquier Enter y
                         no se podía escribir un mensaje de dos renglones. --}}
                    {{-- Abrir y cerrar el cuadro a lo alto. Se abre solo cuando
                         cae un texto largo —una respuesta rápida, una mejora—
                         porque ahí es cuando hace falta leerlo entero. --}}
                    <button type="button" class="wa-agrandar" wire:click="alternarCaja"
                            title="{{ $cajaGrande ? 'Achicar el cuadro' : 'Agrandar el cuadro para leer todo' }}">
                        {{ $cajaGrande ? '⌃ Achicar' : '⌄ Ver todo el texto' }}
                    </button>

                    {{-- Escribir "/" abre las respuestas rápidas, como en la app
                         de WhatsApp Business. Se filtra tecleando: "/cob" deja
                         solo cobertura. Es más rápido que buscar el botón entre
                         diez, y no ocupa lugar en pantalla.

                         Solo cuenta si la barra es lo ÚNICO escrito y todavía no
                         hay espacios. Así un enlace con barras —o una fracción—
                         nunca dispara la lista. --}}
                    @php
                        $paraSlash = $resp->map(fn ($r) => [
                            'id'     => $r->id,
                            'titulo' => $r->titulo,
                            'previa' => \Illuminate\Support\Str::limit(strip_tags($r->texto), 70),
                            'foto'   => $r->tieneFoto(),
                        ])->values();
                    @endphp

                    <div class="wa-slash-wrap"
                         x-data="{
                             abierto: false,
                             termino: '',
                             lista: @js($paraSlash),
                             limpio(s) {
                                 return (s || '').toString().toLowerCase()
                                     .normalize('NFD').replace(/[̀-ͯ]/g, '');
                             },
                             get filtradas() {
                                 const t = this.limpio(this.termino);
                                 if (t === '') return this.lista;
                                 return this.lista.filter(r => this.limpio(r.titulo).includes(t));
                             },
                             alEscribir(valor) {
                                 const m = (valor || '').match(/^\/(\S*)$/);
                                 if (m) { this.termino = m[1]; this.abierto = true; }
                                 else   { this.abierto = false; this.termino = ''; }
                             },
                             elegir(r) {
                                 this.abierto = false;
                                 this.termino = '';
                                 $wire.usarRespuesta(r.id);
                             }
                         }"
                         x-on:click.outside="abierto = false">

                        <div class="wa-slash" x-show="abierto" x-cloak>
                            <div class="wa-slash-t">Respuestas rápidas</div>

                            <template x-for="r in filtradas" :key="r.id">
                                <button type="button" class="wa-slash-op" x-on:click="elegir(r)">
                                    <b><span x-show="r.foto">📷 </span><span x-text="r.titulo"></span></b>
                                    <span x-text="r.previa"></span>
                                </button>
                            </template>

                            <div class="wa-slash-nada" x-show="filtradas.length === 0">
                                Ninguna se llama así. Borrá la barra para seguir escribiendo.
                            </div>
                        </div>

                        <textarea class="wa-escribir" rows="2" wire:model="texto"
                                  placeholder="Escribí tu respuesta… o / para las rápidas"
                                  spellcheck="true" lang="es"
                                  x-on:input="alEscribir($event.target.value)"
                                  x-on:keydown.escape="abierto = false"
                                  x-on:keydown.enter="
                                      if (abierto && filtradas.length) {
                                          $event.preventDefault();
                                          elegir(filtradas[0]);
                                          return;
                                      }
                                      if (! $event.shiftKey && window.innerWidth > 900) {
                                          $event.preventDefault();
                                          $wire.enviar();
                                      }
                                  "></textarea>
                    </div>

                    <div class="wa-btns">
                        <x-filament::button size="sm" wire:click="enviar" icon="heroicon-m-paper-airplane">
                            Enviar
                        </x-filament::button>

                        {{-- Mandar una foto de la computadora o del teléfono.

                             Hasta ahora solo salían fotos YA cargadas en el
                             admin: las del catálogo y las de las respuestas
                             rápidas. Eso sirve para lo que se repite, y no
                             sirve para lo que pasa una vez.

                             Es un <label> con el input escondido adentro, no un
                             botón: así el toque abre el selector de archivos
                             directo, sin JavaScript de por medio. En el
                             teléfono eso además ofrece la cámara.

                             accept="image/*" y capture ausente a propósito: con
                             capture forzaría la cámara y no dejaría elegir una
                             foto de la galería, que es lo que más vas a hacer. --}}
                        <label class="wa-chip wa-foto-btn"
                               wire:loading.class="wa-foto-btn--yendo" wire:target="fotoSuelta"
                               title="Mandar una foto desde este aparato">
                            <span wire:loading.remove wire:target="fotoSuelta">📎 Foto</span>
                            <span wire:loading wire:target="fotoSuelta">Subiendo…</span>

                            <input type="file" accept="image/*" wire:model="fotoSuelta"
                                   style="display:none"
                                   aria-label="Elegir una foto para mandar al cliente">
                        </label>

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

                        {{-- Acá iba "📏 Tabla de tallas". Se quitó del renglón de
                             botones: el método mandarTallas() sigue vivo y la
                             respuesta automática de tallas también, así que
                             volver a ponerlo es agregar el botón otra vez. --}}

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
                                @if($r->tieneFoto())📷 @endif{{ $r->titulo }}
                            </button>
                        @endforeach

                        {{-- En el teléfono el Enter salta línea siempre, así que
                             esta ayuda solo aplica en la computadora. --}}
                        <span class="wa-t-largo" style="font-size:11.5px;color:var(--wa-suave)">
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
            <div class="wa-panel">

                {{-- Los dos cuadros de comparar, arriba y uno al lado del otro.
                     Comparar ES el trabajo: si hay que subir y bajar la pantalla
                     para hacerlo, se deja de hacer y por ahí se cuelan los
                     errores. Abajo quedan los campos para escribir. --}}
                <div class="wa-arriba {{ filled($pedOrigen) ? '' : 'wa-arriba-sola' }}">
                    @if(filled($pedOrigen))
                        <div class="wa-origen">
                            <div class="wa-origen-t">
                                <span>📦 La orden</span>
                                <button type="button" class="wa-mini" wire:click="limpiarPedido">Descartar</button>
                            </div>
                            <div class="wa-origen-x">{{ $pedOrigen }}</div>
                        </div>
                    @endif

                    <div class="wa-previa">
                        <div class="wa-previa-t">📄 Como va en la guía</div>

                        <div class="wa-pv"><span>Nombre</span><b>{{ $pedNombre ?: '—' }}</b></div>
                        <div class="wa-pv"><span>Teléfono</span><b>{{ $pedTelefono ?: '—' }}</b></div>
                        @if(trim($pedTelefonoRecibe) !== '')
                            <div class="wa-pv"><span>Llamar a</span><b>{{ $pedTelefonoRecibe }}</b></div>
                        @endif
                        <div class="wa-pv"><span>Dirección</span><b>{{ $pedDireccion ?: '—' }}</b></div>
                        <div class="wa-pv"><span>Municipio</span><b>{{ $pedMunicipio ?: '—' }}</b></div>
                        <div class="wa-pv"><span>Departamento</span><b>{{ $pedDepartamento ?: '—' }}</b></div>
                        <div class="wa-pv"><span>Contenido</span><b>{{ $this->descripcionPedido() ?: '—' }}</b></div>
                        <div class="wa-pv"><span>Cobrar</span><b>${{ number_format($this->totalPedido(), 2) }}</b></div>
                    </div>
                </div>

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

                <div class="wa-campo">
                    <label class="wa-lab">Teléfono</label>
                    <input type="text" class="wa-in" wire:model="pedTelefono">
                </div>

                {{-- Departamento primero y municipio después, los dos de lista.
                     Escribiéndolo a mano se puede formar una pareja imposible
                     —"San Vicente, San Salvador"— y ese paquete se va al otro
                     lado del país. Eligiendo, esa pareja no se puede ni armar:
                     la lista de municipios solo trae los del departamento. --}}
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

                @php $susMunicipios = $this->municipiosDelDepartamento(); @endphp

                {{-- Buscador de municipio: la lista entera a la vista Y un campo
                     para escribir y filtrarla.

                     Ni el desplegable cerrado ni el campo de texto pelado
                     servían. El primero obliga a buscar con el dedo entre veinte;
                     el segundo no deja ver qué hay. Acá se abre la lista al
                     tocarlo, y si escribís tres letras se reduce a lo que calza.

                     El filtrado es en el navegador, sin ir al servidor: por eso
                     responde con cada tecla. --}}

                {{-- wire:key con el departamento adentro. NO es decorativo:
                     era el motivo de que no se pudiera elegir San Salvador.

                     La lista de municipios se le entrega a Alpine dentro del
                     x-data, y Alpine lee el x-data UNA SOLA VEZ, cuando nace
                     el elemento. Después Livewire puede redibujar el atributo
                     todas las veces que quiera: Alpine ya no lo vuelve a
                     mirar.

                     Entonces pasaba esto: el formulario abría con el
                     departamento del pedido ANTERIOR ya puesto, y la lista
                     nacía con los municipios de ese departamento. Cambiabas a
                     San Salvador, Livewire actualizaba todo lo de abajo... y
                     la lista seguía siendo la vieja. San Salvador no estaba
                     ahí, así que el buscador contestaba "no hay ninguno que se
                     llame así" — y no había forma de elegirlo.

                     Con la llave, al cambiar de departamento Livewire da el
                     elemento por otro, Alpine lo arma de nuevo, y la lista
                     nace con los municipios que corresponden. --}}
                <div class="wa-campo wa-busca"
                     wire:key="muni-{{ $pedDepartamento ?: 'todos' }}"
                     x-data="{
                         abierto: false,
                         buscar: @js($pedMunicipio),

                         /* El mapa entero, una sola vez. La lista que toca NO
                            se calcula en el servidor y se congela acá dentro
                            —eso era el error— sino que se elige del mapa
                            leyendo el departamento de Livewire. Como $wire es
                            reactivo, al cambiarlo esta lista cambia sola. */
                         porDepto: @js($this->municipiosPorDepartamento()),
                         todos: @js($this->todosLosMunicipios()),

                         get opciones() {
                             const d = $wire.pedDepartamento;
                             return (d && this.porDepto[d]) ? this.porDepto[d] : this.todos;
                         },

                         limpio(s) {
                             return (s || '').toString().toLowerCase()
                                 .normalize('NFD').replace(/[̀-ͯ]/g, '');
                         },
                         get filtradas() {
                             const t = this.limpio(this.buscar).trim();
                             if (t === '') return this.opciones;
                             return this.opciones.filter(o => this.limpio(o).includes(t));
                         },
                         elegir(m) {
                             this.buscar = m;
                             this.abierto = false;
                             $wire.set('pedMunicipio', m);
                         }
                     }"
                     x-on:click.outside="abierto = false">

                    <label class="wa-lab">Municipio</label>

                    <input type="text" class="wa-in" x-model="buscar"
                           x-on:focus="abierto = true"
                           x-on:input="abierto = true"
                           x-on:keydown.escape="abierto = false"
                           x-on:keydown.enter.prevent="filtradas.length && elegir(filtradas[0])"
                           x-on:blur="$wire.set('pedMunicipio', buscar)"
                           placeholder="Tocá para ver la lista, o escribí para buscar"
                           autocomplete="off">

                    <div class="wa-opciones" x-show="abierto" x-cloak>
                        <template x-for="m in filtradas" :key="m">
                            <button type="button" class="wa-opcion"
                                    x-text="m"
                                    x-on:click="elegir(m)"></button>
                        </template>

                        <div class="wa-opcion-nada" x-show="filtradas.length === 0">
                            No hay ninguno que se llame así.
                        </div>
                    </div>

                    <div class="wa-ayuda">
                        @if(! empty($susMunicipios))
                            Los {{ count($susMunicipios) }} de {{ $pedDepartamento }}.
                        @else
                            Todos. Al elegir uno, el departamento se completa solo.
                        @endif
                    </div>
                </div>

                {{-- El segundo teléfono: solo hace falta cuando el pedido va
                     para otra persona. Va debajo y no al lado del primero, para
                     que no se confundan al llenarlos de prisa. --}}
                <div class="wa-campo">
                    <label class="wa-lab">Teléfono de quien recibe (opcional)</label>
                    <input type="text" class="wa-in" wire:model.live.debounce.500ms="pedTelefonoRecibe"
                           placeholder="Solo si el paquete va para otra persona">
                    <div class="wa-ayuda">
                        Es al que llama el repartidor. Vacío, se usa el del cliente.
                    </div>
                </div>

                <div class="wa-campo">
                    <label class="wa-lab">Dirección exacta</label>
                    <textarea class="wa-in" rows="2" wire:model="pedDireccion"></textarea>
                </div>

                {{-- El departamento ya se eligió arriba, antes del municipio. --}}

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

                {{-- ── Los renglones del pedido, uno por uno ──────────────────
                     Acá estaba el agujero: las líneas se llenaban al procesar
                     la orden pero NO se mostraban en ninguna parte. Solo se
                     veía el texto y el total, así que no había forma de mirar
                     renglón por renglón si el precio era el que se cotizó.

                     El precio que se muestra es el del CHAT cuando se habló
                     uno, porque eso es lo que el cliente espera pagar. Cuando
                     no se habló, sale el del catálogo y se dice que viene de
                     ahí. Si los dos existen y no coinciden, se ven los dos. --}}
                <div class="wa-campo">
                    <label class="wa-lab">Lo que lleva, renglón por renglón</label>

                    @forelse($pedLineas as $i => $l)
                        @php
                            $precioUsa = $this->precioLinea($l);
                            $precioCat = $this->precioCatalogo($l);
                            $delChat   = trim((string) ($l['precio'] ?? '')) !== '';
                            $difiere   = $delChat && $precioCat > 0 && abs($precioUsa - $precioCat) >= 0.01;
                        @endphp

                        <div wire:key="linea-{{ $i }}" style="margin-bottom:10px">
                            <div class="wa-linea-4">
                                <div class="wa-sel">
                                    <select class="wa-in" wire:model.live="pedLineas.{{ $i }}.size_id">
                                        <option value="">Elegí el producto…</option>
                                        @foreach($this->opcionesProductos() as $id => $et)
                                            <option value="{{ $id }}">{{ $et }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <input type="text" inputmode="numeric" class="wa-in"
                                       wire:model.live="pedLineas.{{ $i }}.cantidad"
                                       aria-label="Cantidad">

                                <input type="text" inputmode="decimal" class="wa-in"
                                       wire:model.live="pedLineas.{{ $i }}.precio"
                                       placeholder="{{ $precioCat > 0 ? number_format($precioCat, 2) : 'c/u' }}"
                                       aria-label="Precio de cada uno">

                                <button type="button" class="wa-x" wire:click="quitarLinea({{ $i }})"
                                        aria-label="Quitar este renglón">✕</button>
                            </div>

                            {{-- De dónde salió el precio y cuánto suma. Sin esto
                                 un número suelto no dice si lo cotizaste vos o
                                 lo puso el sistema. --}}
                            @if($precioUsa > 0)
                                <div class="wa-linea-pie">
                                    @if($difiere)
                                        <span style="color:#d4a017;font-weight:700">
                                            ⚠ ${{ number_format($precioUsa, 2) }} del chat
                                        </span>
                                        · el catálogo dice ${{ number_format($precioCat, 2) }}
                                    @elseif($delChat)
                                        ${{ number_format($precioUsa, 2) }} c/u, del chat
                                    @else
                                        ${{ number_format($precioUsa, 2) }} c/u, del catálogo
                                    @endif
                                    · suma
                                    <b>${{ number_format($precioUsa * max(1, (int) ($l['cantidad'] ?? 1)), 2) }}</b>
                                </div>
                            @endif
                        </div>
                    @empty
                        <div class="wa-ayuda" style="margin-bottom:8px">
                            Todavía no hay renglones. Se llenan solos al procesar una orden
                            del chat, o los agregás acá abajo.
                        </div>
                    @endforelse

                    <button type="button" class="wa-mini" wire:click="agregarLinea">
                        + Agregar renglón
                    </button>
                </div>

                <div class="wa-campo">
                    <label class="wa-lab">Productos, tal como van en la guía</label>
                    <textarea class="wa-in" rows="3" wire:model.live="pedProductosTexto"
                              placeholder="Se llena solo al procesar una orden"></textarea>
                    <div class="wa-ayuda">
                        Este es el texto que ve el repartidor. Los renglones de arriba son
                        para la cuenta.
                    </div>
                </div>

                <div class="wa-sep"></div>

                <div class="wa-campo">
                    <label class="wa-lab">A cobrar</label>
                    <input type="text" class="wa-in" wire:model.live="pedCobrarManual"
                           placeholder="Sale de la orden">
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
                <div style="font-size:13px;color:var(--wa-suave);margin-bottom:12px">
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
                <div style="font-size:13.5px;color:var(--wa-suave);line-height:1.6;padding:10px 0">
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

                <span style="font-size:11.5px;color:var(--wa-suave)">
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
                    <div style="font-size:13px;color:var(--wa-suave);margin-bottom:12px">
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
                    <div style="font-size:13.5px;color:var(--wa-suave);line-height:1.6">
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

                <div style="font-size:13px;color:var(--wa-suave);margin-bottom:12px">
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
                        <b>También las fotos reales de cómo queda puesto</b>
                        @if($usos)
                            <span style="color:var(--wa-suave)">· {{ $usos }} {{ $usos == 1 ? 'foto' : 'fotos' }} más, de esta talla</span>
                        @else
                            <span style="color:var(--wa-suave)">· esta talla no tiene fotos cargadas</span>
                        @endif
                    </span>
                </label>

                <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap">
                    <x-filament::button wire:click="enviarElegidas" icon="heroicon-m-paper-airplane">
                        Mandar {{ count($elegidas) + ($conFotosUso ? $usos : 0) }}
                        {{ (count($elegidas) + ($conFotosUso ? $usos : 0)) == 1 ? 'foto' : 'fotos' }}
                    </x-filament::button>

                    <span style="font-size:11.5px;color:var(--wa-suave)">
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
    var waProbarSonido;

    (function () {
        var ultimoN   = null;   // cuántos sin leer había la vez anterior
        var ultimoId  = null;   // el id del último mensaje entrante
        var tituloBase = document.title;
        var audio = null;

        // El navegador no deja sonar nada hasta que la persona toca algo.
        //
        // Antes esto se enganchaba con {once:true}: se preparaba en el primer
        // toque y listo. El problema es que un contexto de audio se puede
        // SUSPENDER solo más adelante —cuando la pestaña pasa a segundo plano
        // un rato largo, que es exactamente lo que hacés vos— y ahí quedaba
        // suspendido para siempre, porque el enganche ya se había gastado.
        //
        // Ahora escucha siempre y lo despierta en cada toque. Cuesta nada.
        function prepararAudio() {
            try {
                if (!audio) {
                    var AC = window.AudioContext || window.webkitAudioContext;
                    if (AC) audio = new AC();
                }
                if (audio && audio.state === 'suspended') audio.resume();
            } catch (e) {}
        }

        document.addEventListener('click', prepararAudio);
        document.addEventListener('touchstart', prepararAudio, { passive: true });
        document.addEventListener('keydown', prepararAudio);

        // Al volver a la pestaña, despertarlo sin esperar a que toques algo.
        document.addEventListener('visibilitychange', function () {
            if (!document.hidden) prepararAudio();
        });

        /**
         * El timbre.
         *
         * Cambió por completo. El de antes eran dos notas de onda senoidal a
         * volumen 0.25: la senoidal es la onda más limpia y más blanda que
         * existe, y en un cuarto con ruido, o con el teléfono en la mesa, se
         * pierde. Sonaba, pero no llamaba.
         *
         * Este son tres notas que SUBEN, repetidas dos veces, con onda
         * triangular —que tiene armónicos y por eso se abre paso— y al doble
         * de volumen. Subir es lo que hace que se oiga como un llamado y no
         * como un clic del sistema.
         *
         * Y lo importante: antes se hacía "resume()" y se programaban las
         * notas en el mismo renglón. resume() tarda, así que las notas
         * quedaban programadas contra un reloj todavía detenido y muchas veces
         * no sonaba nada. Ahora se espera a que despierte y recién ahí se
         * programan.
         */
        function sonar() {
            if (!audio) { prepararAudio(); }
            if (!audio) return;

            var tocar = function () {
                try {
                    var t0 = audio.currentTime + 0.02;

                    // Sol - Do - Mi, y otra vez. Un arpegio que sube.
                    var notas = [
                        [784,  0.00], [1046, 0.11], [1318, 0.22],
                        [784,  0.42], [1046, 0.53], [1318, 0.64],
                    ];

                    notas.forEach(function (n) {
                        var osc = audio.createOscillator();
                        var vol = audio.createGain();

                        osc.type = 'triangle';
                        osc.frequency.value = n[0];

                        var t = t0 + n[1];

                        vol.gain.setValueAtTime(0.0001, t);
                        vol.gain.exponentialRampToValueAtTime(0.5, t + 0.015);
                        vol.gain.exponentialRampToValueAtTime(0.0001, t + 0.16);

                        osc.connect(vol);
                        vol.connect(audio.destination);

                        osc.start(t);
                        osc.stop(t + 0.18);
                    });
                } catch (e) {}
            };

            try {
                if (audio.state === 'suspended') {
                    // resume() devuelve una promesa: hay que esperarla. En
                    // navegadores viejos no la devuelve, y ahí se toca igual.
                    var p = audio.resume();
                    p && p.then ? p.then(tocar).catch(function () {}) : tocar();
                } else {
                    tocar();
                }
            } catch (e) {}
        }

        // Para el botón de probar: suena y además vibra, si el teléfono deja.
        waProbarSonido = function () {
            prepararAudio();
            sonar();
            try { if (navigator.vibrate) navigator.vibrate([90, 60, 90]); } catch (e) {}
        };

        function avisar(cuantos) {
            sonar();

            if (!('Notification' in window) || Notification.permission !== 'granted') return;

            try {
                var n = new Notification('Baby-Confort · mensajes', {
                    // Puede ser 0: pasa cuando el mensaje entra en el chat que
                    // tenés abierto, que se marca como leído en el acto. Ahí
                    // "Tenés 0 sin leer" sería absurdo.
                    body: cuantos === 0
                        ? 'Entró un mensaje nuevo'
                        : (cuantos === 1
                            ? 'Tenés 1 conversación sin leer'
                            : 'Tenés ' + cuantos + ' conversaciones sin leer'),
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

            var ahora = parseInt(marca.getAttribute('data-n')   || '0', 10);
            var id    = parseInt(marca.getAttribute('data-ult') || '0', 10);

            // El título de la pestaña, para verlo sin cambiar de ventana.
            document.title = ahora > 0 ? '(' + ahora + ') ' + tituloBase : tituloBase;

            // La primera vuelta solo toma nota: no suena al abrir el panel.
            if (ultimoId === null) { ultimoId = id; ultimoN = ahora; return; }

            /*
             * El disparador es el ID, no el contador.
             *
             * Contar conversaciones sin leer fallaba justo cuando más falta
             * hace. Si tenés el chat abierto, ese chat se marca como leído y
             * el número NO sube: entraba el mensaje y no sonaba nada. Lo mismo
             * si el mismo cliente manda tres seguidos — es una sola
             * conversación, el contador no se mueve.
             *
             * Un id siempre crece. Si es mayor que el de la vuelta anterior,
             * entró algo, y punto.
             */
            if (id > ultimoId) avisar(ahora);

            ultimoId = id;
            ultimoN  = ahora;
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

        /*
         * Si estabas mirando el final, guardado APARTE del elemento.
         *
         * Acá estaba el problema de la orden de envío. Al mandarla, el panel
         * vuelve de la pestaña del pedido a la del chat, y Livewire no
         * remienda la conversación: la construye de nuevo. El elemento que
         * aparece es otro, y uno recién nacido tiene scrollTop en 0.
         *
         * La cuenta de abajo entonces miraba ese 0 y concluía "está leyendo
         * arriba, no lo muevas". Y ahí te dejaba: al principio de la
         * conversación, con toda la historia por delante.
         *
         * Con el dato guardado afuera, da igual que el elemento se vaya: la
         * intención tuya sobrevive al redibujado.
         */
        var estabaAbajo = true;

        var CERCA = 140;   // px de tolerancia para considerarte "al final"

        function alFondo(forzar) {
            var chat = document.querySelector('.wa-chat');
            if (!chat) return;

            var conv = chat.getAttribute('data-conv');

            // ¿Es el mismo elemento de antes o uno recién creado?
            // La marca se pega al nodo: si Livewire lo reemplaza, el nuevo
            // viene sin ella. Es la única forma de enterarse.
            var nuevo = !chat.waVisto;
            chat.waVisto = true;

            // Conversación recién abierta: siempre al último mensaje.
            if (conv !== ultimaConv) {
                ultimaConv = conv;
                ultimoAlto = chat.scrollHeight;
                chat.scrollTop = chat.scrollHeight;
                estabaAbajo = true;
                return;
            }

            // Misma conversación, elemento nuevo. Su scrollTop es 0 y no
            // quiere decir nada, así que no se le pregunta a él: se usa lo
            // que se venía recordando.
            if (nuevo) {
                ultimoAlto = chat.scrollHeight;
                if (estabaAbajo || forzar) chat.scrollTop = chat.scrollHeight;
                return;
            }

            var crecio = chat.scrollHeight > ultimoAlto;
            ultimoAlto = chat.scrollHeight;

            // 140 px de tolerancia: si estabas cerca del final, se considera
            // que querés seguir la conversación.
            var cerca = (chat.scrollHeight - chat.scrollTop - chat.clientHeight) < CERCA;
            estabaAbajo = cerca;

            if (forzar || (crecio && cerca)) chat.scrollTop = chat.scrollHeight;
        }

        /*
         * Tomar nota cada vez que desplazás la conversación.
         *
         * Sin esto, "estabaAbajo" solo se actualizaría cada tres segundos, en
         * el refresco. Si subís a leer algo viejo y justo entra un mensaje
         * antes del siguiente refresco, el panel creería que seguís abajo y
         * te arrastraría.
         *
         * Solo anota. No mueve nada ni toca el teclado — ese manejador ya se
         * probó y se quitó: el chat también se desplaza por su cuenta al
         * enfocar el cuadro, y no hay forma de distinguir eso de tu dedo.
         * Anotar es inofensivo; actuar no lo era.
         *
         * En captura porque el evento "scroll" no burbujea.
         */
        document.addEventListener('scroll', function (e) {
            var t = e.target;
            if (!t || !t.classList || !t.classList.contains('wa-chat')) return;

            estabaAbajo = (t.scrollHeight - t.scrollTop - t.clientHeight) < CERCA;
        }, true);

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

        // Si el teclado ya estaba abierto en la vuelta anterior. Sirve para
        // hacer una sola vez lo que no debe repetirse en cada evento.
        var teclaAntes = false;

        /**
         * Cierra el hueco entre la barra gris y el panel, y devuelve dónde
         * queda el panel dentro de lo que se ve.
         *
         * Se MIDE en vez de suponerse. Los contenedores de Filament tienen sus
         * propios márgenes, cambian entre versiones y sus nombres de clase no
         * son promesa de nadie: intentar apagarlos uno por uno fue lo que dejó
         * esa franja negra arriba y abajo. Midiendo, da igual quién la ponga.
         */
        function pegarArriba() {
            // Se vuelve a buscar SIEMPRE, no se confía en la guardada.
            //
            // Acá estaba el problema de la franja al abrir un chat: el elemento
            // se guardaba una sola vez al cargar la página. Al abrir una
            // conversación, Livewire redibuja y puede reemplazarlo — y a partir
            // de ahí todas las correcciones se le aplicaban al elemento viejo,
            // que ya no está en la pantalla. En la lista se veía bien porque
            // ahí la corrección se había aplicado antes del reemplazo.
            caja = document.querySelector('.wa');

            if (!caja) return 0;

            // Se borra lo puesto antes para medir el hueco de verdad y no el
            // que quedó después de la última corrección.
            caja.style.marginTop = '';

            var arriba = caja.getBoundingClientRect().top;

            // Se mide contra el MENÚ, no contra la barra que lo contiene.
            // Si la barra tiene relleno de sobra debajo del menú, ese relleno
            // es parte del problema: midiendo la barra entera, ese espacio
            // queda del lado de "esto ya está ocupado" y nunca se cierra.
            var barra = document.querySelector('.fi-topbar nav')
                     || document.querySelector('.fi-topbar');

            var finBarra = barra ? barra.getBoundingClientRect().bottom : 0;

            var hueco = Math.round(arriba - finBarra);

            if (hueco > 0) {
                caja.style.marginTop = (-hueco) + 'px';
                return finBarra;
            }

            return arriba;
        }

        function ajustar() {
            if (!caja) caja = document.querySelector('.wa');

            if (window.innerWidth > 900) {
                document.documentElement.style.removeProperty('--wa-alto');
                document.documentElement.classList.remove('wa--teclado');
                if (caja) caja.style.marginTop = '';
                teclaAntes = false;
                return;
            }

            // ¿Está el teclado abierto?
            //
            // La medición sola no alcanza. La forma clásica es comparar la
            // ventana visual contra la de la página, pero a esta página le
            // pusimos "interactive-widget=resizes-content" en el viewport para
            // que el teclado no tape el cuadro de escribir — y con eso las DOS
            // se achican a la vez. La resta da casi cero y el teclado quedaba
            // sin detectar, justo en los teléfonos donde mejor funciona todo lo
            // demás.
            //
            // Lo que sí es infalible: si el cursor está adentro del cuadro de
            // escribir, el teclado está abierto. No hay caso en que no.
            var foco = document.activeElement;
            var escribiendo = !!(foco && foco.closest && foco.closest('.wa-abajo'));

            var teclado = escribiendo || (window.innerHeight - vv.height) > 120;

            // Con el teclado abierto el espacio es de verdad poco, y el CSS
            // necesita saberlo para achicar el cuadro de escribir y guardar
            // los botones en un solo renglón. Si no, entre el teclado y el
            // cuadro lleno no queda sitio para leer lo que dijo el cliente.
            document.documentElement.classList.toggle('wa--teclado', teclado);

            if (teclado) {
                // Acá había un lazo que hacía bailar la pantalla.
                //
                // El panel se clavaba con position:fixed y se le movía el
                // "top" al ritmo de vv.offsetTop, en cada evento. Pero mover un
                // elemento clavado cambia la altura de la página, la página se
                // desplaza, offsetTop cambia, y se lo volvía a mover. Ese ida y
                // vuelta es el sube y baja.
                //
                // Ahora el panel no se clava ni se le toca el top. Se le da el
                // alto de lo que se ve y se traba el desplazamiento de la
                // página: si no hay adónde moverse, no hay nada que rebote.
                // Una sola vez al abrirse, no en cada evento: si se llamara
                // siempre, volvería a pelear con el navegador.
                if (!teclaAntes) {
                    try { window.scrollTo(0, 0); } catch (e) {}
                }
            }

            // El alto sale de restar: lo que se ve, menos dónde empieza el
            // panel. Así no queda franja abajo aunque arriba cambie algo.
            var arriba = pegarArriba();
            var alto = Math.round(vv.height - arriba);

            document.documentElement.style.setProperty(
                '--wa-alto',
                Math.max(alto, 240) + 'px'
            );

            teclaAntes = teclado;
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

            window.Livewire.hook('morph.updated', function () {
                ajustar();

                // Y otra vez un instante después. Al abrir una conversación
                // cambia todo el lado derecho —cabecera, ficha del cliente,
                // globos— y el alto definitivo no está listo en el mismo
                // momento del redibujado. Sin esta segunda pasada, la medición
                // se hace sobre una pantalla a medio armar.
                setTimeout(ajustar, 60);
            });

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

        // Y al salir del cuadro hay que devolver todo a su lugar, si no la
        // pantalla se queda encogida con el teclado ya cerrado.
        document.addEventListener('focusout', function (e) {
            if (!e.target.closest || !e.target.closest('.wa-abajo')) return;

            // Un rato de gracia: al tocar "Enviar" el foco sale del cuadro por
            // un instante y vuelve. Sin esta espera, la pantalla pega un salto.
            setTimeout(ajustar, 250);
        });
    })();

    /*
     * Tocar la conversación suelta el cuadro de escribir.
     *
     * Con eso se cierra el teclado y el cuadro se encoge a un renglón, que es
     * lo que uno quiere cuando deja de escribir y se pone a leer. Volver a
     * tocar el cuadro lo estira a tres. El CSS hace el resto: acá solo se
     * quita el cursor de donde estaba.
     *
     * No se suelta si el toque fue sobre algo que hace falta usar —un botón,
     * un enlace, el reproductor de un audio—: ahí el toque tiene su propio
     * trabajo y quitarle el foco al cuadro estorbaría.
     */
    (function () {
        function soltarTeclado(e) {
            if (window.innerWidth > 900) return;

            var t = e.target;
            if (!t || !t.closest) return;

            // Se suelta al tocar CUALQUIER cosa que no sea la zona de escribir.
            // Antes solo miraba .wa-chat, y por eso tocar la cabecera, una
            // etiqueta o el espacio entre globos no cerraba el teclado.
            if (t.closest('.wa-abajo')) return;

            // Salvo lo que tiene trabajo propio: un botón, un enlace, el
            // reproductor de un audio. Ahí cerrar el teclado estorbaría.
            if (t.closest('button, a, audio, input, textarea, select, label')) return;

            var foco = document.activeElement;
            if (foco && foco.blur && foco.closest && foco.closest('.wa-abajo')) {
                foco.blur();
            }
        }

        // Los dos eventos: pointerdown no llega en todos los Android cuando el
        // gesto empieza como desplazamiento, y touchstart sí.
        document.addEventListener('pointerdown', soltarTeclado, true);
        document.addEventListener('touchstart', soltarTeclado, { capture: true, passive: true });

        // Acá había un tercer manejador que soltaba el teclado al desplazar la
        // conversación. Se quitó porque impedía abrirlo: al enfocar el cuadro,
        // el panel baja el chat al último mensaje, ese desplazamiento es del
        // programa y no del dedo, y el manejador lo tomaba como que el usuario
        // se había puesto a leer. Resultado: el teclado se cerraba en el mismo
        // instante en que se abría.
        //
        // No hay forma confiable de distinguir un desplazamiento del dedo de
        // uno hecho por código, así que no vuelve. Tocar fuera del cuadro
        // alcanza.
    })();

    /*
     * Copia el texto de un globo al portapapeles.
     *
     * Va por dos caminos porque el bueno (navigator.clipboard) solo existe en
     * páginas seguras y en navegadores nuevos. El viejo, con un campo escondido
     * y execCommand, funciona en cualquier Android por antiguo que sea.
     *
     * El botón contesta con un ✓ un segundo: sin eso uno no sabe si copió, y
     * termina tocándolo tres veces.
     */
    function waCopiar(boton) {
        var texto = boton.getAttribute('data-copiar') || '';
        if (!texto) return;

        function avisar(bien) {
            var antes = boton.textContent;
            boton.textContent = bien ? '✓' : '✕';
            boton.style.opacity = '1';
            setTimeout(function () {
                boton.textContent = antes;
                boton.style.opacity = '';
            }, 1000);
        }

        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(texto)
                .then(function () { avisar(true); })
                .catch(function () { avisar(alaAntigua(texto)); });
            return;
        }

        avisar(alaAntigua(texto));
    }

    function alaAntigua(texto) {
        try {
            var campo = document.createElement('textarea');
            campo.value = texto;
            // Fuera de la vista pero dentro de la página: si estuviera en
            // display:none no se puede seleccionar, y sin selección no copia.
            campo.setAttribute('readonly', '');
            campo.style.position = 'fixed';
            campo.style.top = '-1000px';
            campo.style.opacity = '0';
            document.body.appendChild(campo);

            campo.select();
            campo.setSelectionRange(0, texto.length);

            var salio = document.execCommand('copy');
            document.body.removeChild(campo);

            return salio;
        } catch (e) {
            return false;
        }
    }

</script>

</x-filament-panels::page>
