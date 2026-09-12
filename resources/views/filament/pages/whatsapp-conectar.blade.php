<x-filament-panels::page>

<style>
    .wc-caja{background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:18px 20px;
             max-width:720px;line-height:1.6}
    html.dark .wc-caja{background:#16202f;border-color:rgba(255,255,255,.10)}

    .wc-aviso{border-radius:11px;padding:12px 15px;font-size:13.5px;margin-bottom:16px;line-height:1.6;
              max-width:720px}
    .wc-ok{background:rgba(46,158,107,.13);border:1px solid #2e9e6b;color:#15603f}
    .wc-mal{background:rgba(229,105,95,.14);border:1px solid #e5695f;color:#b91c1c}
    .wc-ojo{background:rgba(234,179,8,.14);border:1px solid #d4a017;color:#7a5600}
    html.dark .wc-ok{color:#9fe1cb} html.dark .wc-mal{color:#f5c4b3} html.dark .wc-ojo{color:#f0d79a}

    .wc-dato{display:flex;gap:10px;padding:7px 0;border-bottom:1px solid rgba(120,140,170,.16);
             font-size:13.5px;flex-wrap:wrap}
    .wc-dato:last-child{border-bottom:none}
    .wc-et{color:#94a3b8;min-width:190px}
    .wc-val{font-weight:700;font-family:ui-monospace,Menlo,Consolas,monospace}

    .wc-pasos{margin:14px 0 0;padding-left:20px;font-size:13.5px}
    .wc-pasos li{margin-bottom:7px}

    .wc-btn{background:#2e9e6b;color:#fff;border:none;border-radius:11px;padding:13px 22px;
            font-size:15px;font-weight:700;cursor:pointer;font-family:inherit}
    .wc-btn:hover{background:#28885c}
    .wc-btn:disabled{opacity:.55;cursor:not-allowed}

    .wc-estado{margin-top:13px;font-size:13.5px;min-height:20px}
</style>

@if(! $this->listoParaConectar())
    <div class="wc-aviso wc-mal">
        <b>Falta configuración.</b> Para que el botón funcione hacen falta el identificador
        de la aplicación, el del ajuste de registro insertado y la clave secreta
        (<code>WHATSAPP_APP_SECRET</code> en Railway). Sin la clave secreta, Meta no
        entrega el identificador de acceso.
    </div>
@endif

@if($this->conectado())
    <div class="wc-aviso wc-ok">
        <b>Ya está conectado.</b> No hace falta volver a tocar nada acá.
        Solo si cambiás de número o Meta corta la conexión.
    </div>

    <div class="wc-caja" style="margin-bottom:18px">
        <div class="wc-dato"><span class="wc-et">Número</span>
            <span class="wc-val">{{ $this->numero() ?: '—' }}</span></div>
        <div class="wc-dato"><span class="wc-et">Identificador del número</span>
            <span class="wc-val">{{ $this->phoneId() ?: '—' }}</span></div>
        <div class="wc-dato"><span class="wc-et">Cuenta de WhatsApp Business</span>
            <span class="wc-val">{{ $this->wabaId() ?: '—' }}</span></div>
        <div class="wc-dato"><span class="wc-et">Conectado el</span>
            <span class="wc-val">{{ $this->conectadoEl() ?: '—' }}</span></div>
    </div>
@endif

<div class="wc-aviso wc-ojo">
    <b>Antes de apretar, dos cosas que importan.</b>
    <ul class="wc-pasos" style="margin-top:8px">
        <li>Cuando Meta te ofrezca conectar el número, elegí siempre el <b>código QR</b>.
            Ese camino deja el número funcionando en tu teléfono igual que ahora.</li>
        <li>Si en algún momento te ofrece <b>verificar por SMS</b>, no sigas: esa vía se
            lleva el número del teléfono. Cerrá la ventana y avisame.</li>
    </ul>
</div>

<div class="wc-caja">
    <b style="font-size:15px">Conectar el número del negocio</b>
    <ol class="wc-pasos">
        <li>Tocá el botón verde. Se abre una ventana de Meta.</li>
        <li>Entrá con tu cuenta de Facebook, la misma del negocio.</li>
        <li>Elegí la cuenta <b>baby confort</b> y el número <b>+503 6860 1764</b>.</li>
        <li>Escaneá el código QR con tu WhatsApp Business, desde el teléfono.</li>
        <li>Esperá a que la ventana se cierre sola.</li>
    </ol>

    <div style="margin-top:18px">
        <button type="button" class="wc-btn" id="wcBoton"
            @if(! $this->listoParaConectar()) disabled @endif>
            {{ $this->conectado() ? 'Volver a conectar' : 'Conectar mi WhatsApp' }}
        </button>
        <div class="wc-estado" id="wcEstado"></div>
    </div>
</div>

@if($this->listoParaConectar())
<script>
(function () {
    var APP_ID    = '{{ $this->appId() }}';
    var CONFIG_ID = '{{ $this->configId() }}';
    var DESTINO   = '{{ route('whatsapp.conectar') }}';
    var CSRF      = '{{ csrf_token() }}';

    var boton  = document.getElementById('wcBoton');
    var estado = document.getElementById('wcEstado');

    // Lo que Meta va soltando por el camino: el número y la cuenta.
    var sesion = null;

    function decir(texto, color) {
        estado.textContent = texto;
        estado.style.color = color || '#94a3b8';
    }

    // ── El SDK de Meta ───────────────────────────────────────────────────────
    window.fbAsyncInit = function () {
        FB.init({ appId: APP_ID, autoLogAppEvents: true, xfbml: true, version: 'v21.0' });
    };

    (function (d, s, id) {
        var js, fjs = d.getElementsByTagName(s)[0];
        if (d.getElementById(id)) return;
        js = d.createElement(s); js.id = id;
        js.src = 'https://connect.facebook.net/es_LA/sdk.js';
        fjs.parentNode.insertBefore(js, fjs);
    }(document, 'script', 'facebook-jssdk'));

    // ── Meta habla con la página por mensajes ────────────────────────────────
    window.addEventListener('message', function (evento) {
        if (!evento.origin || evento.origin.indexOf('facebook.com') === -1) return;

        var datos;
        try { datos = JSON.parse(evento.data); } catch (e) { return; }
        if (!datos || datos.type !== 'WA_EMBEDDED_SIGNUP') return;

        // Meta usa un nombre distinto según por dónde entró. El de la app de
        // WhatsApp Business (el del código QR) devuelve solo la cuenta, sin el
        // número: eso se resuelve después, del lado del servidor.
        if (datos.event === 'FINISH'
            || datos.event === 'FINISH_ONLY_WABA'
            || datos.event === 'FINISH_WHATSAPP_BUSINESS_APP_ONBOARDING') {
            sesion = datos.data || null;
        } else if (datos.event === 'CANCEL') {
            decir('Cerraste la ventana antes de terminar. No se guardó nada.', '#b45309');
        } else if (datos.event === 'ERROR') {
            var m = (datos.data && datos.data.error_message) ? datos.data.error_message : 'error desconocido';
            decir('Meta devolvió un error: ' + m, '#b91c1c');
        }
    });

    // ── El botón ─────────────────────────────────────────────────────────────
    boton.addEventListener('click', function () {
        if (typeof FB === 'undefined') {
            decir('El SDK de Meta todavía no cargó. Esperá unos segundos y probá otra vez.', '#b45309');
            return;
        }

        sesion = null;
        decir('Abriendo la ventana de Meta…');

        FB.login(function (respuesta) {
            var codigo = respuesta && respuesta.authResponse && respuesta.authResponse.code;

            if (!codigo) {
                decir('No se completó la conexión. Si cerraste la ventana, volvé a intentar.', '#b45309');
                return;
            }
            if (!sesion || !sesion.waba_id) {
                decir('Meta no devolvió la cuenta. Probá de nuevo y asegurate de llegar hasta el final.', '#b45309');
                return;
            }

            // El número puede venir o no. Si no viene, el servidor lo busca.
            guardar(codigo, sesion.waba_id, sesion.phone_number_id || '');
        }, {
            config_id: CONFIG_ID,
            response_type: 'code',
            override_default_response_type: true,
            extras: {
                setup: {},
                sessionInfoVersion: '3'
            }
        });
    });

    function guardar(codigo, wabaId, phoneId) {
        decir('Guardando la conexión…');
        boton.disabled = true;

        fetch(DESTINO, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': CSRF,
                'Accept': 'application/json'
            },
            body: JSON.stringify({ code: codigo, waba_id: wabaId, phone_number_id: phoneId })
        })
        .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, cuerpo: j }; }); })
        .then(function (r) {
            boton.disabled = false;

            if (!r.ok || !r.cuerpo.ok) {
                decir(r.cuerpo.error || 'No se pudo guardar la conexión.', '#b91c1c');
                return;
            }
            if (r.cuerpo.aviso) {
                decir(r.cuerpo.aviso, '#b45309');
                return;
            }

            decir('Conectado: ' + (r.cuerpo.numero || 'listo') + '. Recargando…', '#15603f');
            setTimeout(function () { window.location.reload(); }, 1600);
        })
        .catch(function () {
            boton.disabled = false;
            decir('Se cortó la conexión con el servidor. Probá de nuevo.', '#b91c1c');
        });
    }
})();
</script>
@endif

</x-filament-panels::page>
