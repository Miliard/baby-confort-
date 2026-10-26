<x-filament-panels::page>
    <div class="space-y-6">

        {{-- ─────────── API OFICIAL (lo principal) ─────────── --}}
        <x-filament::section>
            <x-slot name="heading">Seguimiento automático con Express</x-slot>
            @if($apiLista)
                <p class="text-sm text-gray-600 dark:text-gray-300">
                    ✅ Conectado a la API de Express. Las guías se actualizan solas cuando un cliente abre su rastreo
                    (como máximo cada 10 minutos). También puede actualizarlas ahora:
                </p>
                <div class="mt-3 flex flex-wrap items-center gap-3">
                    <x-filament::button wire:click="actualizarAhora" wire:loading.attr="disabled" icon="heroicon-m-arrow-path">
                        <span wire:loading.remove wire:target="actualizarAhora">🔄 Actualizar ahora</span>
                        <span wire:loading wire:target="actualizarAhora">Consultando a Express…</span>
                    </x-filament::button>
                    <span class="text-sm text-gray-500 dark:text-gray-400">Última consulta: <b class="text-gray-950 dark:text-white">{{ $ultimaApi ?? 'todavía ninguna' }}</b></span>
                    <a href="{{ route('express.prueba') }}" target="_blank" class="text-xs text-gray-400 hover:underline">prueba técnica ↗</a>
                </div>
            @else
                <p class="text-sm text-gray-600 dark:text-gray-300">Falta la variable <b>TNT_API_KEY</b> en Railway.</p>
            @endif
        </x-filament::section>

        {{-- ─────────── RESUMEN ─────────── --}}
        @if(! $hayTabla)
            <x-filament::section>
                <p class="text-sm text-danger-600">Falta crear la tabla de estados. Se crea sola al desplegar (migración).</p>
            </x-filament::section>
        @elseif($filas->isNotEmpty())
            {{-- Mismas tarjetas que el Panel de Control de Express. --}}
            <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
                @foreach($tarjetas as $clave => [$ico, $txt])
                    <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-white/10 dark:bg-white/5">
                        <div class="text-sm text-gray-500 dark:text-gray-400">{{ $ico }} {{ $txt }}</div>
                        <div class="mt-1 text-2xl font-bold text-gray-950 dark:text-white">{{ $resumen[$clave] }}</div>
                    </div>
                @endforeach
                <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-white/10 dark:bg-white/5">
                    <div class="text-sm text-gray-500 dark:text-gray-400">📊 Activas</div>
                    <div class="mt-1 text-2xl font-bold text-gray-950 dark:text-white">{{ $activas }}</div>
                    <div class="text-xs text-gray-400">Entregadas en 30 días: {{ $entregadas30 }}</div>
                </div>
            </div>

            <x-filament::section>
                <x-slot name="heading">Guías de los últimos 30 días</x-slot>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-xs uppercase text-gray-500 dark:text-gray-400">
                                <th class="py-2 pr-3">Guía</th><th class="py-2 pr-3">Cliente</th>
                                <th class="py-2 pr-3">Estado</th><th class="py-2 pr-3">Repartidor</th><th class="py-2">Cambió</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($filas as $f)
                                <tr class="border-t border-gray-100 dark:border-white/5">
                                    <td class="py-1.5 pr-3 font-semibold">{{ $f['guia'] }}</td>
                                    <td class="py-1.5 pr-3 text-gray-600 dark:text-gray-300">{{ \Illuminate\Support\Str::limit($f['cliente'], 34) }}</td>
                                    <td class="py-1.5 pr-3">
                                        <span @class([
                                            'rounded-full px-2 py-0.5 text-xs font-bold',
                                            'bg-success-100 text-success-700' => $f['etapa'] === 4,
                                            'bg-info-100 text-info-700'       => $f['etapa'] === 3,
                                            'bg-warning-100 text-warning-700' => $f['etapa'] <= 2,
                                        ])>{{ $f['estado'] }}</span>
                                    </td>
                                    <td class="py-1.5 pr-3 text-gray-500 dark:text-gray-400">{{ $f['repartidor'] ?: '—' }}</td>
                                    <td class="py-1.5 text-gray-500 dark:text-gray-400">{{ $f['cuando'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-filament::section>
        @endif
    </div>

</x-filament-panels::page>
