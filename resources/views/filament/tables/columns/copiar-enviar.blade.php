@php
    $record  = $getRecord();
    $texto   = $record->mensajeParaCliente();
    $enviado = (bool) $record->enviado_at;
@endphp

<div class="px-3 py-4">
    <button
        type="button"
        x-data
        @click="
            (async () => {
                try {
                    await navigator.clipboard.writeText(@js($texto));
                } catch (e) {
                    const ta = document.createElement('textarea');
                    ta.value = @js($texto);
                    ta.style.position = 'fixed'; ta.style.top = '-1000px';
                    document.body.appendChild(ta); ta.select();
                    try { document.execCommand('copy'); } catch (err) {}
                    ta.remove();
                }
                $wire.marcarEnviado({{ $record->id }});
            })()
        "
        title="Un clic: copia el mensaje y lo marca como enviado"
        style="
            display:inline-flex;align-items:center;gap:6px;white-space:nowrap;cursor:pointer;
            border-radius:8px;padding:6px 12px;font-weight:700;font-size:12.5px;border:1px solid;
            {{ $enviado
                ? 'background:#dcfce7;border-color:#86efac;color:#15803d;'
                : 'background:#fee2e2;border-color:#fca5a5;color:#b91c1c;' }}
        "
    >
        {{ $enviado ? '✓ Enviado' : '📋 Copiar y enviar' }}
    </button>
</div>
