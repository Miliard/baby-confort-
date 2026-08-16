<x-filament-panels::page>
    @php
        $r = $this->resumen;
        $s = $this->saldoGuias;
        $entro = $r['depositado'] + $r['transferido'];
    @endphp

    @if(count($this->fechas) > 0)
        {{-- ═══════════ Barra de días ═══════════ --}}
        <div class="flex flex-wrap items-center gap-1.5">
            <span class="mr-1 text-xs text-gray-500">Día:</span>
            @foreach($this->fechas as $f)
                <x-filament::button size="xs" :color="$f === $fecha ? 'primary' : 'gray'"
                    wire:click="verFecha('{{ $f }}')">
                    {{ \Illuminate\Support\Carbon::parse($f)->format('d/m') }}
                </x-filament::button>
            @endforeach
        </div>

        {{-- ═══════════ Las cuatro tarjetas ═══════════ --}}
        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">

            {{-- Resultado --}}
            <div class="rounded-2xl p-4 text-white shadow {{ $r['resultado'] >= 0 ? 'bg-success-600' : 'bg-danger-600' }}">
                <div class="text-[11px] uppercase tracking-wide opacity-80">
                    Resultado {{ \Illuminate\Support\Carbon::parse($fecha)->format('d/m') }}
                </div>
                <div class="mt-0.5 text-3xl font-extrabold tracking-tight">${{ number_format($r['resultado'], 2) }}</div>
                <div class="mt-1 text-[11.5px] opacity-90">
                    {{ $r['bultos'] }} bultos · {{ $r['guias'] }} guías
                    @if($r['proveedor'] == 0)
                        <span class="block font-semibold">⚠️ falta el proveedor</span>
                    @endif
                </div>
            </div>

            {{-- Entró --}}
            <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
                <div class="text-[11px] uppercase tracking-wide text-gray-500">Entró</div>
                <div class="mt-0.5 text-3xl font-extrabold tracking-tight text-gray-950 dark:text-white">
                    ${{ number_format($entro, 2) }}
                </div>
                <div class="mt-1 text-[11.5px] text-gray-500">
                    Express cobró ${{ number_format($r['cobrado'], 2) }} y se quedó ${{ number_format($r['comision'], 2) }}
                    @if($r['transferido'] > 0)
                        <span class="block">+ ${{ number_format($r['transferido'], 2) }} por transferencia</span>
                    @endif
                </div>
            </div>

            {{-- Guías restantes --}}
            <div @class([
                'rounded-2xl border p-4 shadow-sm',
                'border-danger-300 bg-danger-50 dark:border-danger-500/40 dark:bg-danger-500/10'     => $s['hay'] && $s['restantes'] <= 50,
                'border-warning-300 bg-warning-50 dark:border-warning-500/40 dark:bg-warning-500/10' => $s['hay'] && $s['restantes'] > 50 && $s['restantes'] <= 150,
                'border-gray-200 bg-white dark:border-white/10 dark:bg-gray-900'                     => ! $s['hay'] || $s['restantes'] > 150,
            ])>
                <div class="text-[11px] uppercase tracking-wide text-gray-500">Guías restantes</div>
                @if($s['hay'])
                    <div class="mt-0.5 text-3xl font-extrabold tracking-tight text-gray-950 dark:text-white">
                        {{ $s['restantes'] }}
                    </div>
                    <div class="mt-1.5 h-1.5 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-white/10">
                        <div class="h-full rounded-full {{ $s['restantes'] <= 50 ? 'bg-danger-500' : ($s['restantes'] <= 150 ? 'bg-warning-500' : 'bg-success-500') }}"
                             style="width: {{ $s['porcentaje'] }}%"></div>
                    </div>
                    <div class="mt-1 text-[11.5px] text-gray-500">
                        {{ $s['usadas'] }} de {{ $s['compradas'] }} usadas · ${{ number_format($s['costoBulto'], 2) }} c/u
                        @if($s['restantes'] <= 50)
                            <span class="block font-semibold text-danger-600">¡Comprá más!</span>
                        @endif
                    </div>
                @else
                    <div class="mt-2 text-[11.5px] text-gray-500">
                        Cargá tu paquete de guías en el cuadro <b>3</b> de la izquierda.
                    </div>
                @endif
            </div>

            {{-- Sin monto --}}
            <div @class([
                'rounded-2xl border p-4 shadow-sm',
                'border-warning-300 bg-warning-50 dark:border-warning-500/40 dark:bg-warning-500/10' => $this->pendientes->count() > 0,
                'border-gray-200 bg-white dark:border-white/10 dark:bg-gray-900'                     => $this->pendientes->count() === 0,
            ])>
                <div class="text-[11px] uppercase tracking-wide text-gray-500">Sin monto</div>
                <div class="mt-0.5 text-3xl font-extrabold tracking-tight text-gray-950 dark:text-white">
                    {{ $this->pendientes->count() }}
                </div>
                <div class="mt-1 text-[11.5px] text-gray-500">
                    @if($this->pendientes->count() > 0)
                        Falta decir qué pasó con {{ $this->pendientes->count() === 1 ? 'este' : 'estos' }}
                    @else
                        Todo el día está explicado ✅
                    @endif
                </div>
            </div>
        </div>
    @endif

    {{-- ═══════════ Dos columnas ═══════════ --}}
    <div class="grid gap-3 xl:grid-cols-12">

        {{-- ---------- Izquierda: meter datos ---------- --}}
        <div class="space-y-3 xl:col-span-4">

            <x-filament::section compact collapsible :collapsed="count($this->fechas) > 0">
                <x-slot name="heading">1 · Pegar liquidación</x-slot>

                <textarea wire:model="pegado" rows="3"
                    placeholder="13-ago&#9;BABY CONFORT -200&#9;5370975&#9;Luz Villatoro&#9;Corinto&#9;$ 52,00&#9;$ 1,04&#9;$ 50,96"
                    class="w-full rounded-lg border-gray-300 font-mono text-xs dark:border-white/10 dark:bg-white/5"></textarea>

                <div class="mt-2 flex flex-wrap items-end gap-2">
                    <div>
                        <label class="mb-0.5 block text-xs text-gray-500">Día del depósito</label>
                        <input type="date" wire:model="fechaDeposito"
                            class="rounded-lg border-gray-300 text-xs dark:border-white/10 dark:bg-white/5">
                    </div>
                    <x-filament::button size="sm" wire:click="procesar" icon="heroicon-o-arrow-down-tray">
                        Procesar
                    </x-filament::button>
                </div>
            </x-filament::section>

            @if(count($this->fechas) > 0)
                <x-filament::section compact>
                    <x-slot name="heading">2 · Lo que salió ese día</x-slot>

                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="mb-0.5 block text-xs text-gray-500">Proveedor ($)</label>
                            <input type="number" step="0.01" min="0" wire:model="proveedor"
                                class="w-full rounded-lg border-gray-300 text-sm dark:border-white/10 dark:bg-white/5">
                        </div>
                        <div>
                            <label class="mb-0.5 block text-xs text-gray-500">Otros gastos ($)</label>
                            <input type="number" step="0.01" min="0" wire:model="gastos"
                                class="w-full rounded-lg border-gray-300 text-sm dark:border-white/10 dark:bg-white/5">
                        </div>
                        <div class="col-span-2">
                            <input type="text" wire:model="nota" placeholder="Nota (opcional)"
                                class="w-full rounded-lg border-gray-300 text-sm dark:border-white/10 dark:bg-white/5">
                        </div>
                    </div>

                    <x-filament::button size="sm" class="mt-2 w-full justify-center"
                        wire:click="guardarDia" icon="heroicon-o-check">
                        Guardar
                    </x-filament::button>
                    <p class="mt-1 text-xs text-gray-400">Si te equivocaste, corregí el número y guardá otra vez.</p>
                </x-filament::section>
            @endif

            <x-filament::section compact collapsible :collapsed="$s['hay']">
                <x-slot name="heading">3 · Comprar guías</x-slot>

                <div class="flex flex-wrap items-end gap-2">
                    <div>
                        <label class="mb-0.5 block text-xs text-gray-500">Cantidad</label>
                        <input type="number" min="1" wire:model="bloqueCantidad" placeholder="500"
                            class="w-24 rounded-lg border-gray-300 text-sm dark:border-white/10 dark:bg-white/5">
                    </div>
                    <div>
                        <label class="mb-0.5 block text-xs text-gray-500">Costo ($)</label>
                        <input type="number" step="0.01" min="0" wire:model="bloqueCosto" placeholder="1400"
                            class="w-28 rounded-lg border-gray-300 text-sm dark:border-white/10 dark:bg-white/5">
                    </div>
                    <x-filament::button size="sm" wire:click="agregarBloque" icon="heroicon-o-plus">
                        Cargar
                    </x-filament::button>
                </div>
            </x-filament::section>
        </div>

        {{-- ---------- Derecha: ver ---------- --}}
        <div class="space-y-3 xl:col-span-8">

            @if(count($this->fechas) === 0)
                <x-filament::section>
                    <div class="py-12 text-center text-sm text-gray-500">
                        Pegá tu primera liquidación en el cuadro de la izquierda. 👈
                    </div>
                </x-filament::section>
            @else
                {{-- Clientes sin monto --}}
                @if($this->pendientes->count() > 0)
                    <x-filament::section compact>
                        <x-slot name="heading">⚠️ Clientes a los que no les aparece el monto</x-slot>
                        <x-slot name="description">Decime qué pasó con cada uno para que la cuenta cierre.</x-slot>

                        <div class="grid gap-2 md:grid-cols-2">
                            @foreach($this->pendientes as $p)
                                <div wire:key="pend-{{ $p->id }}" x-data="{ monto: '' }"
                                     class="rounded-lg border border-warning-300 p-2.5 dark:border-warning-500/40">
                                    <div class="text-sm font-semibold text-gray-950 dark:text-white">{{ $p->nombre }}</div>
                                    <div class="mt-0.5 text-xs text-gray-500">Guía {{ $p->orden }} · {{ $p->zona }}</div>

                                    <div class="mt-2 flex flex-wrap items-center gap-1.5">
                                        <input type="number" step="0.01" min="0" x-model="monto" placeholder="$ transferido"
                                            class="w-28 rounded-lg border-gray-300 text-xs dark:border-white/10 dark:bg-white/5">
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

                {{-- Gráfica --}}
                @livewire(\App\Filament\Widgets\ResultadoDiarioChart::class)

                @if($r['aiwibiBultos'] > 0)
                    <div class="rounded-xl border border-gray-200 bg-white p-2.5 text-xs text-gray-500 dark:border-white/10 dark:bg-gray-900">
                        Aparte de tus cuentas: {{ $r['aiwibiBultos'] }}
                        {{ $r['aiwibiBultos'] === 1 ? 'bulto de AIWIBI' : 'bultos de AIWIBI' }}
                        por ${{ number_format($r['aiwibiDepositado'], 2) }}. Esa plata no es tuya — va en Remuneración.
                    </div>
                @endif

                {{-- Plegados --}}
                <div class="grid gap-3 lg:grid-cols-2">
                    @if(count($this->depositos) > 0)
                        <x-filament::section compact collapsible collapsed>
                            <x-slot name="heading">Depósitos recibidos</x-slot>
                            <div class="space-y-1.5">
                                @foreach($this->depositos as $d)
                                    <div class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-gray-200 p-2 dark:border-white/10">
                                        <div>
                                            <div class="text-sm font-semibold text-gray-950 dark:text-white">
                                                {{ \Illuminate\Support\Carbon::parse($d['fecha'])->format('d/m/Y') }}
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

                    <x-filament::section compact collapsible collapsed>
                        <x-slot name="heading">Los {{ $this->entregas->count() }} bultos del día</x-slot>
                        <x-slot name="description">Corregí o borrá lo que quedó mal.</x-slot>

                        <div class="max-h-80 overflow-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="border-b border-gray-200 text-left text-xs uppercase text-gray-500 dark:border-white/10">
                                        <th class="py-1.5 pr-2">Cliente</th>
                                        <th class="py-1.5 text-right">Cobrado</th>
                                        <th class="py-1.5 pl-2">Estado</th>
                                        <th class="py-1.5"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($this->entregas as $e)
                                        <tr wire:key="ent-{{ $e->id }}" class="border-b border-gray-100 dark:border-white/5 {{ $e->aiwibi ? 'opacity-50' : '' }}">
                                            <td class="py-1 pr-2">
                                                {{ $e->nombre }}
                                                <span class="block text-xs text-gray-400">{{ $e->orden }}</span>
                                            </td>
                                            <td class="py-1 text-right font-medium {{ $e->monto > 0 ? '' : 'text-gray-400' }}">
                                                ${{ number_format($e->monto, 2) }}
                                            </td>
                                            <td class="py-1 pl-2 text-xs text-gray-500">
                                                @if($e->aiwibi) AIWIBI
                                                @elseif($e->caso === 'transferencia') Transferido ${{ number_format($e->transferido, 2) }}
                                                @elseif($e->caso) {{ \App\Models\ExpressEntrega::CASOS[$e->caso] ?? $e->caso }}
                                                @endif
                                            </td>
                                            <td class="py-1 text-right whitespace-nowrap">
                                                @if($e->caso)
                                                    <x-filament::icon-button icon="heroicon-m-arrow-uturn-left" size="xs" color="warning"
                                                        wire:click="desmarcarCaso({{ $e->id }})" label="Cambiar" />
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

                        <div class="mt-2">
                            <x-filament::button size="xs" color="danger" outlined wire:click="borrarFecha"
                                wire:confirm="¿Borrar todas las entregas de este día? Podés volver a pegarlas.">
                                Borrar el día entero
                            </x-filament::button>
                        </div>
                    </x-filament::section>
                </div>
            @endif
        </div>
    </div>
</x-filament-panels::page>
