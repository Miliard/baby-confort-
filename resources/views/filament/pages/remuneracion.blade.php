{{-- wire:poll relee el tablero solo, sin que haya que recargar la página --}}
<x-filament-panels::page wire:poll.90s>
    @php $r = $this->resumen; @endphp

    @if(! $this->hayDatos)
        <x-filament::section>
            <x-slot name="heading">Elegí cómo traer tus datos</x-slot>

            <div class="grid gap-4 md:grid-cols-2">
                <div class="rounded-xl border border-success-300 p-4 dark:border-success-500/40">
                    <div class="text-sm font-bold text-success-700 dark:text-success-400">🔄 Automático — Google Sheets</div>
                    <p class="mt-1 text-xs text-gray-500">Se actualiza solo. Lo configurás una vez y nunca más.</p>
                    <ol class="mt-3 ml-4 list-decimal space-y-1 text-sm text-gray-600 dark:text-gray-300">
                        <li>Guardá tu Excel como <b>.xlsx</b> y subilo a Google Drive.</li>
                        <li>Abrilo con <b>Hojas de cálculo de Google</b>.</li>
                        <li><b>Archivo → Compartir → Publicar en la Web</b>.</li>
                        <li>Elegí la hoja del año y el formato <b>CSV</b>. Publicar.</li>
                        <li>Copiá el enlace y tocá <b>Conectar la hoja</b> arriba.</li>
                    </ol>
                </div>

                <div class="rounded-xl border border-gray-200 p-4 dark:border-white/10">
                    <div class="text-sm font-bold text-gray-700 dark:text-gray-200">📤 A mano — subir el CSV</div>
                    <p class="mt-1 text-xs text-gray-500">Seguís en tu Excel de siempre, pero lo subís cada vez que querás el corte.</p>
                    <ol class="mt-3 ml-4 list-decimal space-y-1 text-sm text-gray-600 dark:text-gray-300">
                        <li>En tu Excel: <b>Archivo → Exportar</b>.</li>
                        <li>Elegí <b>Descargar como CSV UTF-8</b> (ese, no el otro).</li>
                        <li>Tocá <b>Subir archivo</b> arriba y elegilo.</li>
                    </ol>
                    <p class="mt-3 text-xs text-gray-500">Se sube solo la hoja que tengas abierta en ese momento.</p>
                </div>
            </div>
        </x-filament::section>
    @else
        {{-- Periodo --}}
        <x-filament::section>
            <x-slot name="heading">Periodo</x-slot>

            <div class="flex flex-wrap items-end gap-3">
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-500">Desde</label>
                    <input type="date" wire:model.live="desde"
                           class="rounded-lg border-gray-300 text-sm dark:border-white/10 dark:bg-white/5">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-500">Hasta</label>
                    <input type="date" wire:model.live="hasta"
                           class="rounded-lg border-gray-300 text-sm dark:border-white/10 dark:bg-white/5">
                </div>
                <div class="flex flex-wrap gap-2">
                    <x-filament::button size="xs" color="gray" wire:click="periodo('semana')">Esta semana</x-filament::button>
                    <x-filament::button size="xs" color="gray" wire:click="periodo('mes')">Este mes</x-filament::button>
                    <x-filament::button size="xs" color="gray" wire:click="periodo('anterior')">Mes pasado</x-filament::button>
                    <x-filament::button size="xs" color="gray" wire:click="periodo('todo')">Todo</x-filament::button>
                </div>

                <div class="ml-auto flex flex-wrap items-end gap-3">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-500">Comisión (%)</label>
                        <input type="number" step="0.01" min="0" wire:model.live.debounce.600ms="comision"
                               class="w-24 rounded-lg border-gray-300 text-sm dark:border-white/10 dark:bg-white/5">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-500">Por envío ($)</label>
                        <input type="number" step="0.01" min="0" wire:model.live.debounce.600ms="porEnvio"
                               class="w-24 rounded-lg border-gray-300 text-sm dark:border-white/10 dark:bg-white/5">
                    </div>
                </div>
            </div>
        </x-filament::section>

        {{-- El número que importa --}}
        <div class="rounded-2xl bg-primary-600 p-6 text-white shadow-lg dark:bg-primary-500">
            <div class="text-sm font-medium opacity-90">Total a remunerar</div>
            <div class="mt-1 text-4xl font-bold tracking-tight">
                ${{ number_format($r['aPagar'], 2) }}
            </div>
            <div class="mt-2 text-xs opacity-90">
                {{ $r['envios'] }} {{ $r['envios'] === 1 ? 'envío AIWIBI' : 'envíos AIWIBI' }}
                @if($desde || $hasta)
                    · {{ $desde ?: 'el inicio' }} a {{ $hasta ?: 'hoy' }}
                @else
                    · todo el historial
                @endif
            </div>

            @if($this->leido)
                <div class="mt-3 flex items-center gap-1.5 text-xs opacity-80">
                    <span class="relative flex h-2 w-2">
                        <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-white opacity-75"></span>
                        <span class="relative inline-flex h-2 w-2 rounded-full bg-white"></span>
                    </span>
                    {{ $this->leido }}
                </div>
            @endif
        </div>

        {{-- Cómo se llegó a ese número, paso por paso --}}
        <x-filament::section>
            <x-slot name="heading">Resumen de cálculos</x-slot>

            <div class="space-y-2 text-sm">
                <div class="flex items-center justify-between rounded-lg bg-success-50 px-3 py-2.5 dark:bg-success-500/10">
                    <span>
                        <b class="text-success-700 dark:text-success-400">1. Suma de los pedidos AIWIBI</b>
                        <span class="block text-xs text-gray-500">{{ $r['enEfectivo'] }} pedidos con cobro</span>
                    </span>
                    <span class="text-lg font-bold text-success-700 dark:text-success-400">
                        ${{ number_format($r['efectivo'], 2) }}
                    </span>
                </div>

                <div class="flex items-center justify-between rounded-lg bg-primary-50 px-3 py-2.5 dark:bg-primary-500/10">
                    <span>
                        <b class="text-primary-700 dark:text-primary-400">2. Descontar {{ rtrim(rtrim(number_format($r['comisionPct'], 2), '0'), '.') }}%</b>
                        <span class="block text-xs text-gray-500">
                            {{ rtrim(rtrim(number_format($r['comisionPct'], 2), '0'), '.') }}% de ${{ number_format($r['efectivo'], 2) }}
                        </span>
                    </span>
                    <span class="text-lg font-bold text-danger-600">− ${{ number_format($r['comision'], 2) }}</span>
                </div>

                <div class="flex items-center justify-between rounded-lg border border-dashed border-gray-200 px-3 py-2 dark:border-white/10">
                    <span class="font-semibold text-gray-600 dark:text-gray-300">Subtotal</span>
                    <span class="font-bold">${{ number_format($r['subtotal'], 2) }}</span>
                </div>

                <div class="flex items-center justify-between rounded-lg bg-warning-50 px-3 py-2.5 dark:bg-warning-500/10">
                    <span>
                        <b class="text-warning-700 dark:text-warning-400">3. Descontar envíos</b>
                        <span class="block text-xs text-gray-500">
                            {{ $r['envios'] }} envíos × ${{ number_format($r['porEnvio'], 2) }}
                            @if($r['sinCobro'] > 0) · incluye {{ $r['sinCobro'] }} sin cobro @endif
                            @if(count($r['cancelados'] ?? []) > 0)
                                · {{ count($r['cancelados']) }} con nota en CANCELACIÓN no cuentan
                            @endif
                        </span>
                    </span>
                    <span class="text-lg font-bold text-danger-600">− ${{ number_format($r['descuento'], 2) }}</span>
                </div>

                <div class="mt-1 flex items-center justify-between rounded-xl bg-gray-900 px-4 py-3 text-white dark:bg-white/10">
                    <span class="font-bold">TOTAL FINAL</span>
                    <span class="text-2xl font-extrabold {{ $r['aPagar'] < 0 ? 'text-danger-400' : 'text-success-400' }}">
                        ${{ number_format($r['aPagar'], 2) }}
                    </span>
                </div>

                <p class="pt-1 text-center text-xs text-gray-500">
                    ${{ number_format($r['efectivo'], 2) }}
                    − {{ rtrim(rtrim(number_format($r['comisionPct'], 2), '0'), '.') }}% (${{ number_format($r['comision'], 2) }})
                    = ${{ number_format($r['subtotal'], 2) }}
                    − {{ $r['envios'] }} × ${{ number_format($r['porEnvio'], 2) }} (${{ number_format($r['descuento'], 2) }})
                    = <b>${{ number_format($r['aPagar'], 2) }}</b>
                </p>
            </div>
        </x-filament::section>

        {{-- Detalle --}}
        <x-filament::section collapsible collapsed>
            <x-slot name="heading">Ver las {{ count($r['filas']) }} entregas</x-slot>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 text-left text-xs uppercase text-gray-500 dark:border-white/10">
                            <th class="py-2 pr-3">Fecha</th>
                            <th class="py-2 pr-3">Orden</th>
                            <th class="py-2 pr-3">Cliente</th>
                            <th class="py-2 pr-3">Zona</th>
                            <th class="py-2 text-right">Monto</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($r['filas'] as $f)
                            <tr class="border-b border-gray-100 dark:border-white/5">
                                <td class="py-2 pr-3 whitespace-nowrap text-gray-500">{{ $f['fecha'] ?? '—' }}</td>
                                <td class="py-2 pr-3 whitespace-nowrap text-gray-500">{{ $f['orden'] }}</td>
                                <td class="py-2 pr-3">{{ $f['nombre'] }}</td>
                                <td class="py-2 pr-3 text-gray-500">{{ $f['zona'] }}</td>
                                <td class="py-2 text-right font-medium {{ $f['monto'] > 0 ? '' : 'text-gray-400' }}">
                                    ${{ number_format($f['monto'], 2) }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-6 text-center text-gray-500">
                                    No hay entregas AIWIBI en ese periodo.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    @endif
</x-filament-panels::page>
