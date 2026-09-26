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
@php
    $lotes = $this->fotosPorLote();
    $hoy   = $this->fotosDeHoy();
@endphp

{{-- El contador de control. No es adorno: si subiste diez y arriba hay ocho,
     faltan dos — y así se VE, en vez de tener que acordarse de cuántas eran.
     Una foto que desaparece sin dejar rastro es lo peor que puede pasar acá:
     el cliente se queda sin su comprobante y nadie se entera. --}}
@if($hoy['subidas'] > 0)
    <div class="mt-5 rounded-xl border border-gray-200 dark:border-white/10"
         style="display:flex;align-items:center;justify-content:space-between;
                gap:10px;padding:10px 14px">
        <span class="text-gray-500 dark:text-gray-400" style="font-size:12.5px">
            Hoy entraron <b class="text-gray-950 dark:text-white">{{ $hoy['subidas'] }}</b> fotos ·
            en la lista de abajo hay <b class="text-gray-950 dark:text-white">{{ $hoy['en_lista'] }}</b>
        </span>

        @if($hoy['en_lista'] < $hoy['subidas'])
            <x-filament::badge color="gray" size="xs">
                el resto, al final de la página ↓
            </x-filament::badge>
        @endif
    </div>
@endif

@foreach($lotes as $tanda)
    <div class="mt-5 rounded-xl border border-gray-200 bg-white dark:border-white/10 dark:bg-gray-900"
         style="padding:14px" wire:key="tanda-{{ $tanda['clave'] ?: 'sueltas' }}">

        <div style="display:flex;align-items:baseline;justify-content:space-between;
                    gap:8px;margin-bottom:12px">
            <span class="font-bold text-gray-950 dark:text-white" style="font-size:14px">
                📤 {{ $tanda['titulo'] }}
            </span>
            {{-- Limpiar la tanda entera. Va en la cabecera, junto al título de
                 SU tanda: así no hay forma de limpiar una creyendo que es
                 otra, que es el error del que estamos escapando. --}}
            <span style="display:flex;align-items:center;gap:8px;flex:none">
                <span class="text-gray-500 dark:text-gray-400" style="font-size:12px">
                    {{ count($tanda['filas']) }} sin mandar
                </span>

                <x-filament::icon-button
                    icon="heroicon-m-trash" color="gray" size="sm"
                    wire:click="limpiarLoteAlChat('{{ $tanda['clave'] }}')"
                    wire:confirm="Quitar de la lista las {{ count($tanda['filas']) }} fotos de {{ $tanda['titulo'] }}, sin mandarlas. Las fotos NO se borran: siguen en el rastreo del cliente. ¿Seguimos?"
                    label="Limpiar esta tanda de la lista, sin mandar" />
            </span>
        </div>

        {{-- El desglose, antes del botón.

             El botón solo puede decir un número —"mandar las 8"— y ese número
             deja la pregunta abierta: ¿y las otras dos? Acá está la respuesta
             antes de que la pregunta aparezca, sin tener que ir renglón por
             renglón buscando cuáles son. --}}
        @php
            $c = $tanda['cuenta'];

            $partes = [];
            if ($c[\App\Services\FotosAlChat::LISTA])      $partes[] = ['success', $c[\App\Services\FotosAlChat::LISTA] . ' listas'];
            if ($c[\App\Services\FotosAlChat::ESPERA])     $partes[] = ['warning', $c[\App\Services\FotosAlChat::ESPERA] . ' esperando que escriban'];
            if ($c[\App\Services\FotosAlChat::SIN_CHAT])   $partes[] = ['gray',    $c[\App\Services\FotosAlChat::SIN_CHAT] . ' sin chat'];
            if ($c[\App\Services\FotosAlChat::SIN_NUMERO]) $partes[] = ['danger',  $c[\App\Services\FotosAlChat::SIN_NUMERO] . ' sin teléfono'];
        @endphp

        @if(count($partes) > 1)
            <div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:10px">
                @foreach($partes as [$col, $txt])
                    <x-filament::badge :color="$col" size="xs">{{ $txt }}</x-filament::badge>
                @endforeach
            </div>
        @endif

        {{-- El botón va ARRIBA de su lista, igual que el de bajar el lote:
             al final habría que recorrer veinte renglones para llegar. --}}
        @if($tanda['listas'] > 0)
            {{-- La clave va entre comillas simples porque el atributo ya usa
                 dobles. Es la fecha y hora de la subida: no trae comillas. --}}
            {{-- Se apaga mientras trabaja. Sin esto, un segundo toque mientras
                 todavía está mandando arranca otra tanda encima. La reserva
                 en la base ya lo frena, pero mejor que ni se pueda intentar. --}}
            <x-filament::button wire:click="mandarLoteAlChat('{{ $tanda['clave'] }}')" size="lg"
                wire:loading.attr="disabled" wire:target="mandarLoteAlChat"
                icon="heroicon-m-paper-airplane"
                wire:confirm="Se le va a mandar la foto de su paquete a {{ $tanda['listas'] }} {{ $tanda['listas'] === 1 ? 'cliente' : 'clientes' }} de esta tanda. Esto no se puede deshacer. ¿Seguimos?"
                class="w-full justify-center">
                Mandar las {{ $tanda['listas'] }} que se pueden
            </x-filament::button>
        @else
            <p class="rounded-xl border border-dashed border-gray-300 text-center text-gray-500 dark:border-white/10 dark:text-gray-400"
               style="padding:12px;font-size:12px">
                Ninguna de esta tanda se puede mandar ahora. Abajo dice por qué en cada una.
            </p>
        @endif

        <div style="display:flex;flex-direction:column;gap:8px;margin-top:12px">
            @foreach($tanda['filas'] as $f)
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

                {{-- Las medidas van en style y no en clases de Tailwind.
                     Este proyecto no compila Tailwind: usa el CSS que Filament
                     ya trae hecho, así que solo existen las clases que Filament
                     usa por dentro. "h-14 w-14" no está entre ellas — por eso
                     la miniatura salió a tamaño real y dejó al texto en una
                     columna de una palabra por renglón.

                     Los colores y la tipografía sí funcionan porque Filament
                     las usa. Lo que decide el ancho, mejor escrito acá. --}}
                <div wire:key="fchat-{{ $foto->id }}"
                     class="rounded-xl border border-gray-200 dark:border-white/10"
                     style="display:flex;align-items:flex-start;gap:10px;padding:10px">

                    {{-- La miniatura no es decoración: es como se ve de un
                         vistazo que la foto y el cliente van juntos. --}}
                    @if($foto->url())
                        <a href="{{ $foto->url() }}" target="_blank" rel="noopener"
                           style="flex:none" title="Abrir la foto en grande">
                            <img src="{{ $foto->url() }}" alt=""
                                 style="width:60px;height:60px;object-fit:cover;
                                        border-radius:9px;display:block">
                        </a>
                    @endif

                    <div style="flex:1 1 auto;min-width:0">
                        <div style="display:flex;flex-wrap:wrap;align-items:center;gap:6px">
                            <span class="font-mono font-bold text-primary-600 dark:text-primary-400"
                                  style="font-size:14px">
                                {{ $foto->telefono ?: 'sin número' }}
                            </span>
                            <x-filament::badge :color="$color" size="xs">{{ $etiqueta }}</x-filament::badge>
                        </div>

                        <div class="font-semibold text-gray-950 dark:text-white"
                             style="font-size:14px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                            {{ $foto->nombre ?: '—' }}
                        </div>

                        @if(filled($foto->guia))
                            <p class="text-gray-500 dark:text-gray-400" style="font-size:12px">
                                Guía {{ $foto->guia }}
                            </p>
                        @endif

                        <p class="text-gray-500 dark:text-gray-400"
                           style="font-size:12px;line-height:1.4;margin-top:4px">
                            {{ $f['porque'] }}
                        </p>

                        @if(filled($foto->chat_error))
                            <p class="font-semibold text-danger-600 dark:text-danger-400"
                               style="font-size:12px;line-height:1.4;margin-top:4px">
                                No salió: {{ $foto->chat_error }}
                            </p>
                        @endif

                        {{-- Escribir el teléfono a mano.

                             Lo que decide si la foto se puede mandar es el
                             TELÉFONO, no el número de guía. La guía sirve para
                             el rastreo; el teléfono dice a quién mandársela.

                             Cuando el lector de la etiqueta no logra sacarlo
                             —foto movida, brillo, texto chico— esa foto queda
                             trabada y no había forma de destrabarla desde acá:
                             la única salida era volver a subir la foto.

                             También aparece en las que SÍ tienen número, para
                             corregir uno leído mal. El lector confunde un 6 con
                             un 5 y la foto le llega a otra persona. --}}
                        {{-- El campo va en TODOS los renglones, no solo en los
                             que quedaron sin teléfono.

                             El motivo es el caso peor: el lector confunde un 6
                             con un 5, ese número por casualidad tiene chat, y
                             el renglón aparece verde y "listo para mandar".
                             Ahí no hay ninguna señal de que algo esté mal, y
                             la foto de un paquete se le va a un desconocido.

                             Mostrándolo siempre, corregir cuesta lo mismo que
                             mirar. Y para las guías hechas a mano, que no
                             pasan por la cola del sistema, este campo es todo
                             lo que hace falta: con el teléfono puesto, la foto
                             sale igual que cualquier otra. --}}
                        <div style="display:flex;gap:6px;align-items:center;margin-top:7px">
                            <input type="text" inputmode="numeric" maxlength="12"
                                   wire:model.defer="telManual.{{ $foto->id }}"
                                   wire:keydown.enter="guardarTelefono({{ $foto->id }})"
                                   placeholder="{{ $foto->telefono ? 'Corregir: ' . $foto->telefono : '7055 1234' }}"
                                   aria-label="Escribir o corregir el teléfono de esta foto"
                                   class="rounded-lg border border-gray-300 dark:border-white/20"
                                   style="flex:1 1 auto;min-width:0;padding:7px 10px;
                                          font-size:14px;background:transparent;color:inherit">

                            <x-filament::button size="xs" color="gray"
                                wire:click="guardarTelefono({{ $foto->id }})">
                                Guardar
                            </x-filament::button>
                        </div>
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

        <p class="text-gray-500 dark:text-gray-400"
           style="font-size:12px;line-height:1.55;margin-top:12px">
            Las que dicen <b>esperando que escriba</b> no se pierden: WhatsApp solo deja
            mandar mensajes dentro de las 24 horas siguientes al último del cliente.
            En cuanto vuelva a escribir, aparece acá como lista.
        </p>
    </div>
@endforeach

{{-- ─────────── LAS DE HOY QUE NO ESTÁN EN LA LISTA ───────────
     Acá van las fotos que "desaparecían". No se perdían: se guardaban bien,
     pero en una guía que ya estaba marcada como mandada — porque salió
     antes, o porque la quitaste con la ✕ o el tacho. La lista de arriba solo
     muestra las pendientes, y estas no aparecían en ningún lado.

     Cada una dice por qué está acá, y tiene un botón para devolverla a la
     lista. Que no vuelvan SOLAS es a propósito: volver sola es lo que
     causaba los repetidos. --}}
@php $fuera = $this->fotosFueraDeLista(); @endphp

@if(count($fuera))
    <details class="mt-5 rounded-xl border border-gray-200 bg-white dark:border-white/10 dark:bg-gray-900"
             style="padding:12px 14px">
        <summary class="font-bold text-gray-950 dark:text-white"
                 style="font-size:13.5px;cursor:pointer">
            Subidas hoy que no están en la lista ({{ count($fuera) }})
        </summary>

        <p class="text-gray-500 dark:text-gray-400" style="font-size:12px;line-height:1.5;margin:8px 0 10px">
            Estas guías ya estaban marcadas antes de subir la foto de hoy. Si alguna
            tiene que salir, devolvela a la lista.
        </p>

        <div style="display:flex;flex-direction:column;gap:8px">
            @foreach($fuera as $foto)
                @php
                    $aMano = str_starts_with((string) $foto->chat_error, 'Marcada a mano');
                    $cuando = $foto->chat_enviada_at
                        ? $foto->chat_enviada_at->timezone(config('app.zona_local'))->format('d/m g:i a')
                        : '';
                @endphp

                <div wire:key="fuera-{{ $foto->id }}"
                     class="rounded-xl border border-gray-200 dark:border-white/10"
                     style="display:flex;align-items:center;gap:10px;padding:9px">

                    @if($foto->url())
                        <a href="{{ $foto->url() }}" target="_blank" rel="noopener" style="flex:none">
                            <img src="{{ $foto->url() }}" alt=""
                                 style="width:48px;height:48px;object-fit:cover;border-radius:8px;display:block">
                        </a>
                    @endif

                    <div style="flex:1 1 auto;min-width:0">
                        <div class="font-mono font-bold text-primary-600 dark:text-primary-400" style="font-size:13.5px">
                            {{ $foto->telefono ?: 'sin número' }}
                        </div>
                        <div class="text-gray-500 dark:text-gray-400" style="font-size:12px;line-height:1.4">
                            {{ $foto->nombre ?: '—' }}
                            @if(filled($foto->guia)) · guía {{ $foto->guia }} @endif
                        </div>
                        <div class="text-gray-500 dark:text-gray-400" style="font-size:12px;line-height:1.4">
                            {{ $aMano ? 'La quitaste de la lista' : 'Ya se mandó' }}
                            @if($cuando) el {{ $cuando }} @endif
                        </div>
                    </div>

                    <x-filament::button size="xs" color="gray"
                        wire:click="devolverFotoALista({{ $foto->id }})"
                        wire:confirm="{{ $aMano ? 'Devolverla a la lista de por mandar. ¿Seguimos?' : 'Esta foto YA SE MANDÓ. Si la devolvés a la lista, el cliente la va a recibir otra vez. ¿Seguro?' }}">
                        Volver a la lista
                    </x-filament::button>
                </div>
            @endforeach
        </div>
    </details>
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
