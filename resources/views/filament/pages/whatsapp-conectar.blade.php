<x-filament-panels::page>

<style>
    .wc-caja{background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:18px 20px;
             max-width:760px;line-height:1.6;margin-bottom:16px}
    html.dark .wc-caja{background:#16202f;border-color:rgba(255,255,255,.10)}

    .wc-aviso{border-radius:11px;padding:12px 15px;font-size:13.5px;margin-bottom:16px;
              line-height:1.6;max-width:760px}
    .wc-ok{background:rgba(46,158,107,.13);border:1px solid #2e9e6b;color:#15603f}
    .wc-mal{background:rgba(229,105,95,.14);border:1px solid #e5695f;color:#b91c1c}
    html.dark .wc-ok{color:#9fe1cb} html.dark .wc-mal{color:#f5c4b3}

    .wc-dato{display:flex;gap:10px;padding:7px 0;border-bottom:1px solid rgba(120,140,170,.16);
             font-size:13.5px;flex-wrap:wrap}
    .wc-dato:last-child{border-bottom:none}
    .wc-et{color:#94a3b8;min-width:210px}
    .wc-val{font-weight:700;font-family:ui-monospace,Menlo,Consolas,monospace;word-break:break-all}

    .wc-pasos{margin:12px 0 0;padding-left:20px;font-size:13.5px}
    .wc-pasos li{margin-bottom:6px}
    .wc-btns{display:flex;gap:10px;flex-wrap:wrap;margin-top:16px;align-items:center}
</style>

@if($mensaje)
    <div class="wc-aviso {{ $tono === 'ok' ? 'wc-ok' : 'wc-mal' }}">{{ $mensaje }}</div>
@endif

@if(! $this->hayCredenciales())
    <div class="wc-aviso wc-mal">
        <b>Faltan las credenciales.</b> En Railway tienen que estar cargadas
        <code>WHATSAPP_TOKEN</code> (el identificador del usuario del sistema) y
        <code>WHATSAPP_PHONE_ID</code>. Después del deploy, recargá esta página.
    </div>
@endif

<div class="wc-caja">
    <b style="font-size:15px">Lo que hay configurado</b>
    <div style="margin-top:10px">
        <div class="wc-dato"><span class="wc-et">Identificador de acceso</span>
            <span class="wc-val">{{ filled($this->phoneId()) && $this->hayCredenciales() ? 'cargado' : 'falta' }}</span></div>
        <div class="wc-dato"><span class="wc-et">Identificador del número</span>
            <span class="wc-val">{{ $this->phoneId() ?: '—' }}</span></div>
        <div class="wc-dato"><span class="wc-et">Cuenta de WhatsApp Business</span>
            <span class="wc-val">{{ $this->wabaId() ?: '—' }}</span></div>
        <div class="wc-dato"><span class="wc-et">Número</span>
            <span class="wc-val">{{ $this->numero() ?: 'sin comprobar' }}</span></div>
        <div class="wc-dato"><span class="wc-et">Suscrita para recibir</span>
            <span class="wc-val">{{ $this->suscrita() ? 'sí · ' . ($this->conectadoEl() ?: '') : 'todavía no' }}</span></div>
    </div>
</div>

<div class="wc-caja">
    <b style="font-size:15px">Los dos pasos</b>
    <ol class="wc-pasos">
        <li><b>Probar</b> · le pregunta a Meta de quién es el número. Si contesta con
            tu número, el identificador de acceso sirve.</li>
        <li><b>Suscribir</b> · le dice a Meta que este panel atiende tu cuenta. Sin esto
            podrías enviar, pero no entraría ni un mensaje.</li>
    </ol>

    <div class="wc-btns">
        <x-filament::button wire:click="probar" color="gray" icon="heroicon-m-signal">
            Probar
        </x-filament::button>

        <x-filament::button wire:click="suscribir" color="success" icon="heroicon-m-link">
            Suscribir la app a mi cuenta
        </x-filament::button>
    </div>

    <div style="margin-top:14px;font-size:12.5px;color:#94a3b8">
        Suscribir dos veces no rompe nada: Meta simplemente vuelve a decir que sí.
    </div>
</div>

</x-filament-panels::page>
