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

{{-- ─────────── MANDARLE LA FOTO AL CLIENTE ───────────
     La foto de la etiqueta es el comprobante de que el paquete existe y va en
     camino. Hoy el cliente la ve solo si entra a su enlace de rastreo, y la
     mayoría no entra.

     Acá se emparejan solas —la guía sale del QR, y con la guía viene el
     teléfono— pero NO se mandan solas. Primero se miran. Una foto pegada a la
     guía equivocada le llega a un cliente real y no hay cómo sacarla. --}}
@php $paraChat = $this->fotosParaChat(); @endphp

@if(count($paraChat))
    @php
        $listas = collect($paraChat)->where('estado', \App\Services\FotosAlChat::LISTA);
    @endphp

    <div class="mt-5 rounded-xl border border-gray-200 bg-white p-4 dark:border-white/10 dark:bg-gray-900">

        <div class="mb-3 flex items-baseline justify-between gap-2">
            <span class="text-sm font-bold text-gray-950 dark:text-white">
                📤 Mandarles la foto por el chat
            </span>
            <span class="text-xs text-gray-500 dark:text-gray-400">
                {{ count($paraChat) }} sin mandar
            </span>
        </div>

        {{-- El botón va ARRIBA de la lista, igual que el de bajar el lote:
             al final habría que recorrer veinte renglones para llegar. --}}
        @if($listas->count())
            <x-filament::button wire:click="mandarFotosAlChat" size="lg"
                icon="heroicon-m-paper-airplane"
                wire:confirm="Se le va a mandar la foto de su paquete a {{ $listas->count() }} {{ $listas->count() === 1 ? 'cliente' : 'clientes' }}. Esto no se puede deshacer. ¿Seguimos?"
                class="w-full justify-center">
                Mandar las {{ $listas->count() }} que se pueden
            </x-filament::button>
        @else
            <p class="rounded-xl border border-dashed border-gray-300 p-3 text-center text-xs text-gray-500 dark:border-white/10 dark:text-gray-400">
                Ninguna se puede mandar ahora. Abajo dice por qué en cada una.
            </p>
        @endif

        <div class="mt-3 space-y-2">
            @foreach($paraChat as $f)
                @php
                    $foto   = $f['foto'];
                    $estado = $f['estado'];

                    [$color, $etiqueta] = match ($estado) {
                        \App\Services\FotosAlChat::LISTA    => ['success', 'lista'],
                        \App\Services\FotosAlChat::ESPERA   => ['warning', 'esperando que escriba'],
                        \App\Services\FotosAlChat::SIN_CHAT => ['gray',    'sin chat'],
                        default                             => ['danger',  'sin teléfono'],
                    };
                @endphp

                <div wire:key="fchat-{{ $foto->id }}"
                     class="flex items-start gap-3 rounded-xl border border-gray-200 p-2.5 dark:border-white/10">

                    {{-- La miniatura no es decoración: es como se ve de un
                         vistazo que la foto y el cliente van juntos. --}}
                    @if($foto->url())
                        <a href="{{ $foto->url() }}" target="_blank" rel="noopener" class="flex-none">
                            <img src="{{ $foto->url() }}" alt=""
                                 class="h-14 w-14 rounded-lg object-cover">
                        </a>
                    @endif

                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-1.5">
                            <span class="font-mono text-sm font-bold text-primary-600 dark:text-primary-400">
                                {{ $foto->telefono ?: 'sin número' }}
                            </span>
                            <x-filament::badge :color="$color" size="xs">{{ $etiqueta }}</x-filament::badge>
                        </div>

                        <div class="truncate text-sm font-semibold text-gray-950 dark:text-white">
                            {{ $foto->nombre ?: '—' }}
                        </div>

                        @if(filled($foto->guia))
                            <p class="text-xs text-gray-500 dark:text-gray-400">Guía {{ $foto->guia }}</p>
                        @endif

                        <p class="mt-1 text-xs leading-snug text-gray-500 dark:text-gray-400">
                            {{ $f['porque'] }}
                        </p>

                        @if(filled($foto->chat_error))
                            <p class="mt-1 text-xs font-semibold text-danger-600 dark:text-danger-400">
                                No salió: {{ $foto->chat_error }}
                            </p>
                        @endif
                    </div>

                    {{-- Sacarla de la lista sin mandarla. Hace falta sobre todo
                         para las que no tienen chat: si no hay cómo quitarlas,
                         se quedan ahí para siempre y la lista deja de servir
                         para saber qué falta. --}}
                    <x-filament::icon-button
                        icon="heroicon-m-x-mark" color="gray" size="sm"
                        wire:click="omitirFotoChat({{ $foto->id }})"
                        wire:confirm="Quitarla de la lista sin mandarla. ¿Seguro?"
                        label="Quitar de la lista sin mandar" />
                </div>
            @endforeach
        </div>

        <p class="mt-3 text-xs leading-relaxed text-gray-500 dark:text-gray-400">
            Las que dicen <b>esperando que escriba</b> no se pierden: WhatsApp solo deja
            mandar mensajes dentro de las 24 horas siguientes al último del cliente.
            En cuanto vuelva a escribir, aparece acá como lista.
        </p>
    </div>
@endif

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

{{-- La lista de guías solo existe en el panel de administración. Desde el
     panel de mensajes hay que pedir la dirección apuntando a 'admin' a mano, y
     a los colaboradores de solo chat ni se les muestra: no pueden entrar. --}}
@php
    $urlGuias = null;

    if (! (auth()->user()?->solo_chat ?? false)) {
        try {
            $urlGuias = \App\Filament\Resources\GuiaFotoResource::getUrl('index', [], true, 'admin');
        } catch (\Throwable $e) {
            $urlGuias = null;
        }
    }
@endphp

@if($urlGuias)
    <div class="mt-5 text-center">
        <x-filament::link :href="$urlGuias" icon="heroicon-m-photo">
            Ver todas las guías con foto
        </x-filament::link>
    </div>
@endif
