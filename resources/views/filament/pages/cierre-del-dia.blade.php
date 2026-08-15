<x-filament-panels::page>
    @php $r = $this->resumen; @endphp

    {{-- ---------- Pegar la liquidación ---------- --}}
    <x-filament::section collapsible :collapsed="count($this->fechas) > 0">
        <x-slot name="heading">Pegar la liquidación de Express</x-slot>
        <x-slot name="description">
            Copiá las celdas desde Excel y pegalas aquí. Pueden venir varias fechas juntas.
        </x-slot>

        <textarea wire:model="pegado" rows="6" placeholder="13-ago&#9;BABY CONFORT -200&#9;5370975&#9;Luz Villatoro&#9;Corinto&#9;$ 52,00&#9;$ 1,04&#9;$ 50,96"
            class="w-full rounded-xl border-gray-300 font-mono text-xs dark:border-white/10 dark:bg-white/5"></textarea>

        <div class="mt-3">
            <x-filament::button wire:click="procesar" icon="heroicon-o-arrow-down-tray">
                Procesar el pegado
            </x-filament::button>
        </div>
    </x-filament::section>

    @if(count($this->fechas) === 0)
        <x-filament::section>
            <div class="py-6 text-center text-sm text-gray-500">
                Todavía no hay días cargados. Pegá arriba tu primera liquidación. 👆
            </div>
        </x-filament::section>
    @else
        {{-- ---------- Elegir el día ---------- --}}
        <x-filament::section>
            <x-slot name="heading">Día</x-slot>
            <div class="flex flex-wrap gap-2">
                @foreach($this->fechas as $f)
                    <x-filament::button size="xs" :color="$f === $fecha ? 'primary' : 'gray'"
                        wire:click="verFecha('{{ $f }}')">
                        {{ \Illuminate\Support\Carbon::parse($f)->format('d/m') }}
                    </x-filament::button>
                @endforeach
            </div>
        </x-filament::section>

        {{-- ---------- El número ---------- --}}
        <div class="rounded-2xl p-6 text-white shadow-lg {{ $r['resultado'] >= 0 ? 'bg-success-600' : 'bg-danger-600' }}">
            <div class="text-sm font-medium opacity-90">
                Resultado del {{ \Illuminate\Support\Carbon::parse($fecha)->translatedFormat('d \d\e F') }}
            </div>
            <div class="mt-1 text-4xl font-bold tracking-tight">
                ${{ number_format($r['resultado'], 2) }}
            </div>
            <div class="mt-2 text-xs opacity-90">
                {{ $r['bultos'] }} {{ $r['bultos'] === 1 ? 'bulto' : 'bultos' }}
                en {{ $r['guias'] }} {{ $r['guias'] === 1 ? 'guía' : 'guías' }}
                @if($r['proveedor'] == 0)
                    · ⚠️ falta lo que te cobró el proveedor
                @endif
            </div>
        </div>

        {{-- ---------- Faltan explicar los $0 ---------- --}}
        @if($this->pendientes->count() > 0)
            <x-filament::section>
                <x-slot name="heading">
                    ⚠️ {{ $this->pendientes->count() }}
                    {{ $this->pendientes->count() === 1 ? 'bulto vino en $0' : 'bultos vinieron en $0' }}
                </x-slot>
                <x-slot name="description">
                    Express no cobró nada por estos. Decime qué pasó con cada uno para que la cuenta salga bien.
                </x-slot>

                <div class="space-y-2">
                    @foreach($this->pendientes as $p)
                        <div wire:key="pend-{{ $p->id }}" x-data="{ monto: '' }"
                             class="rounded-xl border border-warning-300 p-3 dark:border-warning-500/40">
                            <div class="text-sm font-semibold text-gray-950 dark:text-white">{{ $p->nombre }}</div>
                            <div class="mt-0.5 text-xs text-gray-500">Guía {{ $p->orden }} · {{ $p->zona }}</div>

                            <div class="mt-2 flex flex-wrap items-center gap-2">
                                <input type="number" step="0.01" min="0" x-model="monto" placeholder="¿Cuánto te transfirió?"
                                    class="w-44 rounded-lg border-gray-300 text-sm dark:border-white/10 dark:bg-white/5">

                                <x-filament::button size="xs" color="success"
                                    x-on:click="$wire.marcarCaso({{ $p->id }}, 'transferencia', monto)">
                                    Me transfirieron
                                </x-filament::button>

                                <x-filament::button size="xs" color="gray"
                                    wire:click="marcarCaso({{ $p->id }}, 'bulto_extra')">
                                    Bulto extra
                                </x-filament::button>

                                <x-filament::button size="xs" color="danger"
                                    wire:click="marcarCaso({{ $p->id }}, 'devolucion')">
                                    Se devolvió
                                </x-filament::button>
                            </div>
                        </div>
                    @endforeach
                </div>
            </x-filament::section>
        @endif

        {{-- ---------- Lo que solo vos sabés ---------- --}}
        <x-filament::section>
            <x-slot name="heading">Lo que salió ese día</x-slot>

            <div class="flex flex-wrap items-end gap-3">
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-500">Me cobró el proveedor ($)</label>
                    <input type="number" step="0.01" min="0" wire:model="proveedor"
                        class="w-40 rounded-lg border-gray-300 text-sm dark:border-white/10 dark:bg-white/5">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-500">Otros gastos ($)</label>
                    <input type="number" step="0.01" min="0" wire:model="gastos"
                        class="w-40 rounded-lg border-gray-300 text-sm dark:border-white/10 dark:bg-white/5">
                </div>
                <div class="min-w-[200px] flex-1">
                    <label class="mb-1 block text-xs font-medium text-gray-500">Nota (opcional)</label>
                    <input type="text" wire:model="nota" placeholder="Ej: compra de pañales talla M"
                        class="w-full rounded-lg border-gray-300 text-sm dark:border-white/10 dark:bg-white/5">
                </div>
                <x-filament::button wire:click="guardarDia" icon="heroicon-o-check">Guardar</x-filament::button>
            </div>
        </x-filament::section>

        {{-- ---------- El desglose ---------- --}}
        <x-filament::section>
            <x-slot name="heading">Cómo sale ese número</x-slot>

            <div class="space-y-1.5 text-sm">
                @php
                    $fila = function ($etiqueta, $valor, $sub = null, $signo = '+') {
                        return compact('etiqueta', 'valor', 'sub', 'signo');
                    };
                    $lineas = [
                        $fila('Express te depositó', $r['depositado'], $r['bultos'] . ' bultos · cobró $' . number_format($r['cobrado'], 2) . ' y se quedó $' . number_format($r['comision'], 2)),
                    ];
                    if ($r['transferido'] > 0) {
                        $lineas[] = $fila('Te transfirieron directo', $r['transferido'], 'de los bultos que vinieron en $0');
                    }
                    $lineas[] = $fila('Bultos', $r['costoBultos'], $r['bultos'] . ' × $' . number_format($r['costoBulto'], 2), '−');
                    $lineas[] = $fila('Proveedor', $r['proveedor'], $r['proveedor'] == 0 ? 'todavía sin cargar' : null, '−');
                    if ($r['gastos'] > 0) $lineas[] = $fila('Otros gastos', $r['gastos'], null, '−');
                @endphp

                @foreach($lineas as $l)
                    <div class="flex items-center justify-between border-b border-gray-100 py-2 dark:border-white/10">
                        <span>
                            <span class="text-gray-700 dark:text-gray-200">{{ $l['etiqueta'] }}</span>
                            @if($l['sub'])<span class="block text-xs text-gray-400">{{ $l['sub'] }}</span>@endif
                        </span>
                        <span class="font-semibold {{ $l['signo'] === '−' ? 'text-danger-600' : '' }}">
                            {{ $l['signo'] }} ${{ number_format($l['valor'], 2) }}
                        </span>
                    </div>
                @endforeach

                <div class="flex items-center justify-between pt-2">
                    <span class="font-bold">Resultado</span>
                    <span class="text-xl font-extrabold {{ $r['resultado'] >= 0 ? 'text-success-600' : 'text-danger-600' }}">
                        ${{ number_format($r['resultado'], 2) }}
                    </span>
                </div>
            </div>

            @if($r['aiwibiBultos'] > 0)
                <div class="mt-4 rounded-xl bg-gray-50 p-3 text-xs text-gray-500 dark:bg-white/5">
                    Aparte: {{ $r['aiwibiBultos'] }}
                    {{ $r['aiwibiBultos'] === 1 ? 'bulto de AIWIBI' : 'bultos de AIWIBI' }}
                    por ${{ number_format($r['aiwibiDepositado'], 2) }}. Esa plata no es tuya —
                    va en la pantalla de Remuneración.
                </div>
            @endif
        </x-filament::section>

        {{-- ---------- Detalle ---------- --}}
        <x-filament::section collapsible collapsed>
            <x-slot name="heading">Ver los {{ $this->entregas->count() }} bultos del día</x-slot>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 text-left text-xs uppercase text-gray-500 dark:border-white/10">
                            <th class="py-2 pr-3">Guía</th>
                            <th class="py-2 pr-3">Cliente</th>
                            <th class="py-2 pr-3">Zona</th>
                            <th class="py-2 text-right">Cobrado</th>
                            <th class="py-2 pl-3">Nota</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($this->entregas as $e)
                            <tr class="border-b border-gray-100 dark:border-white/5 {{ $e->aiwibi ? 'opacity-50' : '' }}">
                                <td class="py-2 pr-3 whitespace-nowrap text-gray-500">{{ $e->orden }}</td>
                                <td class="py-2 pr-3">{{ $e->nombre }}</td>
                                <td class="py-2 pr-3 text-gray-500">{{ $e->zona }}</td>
                                <td class="py-2 text-right font-medium {{ $e->monto > 0 ? '' : 'text-gray-400' }}">
                                    ${{ number_format($e->monto, 2) }}
                                </td>
                                <td class="py-2 pl-3 text-xs text-gray-500">
                                    @if($e->aiwibi) AIWIBI
                                    @elseif($e->caso === 'transferencia') Transferido ${{ number_format($e->transferido, 2) }}
                                    @elseif($e->caso) {{ \App\Models\ExpressEntrega::CASOS[$e->caso] ?? $e->caso }}
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                <x-filament::button size="xs" color="danger" outlined wire:click="borrarFecha"
                    wire:confirm="¿Borrar todas las entregas de este día? Podés volver a pegarlas.">
                    Borrar este día y volver a pegarlo
                </x-filament::button>
            </div>
        </x-filament::section>
    @endif
</x-filament-panels::page>
