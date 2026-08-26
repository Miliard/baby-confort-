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
