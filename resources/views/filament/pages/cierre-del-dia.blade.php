<x-filament-panels::page>
    @php $r = $this->resumen; @endphp

    <div class="grid gap-5 lg:grid-cols-5">

        {{-- ═══════════ IZQUIERDA: meter datos ═══════════ --}}
        <div class="space-y-4 lg:col-span-2 lg:sticky lg:top-24 lg:self-start">

            {{-- Pegar la liquidación --}}
            <x-filament::section compact collapsible :collapsed="count($this->fechas) > 0">
                <x-slot name="heading">1 · Pegar la liquidación</x-slot>

                <textarea wire:model="pegado" rows="4"
                    placeholder="13-ago&#9;BABY CONFORT -200&#9;5370975&#9;Luz Villatoro&#9;Corinto&#9;$ 52,00&#9;$ 1,04&#9;$ 50,96"
                    class="w-full rounded-lg border-gray-300 font-mono text-xs dark:border-white/10 dark:bg-white/5"></textarea>

                <div class="mt-2 flex flex-wrap items-end gap-2">
                    <div>
                        <label class="mb-1 block text-xs text-gray-500">Día del depósito</label>
                        <input type="date" wire:model="fechaDeposito"
                            class="rounded-lg border-gray-300 text-sm dark:border-white/10 dark:bg-white/5">
                    </div>
                    <x-filament::button size="sm" wire:click="procesar" icon="heroicon-o-arrow-down-tray">
                        Procesar
                    </x-filament::button>
                </div>
            </x-filament::section>

            @if(count($this->fechas) > 0)
                {{-- Elegir el día --}}
                <x-filament::section compact>
                    <x-slot name="heading">2 · Día</x-slot>
                    <div class="flex flex-wrap gap-1.5">
                        @foreach($this->fechas as $f)
                            <x-filament::button size="xs" :color="$f === $fecha ? 'primary' : 'gray'"
                                wire:click="verFecha('{{ $f }}')">
                                {{ \Illuminate\Support\Carbon::parse($f)->format('d/m') }}
                            </x-filament::button>
                        @endforeach
                    </div>
                </x-filament::section>

                {{-- Lo que salió --}}
                <x-filament::section compact>
                    <x-slot name="heading">3 · Lo que salió ese día</x-slot>

                    <div class="space-y-2">
                        <div>
                            <label class="mb-1 block text-xs text-gray-500">Me cobró el proveedor ($)</label>
                            <input type="number" step="0.01" min="0" wire:model="proveedor"
                                class="w-full rounded-lg border-gray-300 text-sm dark:border-white/10 dark:bg-white/5">
                        </div>
                        <div>
                            <label class="mb-1 block text-xs text-gray-500">Otros gastos ($)</label>
                            <input type="number" step="0.01" min="0" wire:model="gastos"
                                class="w-full rounded-lg border-gray-300 text-sm dark:border-white/10 dark:bg-white/5">
                        </div>
                        <div>
                            <label class="mb-1 block text-xs text-gray-500">Nota</label>
                            <input type="text" wire:model="nota" placeholder="Ej: compra de pañales talla M"
                                class="w-full rounded-lg border-gray-300 text-sm dark:border-white/10 dark:bg-white/5">
                        </div>
                        <x-filament::button size="sm" class="w-full justify-center"
                            wire:click="guardarDia" icon="heroicon-o-check">
                            Guardar
                        </x-filament::button>
                        <p class="text-xs text-gray-400">
                            Si te equivocaste, escribí el número correcto y volvé a guardar.
                        </p>
                    </div>
                </x-filament::section>

                {{-- Los $0 por contestar --}}
                @if($this->pendientes->count() > 0)
                    <x-filament::section compact>
                        <x-slot name="heading">
                            4 · ⚠️ {{ $this->pendientes->count() }} en $0
                        </x-slot>
                        <x-slot name="description">Express no cobró nada. ¿Qué pasó?</x-slot>

                        <div class="space-y-2">
                            @foreach($this->pendientes as $p)
                                <div wire:key="pend-{{ $p->id }}" x-data="{ monto: '' }"
                                     class="rounded-lg border border-warning-300 p-2.5 dark:border-warning-500/40">
                                    <div class="text-sm font-semibold text-gray-950 dark:text-white">{{ $p->nombre }}</div>
                                    <div class="mt-0.5 text-xs text-gray-500">Guía {{ $p->orden }} · {{ $p->zona }}</div>

                                    <div class="mt-2 flex flex-wrap items-center gap-1.5">
                                        <input type="number" step="0.01" min="0" x-model="monto" placeholder="$ que te transfirió"
                                            class="w-32 rounded-lg border-gray-300 text-xs dark:border-white/10 dark:bg-white/5">
                                        <x-filament::button size="xs" color="success"
                                            x-on:click="$wire.marcarCaso({{ $p->id }}, 'transferencia', monto)">
                                            Transferido
                                        </x-filament::button>
                                        <x-filament::button size="xs" color="gray"
                                            wire:click="marcarCaso({{ $p->id }}, 'bulto_extra')">
                                            Bulto extra
                                        </x-filament::button>
                                        <x-filament::button size="xs" color="danger"
                                            wire:click="marcarCaso({{ $p->id }}, 'devolucion')">
                                            Devuelto
                                        </x-filament::button>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </x-filament::section>
                @endif
            @endif
        </div>

        {{-- ═══════════ DERECHA: ver el resultado ═══════════ --}}
        <div class="space-y-4 lg:col-span-3">

            @if(count($this->fechas) === 0)
                <x-filament::section>
                    <div class="py-10 text-center text-sm text-gray-500">
                        Pegá tu primera liquidación en el cuadro de la izquierda. 👈
                    </div>
                </x-filament::section>
            @else
                {{-- El número --}}
                <div class="rounded-2xl p-5 text-white shadow-lg {{ $r['resultado'] >= 0 ? 'bg-success-600' : 'bg-danger-600' }}">
                    <div class="text-xs font-medium opacity-90">
                        Resultado del {{ \Illuminate\Support\Carbon::parse($fecha)->translatedFormat('d \d\e F') }}
                    </div>
                    <div class="mt-0.5 text-4xl font-bold tracking-tight">${{ number_format($r['resultado'], 2) }}</div>
                    <div class="mt-1.5 text-xs opacity-90">
                        {{ $r['bultos'] }} {{ $r['bultos'] === 1 ? 'bulto' : 'bultos' }}
                        en {{ $r['guias'] }} {{ $r['guias'] === 1 ? 'guía' : 'guías' }}
                        @if($r['proveedor'] == 0) · ⚠️ falta lo del proveedor @endif
                    </div>
                </div>

                {{-- Gráfica --}}
                @livewire(\App\Filament\Widgets\ResultadoDiarioChart::class)

                {{-- Desglose --}}
                <x-filament::section compact>
                    <x-slot name="heading">Cómo sale ese número</x-slot>

                    @php
                        $lineas = [[
                            'et' => 'Express te depositó', 'v' => $r['depositado'], 's' => '+',
                            'sub' => $r['bultos'] . ' bultos · cobró $' . number_format($r['cobrado'], 2)
                                   . ' y se quedó $' . number_format($r['comision'], 2),
                        ]];
                        if ($r['transferido'] > 0) {
                            $lineas[] = ['et' => 'Te transfirieron directo', 'v' => $r['transferido'], 's' => '+',
                                         'sub' => 'de los bultos que vinieron en $0'];
                        }
                        $lineas[] = ['et' => 'Bultos', 'v' => $r['costoBultos'], 's' => '−',
                                     'sub' => $r['bultos'] . ' × $' . number_format($r['costoBulto'], 2)];
                        $lineas[] = ['et' => 'Proveedor', 'v' => $r['proveedor'], 's' => '−',
                                     'sub' => $r['proveedor'] == 0 ? 'todavía sin cargar' : null];
                        if ($r['gastos'] > 0) {
                            $lineas[] = ['et' => 'Otros gastos', 'v' => $r['gastos'], 's' => '−', 'sub' => null];
                        }
                    @endphp

                    <div class="space-y-0.5 text-sm">
                        @foreach($lineas as $l)
                            <div class="flex items-center justify-between border-b border-gray-100 py-1.5 dark:border-white/10">
                                <span>
                                    <span class="text-gray-700 dark:text-gray-200">{{ $l['et'] }}</span>
                                    @if($l['sub'])<span class="block text-xs text-gray-400">{{ $l['sub'] }}</span>@endif
                                </span>
                                <span class="font-semibold {{ $l['s'] === '−' ? 'text-danger-600' : '' }}">
                                    {{ $l['s'] }} ${{ number_format($l['v'], 2) }}
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
                        <div class="mt-3 rounded-lg bg-gray-50 p-2.5 text-xs text-gray-500 dark:bg-white/5">
                            Aparte: {{ $r['aiwibiBultos'] }} de AIWIBI por ${{ number_format($r['aiwibiDepositado'], 2) }}.
                            Esa plata no es tuya — va en Remuneración.
                        </div>
                    @endif
                </x-filament::section>

                {{-- Depósitos --}}
                @if(count($this->depositos) > 0)
                    <x-filament::section compact collapsible collapsed>
                        <x-slot name="heading">Depósitos recibidos</x-slot>
                        <x-slot name="description">Un depósito cubre varias fechas de entrega.</x-slot>

                        <div class="space-y-1.5">
                            @foreach($this->depositos as $d)
                                <div class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-gray-200 p-2.5 dark:border-white/10">
                                    <div>
                                        <div class="text-sm font-semibold text-gray-950 dark:text-white">
                                            {{ \Illuminate\Support\Carbon::parse($d['fecha'])->translatedFormat('d \d\e F') }}
                                        </div>
                                        <div class="text-xs text-gray-500">
                                            {{ $d['bultos'] }} bultos · entregas del {{ implode(', ', $d['entregas']) }}
                                        </div>
                                    </div>
                                    <div class="font-bold text-success-600">${{ number_format($d['monto'], 2) }}</div>
                                </div>
                            @endforeach
                        </div>
                    </x-filament::section>
                @endif

                {{-- Detalle con corrección --}}
                <x-filament::section compact collapsible collapsed>
                    <x-slot name="heading">Los {{ $this->entregas->count() }} bultos del día</x-slot>
                    <x-slot name="description">Si algo quedó mal, corregilo o borralo aquí.</x-slot>

                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-gray-200 text-left text-xs uppercase text-gray-500 dark:border-white/10">
                                    <th class="py-2 pr-2">Guía</th>
                                    <th class="py-2 pr-2">Cliente</th>
                                    <th class="py-2 text-right">Cobrado</th>
                                    <th class="py-2 pl-2">Estado</th>
                                    <th class="py-2"></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($this->entregas as $e)
                                    <tr wire:key="ent-{{ $e->id }}" class="border-b border-gray-100 dark:border-white/5 {{ $e->aiwibi ? 'opacity-50' : '' }}">
                                        <td class="py-1.5 pr-2 whitespace-nowrap text-xs text-gray-500">{{ $e->orden }}</td>
                                        <td class="py-1.5 pr-2">{{ $e->nombre }}</td>
                                        <td class="py-1.5 text-right font-medium {{ $e->monto > 0 ? '' : 'text-gray-400' }}">
                                            ${{ number_format($e->monto, 2) }}
                                        </td>
                                        <td class="py-1.5 pl-2 text-xs text-gray-500">
                                            @if($e->aiwibi)
                                                AIWIBI
                                            @elseif($e->caso === 'transferencia')
                                                Transferido ${{ number_format($e->transferido, 2) }}
                                            @elseif($e->caso)
                                                {{ \App\Models\ExpressEntrega::CASOS[$e->caso] ?? $e->caso }}
                                            @endif
                                        </td>
                                        <td class="py-1.5 text-right whitespace-nowrap">
                                            @if($e->caso)
                                                <x-filament::icon-button icon="heroicon-m-arrow-uturn-left" size="xs" color="warning"
                                                    wire:click="desmarcarCaso({{ $e->id }})" label="Cambiar la respuesta" />
                                            @endif
                                            <x-filament::icon-button icon="heroicon-m-trash" size="xs" color="danger"
                                                wire:click="borrarEntrega({{ $e->id }})"
                                                wire:confirm="¿Borrar este bulto?" label="Borrar" />
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-3">
                        <x-filament::button size="xs" color="danger" outlined wire:click="borrarFecha"
                            wire:confirm="¿Borrar todas las entregas de este día? Podés volver a pegarlas.">
                            Borrar el día entero y pegarlo de nuevo
                        </x-filament::button>
                    </div>
                </x-filament::section>
            @endif
        </div>
    </div>
</x-filament-panels::page>
