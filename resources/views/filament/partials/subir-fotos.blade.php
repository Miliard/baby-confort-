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
{{-- Cada tanda muestra TODAS sus fotos: las que ya salieron, con su marca de
     enviada, y las que faltan. Nada se va de la pantalla hasta que se limpia
     la tanda con el tacho. --}}
@php $lotes = $this->fotosPorLote(); @endphp

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
            @php
                $enviadas = $tanda['cuenta'][\App\Services\FotosAlChat::ENVIADA];
                $faltan   = count($tanda['filas']) - $enviadas;
            @endphp

            <span style="display:flex;align-items:center;gap:8px;flex:none">
                <span class="text-gray-500 dark:text-gray-400" style="font-size:12px">
                    {{ $enviadas }} de {{ count($tanda['filas']) }} enviadas
                </span>

                {{-- La confirmación cambia si todavía quedan sin mandar: ahí
                     limpiar es tirar trabajo pendiente, y conviene que lo diga. --}}
                <x-filament::icon-button
                    icon="heroicon-m-trash" color="gray" size="sm"
                    wire:click="limpiarLoteAlChat('{{ $tanda['clave'] }}')"
                    wire:confirm="{{ $faltan > 0
                        ? 'Limpiar ' . $tanda['titulo'] . '. OJO: ' . $faltan . ($faltan === 1 ? ' foto todavía no se mandó.' : ' fotos todavía no se mandaron.') . ' Las fotos no se borran, siguen en el rastreo. ¿Seguimos?'
                        : 'Limpiar ' . $tanda['titulo'] . '. Ya se mandaron todas. ¿Seguimos?' }}"
                    label="Limpiar esta tanda" />
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
            if ($c[\App\Services\FotosAlChat::ENVIADA])    $partes[] = ['primary', $c[\App\Services\FotosAlChat::ENVIADA] . ' enviadas'];
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
                Mandar {{ $tanda['listas'] === 1 ? 'la 1 que falta' : 'las ' . $tanda['listas'] . ' que faltan' }}
            </x-filament::button>
        @elseif($enviadas === count($tanda['filas']))
            {{-- Todo enviado: la tanda se queda a la vista como constancia
                 hasta que la limpies. Es lo que querías poder ver. --}}
            <p class="rounded-xl text-center font-semibold text-success-700 dark:text-success-400"
               style="padding:12px;font-size:13px;background:rgba(46,158,107,.10)">
                ✅ Se mandaron todas. Limpiala con el tacho cuando quieras.
            </p>
        @else
            <p class="rounded-xl border border-dashed border-gray-300 text-center text-gray-500 dark:border-white/10 dark:text-gray-400"
               style="padding:12px;font-size:12px">
                Las que faltan no se pueden mandar ahora. Abajo dice por qué en cada una.
            </p>
        @endif

        <div style="display:flex;flex-direction:column;gap:8px;margin-top:12px">
            @foreach($tanda['filas'] as $f)
                @php
                    $foto   = $f['foto'];
                    $estado = $f['estado'];

                    [$color, $etiqueta] = match ($estado) {
                        \App\Services\FotosAlChat::ENVIADA  => ['primary', '✓ enviada'],
                        \App\Services\FotosAlChat::LISTA    => ['success', 'lista'],
                        \App\Services\FotosAlChat::ESPERA   => ['warning', 'esperando que escriba'],
                        \App\Services\FotosAlChat::SIN_CHAT => ['gray',    'sin chat'],
                        default                             => ['danger',  'sin teléfono'],
                    };

                    $yaSalio = $estado === \App\Services\FotosAlChat::ENVIADA;

                    // Qué le pasó después de salir, con los mismos colores que
                    // los puntitos del chat. Es el mensaje que se ENCONTRÓ en
                    // el chat al verificar, no la marca del panel.
                    $msj = $yaSalio ? ($f['msj'] ?? null) : null;

                    [$colMsj, $txtMsj] = match ($msj?->estado) {
                        'leido'     => ['#22c55e', 'la vio'],
                        'entregado' => ['#eab308', 'le llegó'],
                        'enviado'   => ['#94a3b8', 'todavía no le llega'],
                        default     => [null, null],
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

                        {{-- A qué chat va, con el nombre de ESE chat. Es lo que
                             se compara de un vistazo: el nombre de la etiqueta
                             arriba, el del chat acá. Si coinciden, está bien;
                             si no, se ve antes de mandar. --}}
                        @if(! empty($f['conv']))
                            <div class="text-gray-500 dark:text-gray-400" style="font-size:12px;line-height:1.4">
                                → chat de <b class="text-gray-950 dark:text-white">{{ $f['conv']->titulo() }}</b>
                            </div>
                        @endif

                        {{-- Si el número se corrigió emparejándolo con
                             Preparados, lo que decía la etiqueta. Las dos
                             cosas a la vista: vos decidís si tiene sentido. --}}
                        @php
                            $leidoTel = preg_replace('/\D/', '', (string) $foto->tel_leido);
                            $ahoraTel = preg_replace('/\D/', '', (string) $foto->telefono);
                        @endphp
                        @if($foto->tel_leido && $leidoTel !== $ahoraTel)
                            <div style="font-size:12px;line-height:1.4;color:#2563eb">
                                🔎 La etiqueta decía
                                <b>{{ $foto->tel_leido === '—' ? 'sin número' : $foto->tel_leido }}</b>
                                · se emparejó con Preparados
                            </div>
                        @endif

                        @if(filled($foto->guia))
                            <p class="text-gray-500 dark:text-gray-400" style="font-size:12px">
                                Guía {{ $foto->guia }}
                            </p>
                        @endif

                        <p class="text-gray-500 dark:text-gray-400"
                           style="font-size:12px;line-height:1.4;margin-top:4px">
                            {{ $f['porque'] }}
                            @if($txtMsj)
                                · <b style="color:{{ $colMsj }}">{{ $txtMsj }}</b>
                            @endif
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
                        {{-- Solo en las que faltan. En una ya enviada, corregir el
                             teléfono no cambia nada: la foto ya le llegó a
                             quien le llegó. --}}
                        {{-- El campo del teléfono: abierto SOLO si falta el número.

                             Antes estaba abierto en todos los renglones, para
                             poder corregir. Pero después de escribir el número
                             y guardarlo, el campo seguía ahí vacío — y parecía
                             que lo seguía pidiendo. Un campo abierto se lee
                             como "falta algo".

                             Ahora: sin número → abierto. Con número → cerrado,
                             y queda un enlace chico por si hay que corregir uno
                             mal leído.

                             El wire:key lleva el teléfono adentro: al guardar,
                             el número cambia, el bloque se arma de nuevo y
                             nace cerrado. Sin eso, Alpine recordaba que estaba
                             abierto y lo dejaba así. --}}
                        @unless($yaSalio)
                            @php $faltaTel = $estado === \App\Services\FotosAlChat::SIN_NUMERO; @endphp

                            <div wire:key="tel-{{ $foto->id }}-{{ $foto->telefono }}"
                                 x-data="{ editar: {{ $faltaTel ? 'true' : 'false' }} }"
                                 style="margin-top:7px">

                                <button type="button" x-show="!editar" x-on:click="editar = true"
                                        class="text-gray-500 dark:text-gray-400"
                                        style="border:none;background:none;padding:0;cursor:pointer;
                                               font-size:12px;text-decoration:underline;
                                               text-underline-offset:2px;font-family:inherit">
                                    ✏️ Corregir el número
                                </button>

                                <div x-show="editar" x-cloak
                                     style="display:flex;gap:6px;align-items:center">
                                    <input type="text" inputmode="numeric" maxlength="12"
                                           wire:model="telManual.{{ $foto->id }}"
                                           wire:keydown.enter="guardarTelefono({{ $foto->id }})"
                                           placeholder="{{ $foto->telefono ? 'Ahora: ' . $foto->telefono : 'Escribí el teléfono: 7055 1234' }}"
                                           aria-label="Escribir el teléfono de esta foto"
                                           class="rounded-lg border border-gray-300 dark:border-white/20"
                                           style="flex:1 1 auto;min-width:0;padding:7px 10px;
                                                  font-size:14px;background:transparent;color:inherit">

                                    <x-filament::button size="xs" color="gray"
                                        wire:click="guardarTelefono({{ $foto->id }})">
                                        Guardar
                                    </x-filament::button>
                                </div>
                            </div>
                        @endunless
                    </div>

                    {{-- Sacar una sola de la pantalla. Para la que no tiene chat
                         y se la pasaste por otro lado. En las enviadas no va:
                         esas se quedan como constancia hasta limpiar la tanda. --}}
                    @unless($yaSalio)
                        <x-filament::icon-button
                            icon="heroicon-m-x-mark" color="gray" size="sm"
                            wire:click="omitirFotoChat({{ $foto->id }})"
                            wire:confirm="Quitarla de la pantalla sin mandarla. ¿Seguro?"
                            label="Quitar de la pantalla sin mandar" />
                    @else
                        {{-- Reenviar a mano. El chat dice que llegó, pero vos
                             podés saber algo que el chat no: que el cliente
                             no la ve, que la borró, que cambió de teléfono.
                             Nunca se reenvía solo — solo con este botón. --}}
                        <x-filament::button size="xs" color="gray"
                            icon="heroicon-m-arrow-path"
                            wire:click="reenviarFoto({{ $foto->id }})"
                            wire:loading.attr="disabled" wire:target="reenviarFoto({{ $foto->id }})"
                            wire:confirm="El chat dice que esta foto ya le llegó. Si la reenviás, la va a recibir otra vez. ¿Seguro?">
                            Reenviar
                        </x-filament::button>
                    @endunless
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
