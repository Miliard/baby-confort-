{{-- Subidor de fotos de etiquetas: lee el QR de cada una y las guarda. --}}
<p class="mb-4 text-sm text-gray-500 dark:text-gray-400">
    Subí las fotos de las etiquetas. Se lee el <span class="font-semibold">código QR</span> de cada una
    para saber a qué guía pertenece, y la foto queda visible para el cliente en su enlace de seguimiento.
</p>

{{-- Camino de respaldo, arriba de todo: si la subida en lote se traba, este
     formulario simple no depende de nada que se pueda trabar. --}}
<a href="{{ route('fotos.simple') }}"
   class="mb-4 flex items-center justify-between gap-3 rounded-xl border border-warning-300 bg-warning-50 p-4 dark:border-warning-500/40 dark:bg-warning-500/10">
    <span>
        <span class="block text-sm font-bold text-warning-700 dark:text-warning-400">
            ¿Se traba al subir? Usá la página simple
        </span>
        <span class="block text-xs text-warning-700/80 dark:text-warning-400/80">
            Una foto a la vez, escribiendo la guía a mano. Sin lectura de QR: no se puede trabar.
        </span>
    </span>
    <span class="flex-none text-lg text-warning-600">→</span>
</a>

<label for="fotos-input"
    class="block cursor-pointer rounded-xl border-2 border-dashed border-gray-300 bg-gray-50 p-6 text-center transition hover:border-primary-500 dark:border-white/20 dark:bg-white/5">
    <div class="text-4xl leading-none">📷</div>
    <div class="mt-2 text-sm font-bold text-gray-950 dark:text-white">Elegir fotos de las etiquetas</div>
    <div class="text-xs text-gray-500 dark:text-gray-400">Podés elegir varias a la vez</div>
</label>
<input id="fotos-input" type="file" accept="image/*" multiple class="hidden">

{{-- Barra de progreso mientras se suben --}}
<div id="progreso-caja" class="mt-4 hidden">
    <div class="mb-1.5 flex justify-between text-sm font-bold text-gray-950 dark:text-white">
        <span id="progreso-texto">Procesando…</span>
        <span id="progreso-num" class="font-medium text-gray-500 dark:text-gray-400"></span>
    </div>
    <div class="h-2.5 overflow-hidden rounded-full bg-gray-200 dark:bg-white/10">
        <div id="progreso-barra" class="h-full w-0 rounded-full bg-primary-600 transition-all duration-300"></div>
    </div>
    <div id="progreso-detalle" class="mt-2 text-xs text-gray-500 dark:text-gray-400"></div>
</div>

{{-- Solo se listan las que NO se pudieron leer, para escribir la guía a mano --}}
<div id="resultados" class="mt-3 flex flex-col gap-2"></div>

{{-- Autodiagnóstico: prueba el circuito completo sin que tengas que elegir
     ninguna foto. Dice en una línea si el problema es el servidor, el
     navegador, o si en realidad todo funciona y hay que mirar otra cosa. --}}
<div class="mt-5 rounded-xl border border-gray-200 p-4 dark:border-white/10">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <div class="text-sm font-bold text-gray-950 dark:text-white">¿Las fotos no suben?</div>
            <div class="text-xs text-gray-500 dark:text-gray-400">
                Versión del código: <b>{{ \App\Http\Controllers\GuiaFotoController::VERSION }}</b>
            </div>
        </div>
        <button type="button" id="bc-probar" onclick="bcProbarSubida(this)"
            class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-bold text-white">
            🩺 Probar ahora
        </button>
    </div>
    <div id="bc-probar-salida" class="mt-3 text-xs leading-relaxed text-gray-500 dark:text-gray-400">
        Crea una imagen de prueba y la manda al servidor. No toca tus guías.
    </div>
</div>

<script>
// Prueba de punta a punta: genera la imagen, la achica igual que las de verdad
// y la manda. Después borra sola la guía de prueba.
window.bcProbarSubida = async function (boton) {
    const salida = document.getElementById('bc-probar-salida');
    const token  = document.querySelector('meta[name="csrf-token"]')?.content;
    const pasos  = [];

    const pintar = (color) => {
        salida.innerHTML = pasos.join('<br>');
        salida.style.color = color || '';
    };

    boton.disabled = true;
    pasos.push('⏳ Probando…'); pintar();

    try {
        pasos[0] = '① Navegador: ' + (navigator.userAgent.slice(0, 60)) + '…';

        const c = document.createElement('canvas');
        c.width = 1200; c.height = 900;
        const x = c.getContext('2d');
        x.fillStyle = '#4aa3df'; x.fillRect(0, 0, 1200, 900);
        x.fillStyle = '#fff'; x.font = '60px sans-serif';
        x.fillText('PRUEBA', 60, 460);

        const blob = await new Promise(r => c.toBlob(r, 'image/jpeg', 0.85));
        if (!blob) throw new Error('el navegador no pudo crear la imagen');
        pasos.push('② Imagen creada y comprimida: ' + Math.round(blob.size / 1024) + ' KB ✅');
        pintar();

        const guia = '9' + String(Date.now()).slice(-6);
        const fd = new FormData();
        fd.append('guia', guia);
        fd.append('foto', blob, 'prueba.jpg');

        const res = await fetch(@js(route('fotos.subir')), {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json' },
            body: fd,
        });
        const crudo = await res.text();
        let data = null;
        try { data = JSON.parse(crudo); } catch (e) {}

        if (!data || !data.ok) {
            pasos.push('③ El servidor NO aceptó la foto ❌');
            pasos.push('&nbsp;&nbsp;&nbsp;Código ' + res.status + ' · ' +
                (data ? (data.error || data.message || 'sin motivo') : crudo.slice(0, 120)));
            pasos.push('<b>Mandale esta pantalla a Claude.</b>');
            pintar('#dc2626');
            boton.disabled = false;
            return;
        }

        pasos.push('③ El servidor la aceptó y la guardó ✅');

        pasos.push('<b style="color:#059669">Todo el circuito funciona.</b> ' +
                   'Si aun así una foto tuya no sube, es por esa foto en concreto ' +
                   '(formato o tamaño), no por el sistema.');
        pasos.push('<span style="color:#94a3b8">Guía de prueba creada: ' + guia +
                   ' — borrala desde “Guías con foto”.</span>');
        pintar();
    } catch (e) {
        pasos.push('❌ Falló en el navegador: ' + e.message);
        pasos.push('<b>Mandale esta pantalla a Claude.</b>');
        pintar('#dc2626');
    }

    boton.disabled = false;
};
</script>

{{-- Cuánto ocupan las fotos y hasta cuándo se guardan --}}
@php $espacio = \App\Http\Controllers\GuiaFotoController::espacioUsado(); @endphp
<div class="mt-5 rounded-xl border border-gray-200 bg-gray-50 p-4 text-sm dark:border-white/10 dark:bg-white/5">
    <div class="flex items-center justify-between gap-3">
        <span class="text-gray-500 dark:text-gray-400">Fotos guardadas ahora</span>
        <span class="font-bold text-gray-950 dark:text-white">
            {{ $espacio['archivos'] }} · {{ $espacio['legible'] }}
        </span>
    </div>
    <p class="mt-2 text-xs leading-relaxed text-gray-500 dark:text-gray-400">
        Cada foto se guarda <b>{{ $espacio['dias'] }} días</b> y después se borra sola del disco.
        Los datos del pedido (guía, cliente, teléfono y qué llevaba) <b>no se borran nunca</b>:
        el rastreo y el ranking de productos siguen funcionando aunque la imagen ya no esté.
    </p>
</div>

<div class="mt-5 text-center">
    <x-filament::link :href="\App\Filament\Resources\GuiaFotoResource::getUrl()" icon="heroicon-m-photo">
        Ver todas las guías con foto
    </x-filament::link>
</div>
