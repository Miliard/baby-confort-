<div x-data="{ copiado: false }">
    <textarea readonly x-ref="ta" rows="5"
        style="width:100%;padding:12px;border:1px solid #e2e8ee;border-radius:10px;font-size:14px;line-height:1.5;color:#2b3a4a;background:#f7fbfe;resize:vertical">{{ $msg }}</textarea>

    <div style="display:flex;gap:10px;margin-top:12px;flex-wrap:wrap">
        <button type="button"
            @click="navigator.clipboard.writeText($refs.ta.value); copiado = true; setTimeout(() => copiado = false, 1600)"
            style="background:#2e9e6b;color:#fff;border:none;border-radius:10px;padding:11px 20px;font-weight:700;cursor:pointer;font-size:14px">
            <span x-text="copiado ? '¡Copiado! ✔' : 'Copiar mensaje'"></span>
        </button>
        <a href="{{ $wa }}" target="_blank" rel="noopener"
            style="background:#25D366;color:#fff;text-decoration:none;border-radius:10px;padding:11px 20px;font-weight:700;font-size:14px">
            Abrir WhatsApp
        </a>
    </div>

    <p style="color:#6b7c8c;font-size:12.5px;margin-top:12px;line-height:1.5">
        Copia el mensaje y pégalo en el chat de WhatsApp que ya tienes abierto con el cliente — así no se abre otra pestaña.
    </p>
</div>
