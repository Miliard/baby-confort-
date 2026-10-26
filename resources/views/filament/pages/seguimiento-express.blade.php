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
            <style>
                .se-tarjetas{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}
                @media(min-width:900px){.se-tarjetas{grid-template-columns:repeat(4,minmax(0,1fr));gap:20px}}
                .se-t{background:#fff;border-radius:10px;padding:18px 20px;min-height:112px;
                      box-shadow:0 1px 3px rgba(15,23,42,.08);border:1px solid rgba(15,23,42,.05)}
                .dark .se-t{background:rgba(255,255,255,.05);border-color:rgba(255,255,255,.10);box-shadow:none}
                .se-t-l{font-size:14px;font-weight:600;color:#6b7280}
                .dark .se-t-l{color:#9ca3af}
                .se-t-n{font-size:34px;font-weight:300;line-height:1.2;margin-top:10px;color:#1f2937}
                .dark .se-t-n{color:#f3f4f6}
                .se-t-s{font-size:12px;color:#9ca3af;margin-top:4px}
                .se-t.hay .se-t-n{font-weight:500}
                /* Las guías: tabla en la computadora, tarjetas en el teléfono */
                .se-lista-t{display:none}
                @media(max-width:899px){.se-tabla{display:none}.se-lista-t{display:grid;gap:10px}}
                .se-g{background:#fff;border-radius:10px;padding:12px 14px;border:1px solid rgba(15,23,42,.06)}
                .dark .se-g{background:rgba(255,255,255,.05);border-color:rgba(255,255,255,.10)}
                .se-g-1{display:flex;justify-content:space-between;align-items:center;gap:8px}
                .se-g-n{font-weight:700;font-size:15px}
                .se-g-c{font-size:13.5px;margin-top:4px}
                .se-g-r{font-size:12px;color:#9ca3af;margin-top:3px}
                .se-est{border-radius:999px;padding:2px 9px;font-size:11.5px;font-weight:700;white-space:nowrap}
                .se-e4{background:#dcfce7;color:#15803d}.se-e3{background:#dbeafe;color:#1d4ed8}.se-e2{background:#fef3c7;color:#b45309}
            </style>

            {{-- Mismas tarjetas que el Panel de Control de Express. --}}
            <div class="se-tarjetas">
                @foreach($tarjetas as $clave => [$ico, $txt])
                    <div @class(['se-t', 'hay' => $resumen[$clave] > 0])>
                        <div class="se-t-l">{{ $txt }}</div>
                        <div class="se-t-n">{{ $resumen[$clave] }}</div>
                        @if($clave === 'POR_RECOGER')<div class="se-t-s">Marcadas como listas</div>@endif
                    </div>
                @endforeach
            </div>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Activas: <b class="text-gray-950 dark:text-white">{{ $activas }}</b> · Entregadas en 30 días: <b class="text-gray-950 dark:text-white">{{ $entregadas30 }}</b>
            </p>

            <x-filament::section>
                <x-slot name="heading">Guías de los últimos 30 días</x-slot>

                {{-- Teléfono: una tarjeta por guía --}}
                <div class="se-lista-t">
                    @foreach($filas as $f)
                        <div class="se-g">
                            <div class="se-g-1">
                                <span class="se-g-n">{{ $f['guia'] }}</span>
                                <span class="se-est se-e{{ min(4, max(2, $f['etapa'])) }}">{{ $f['estado'] }}</span>
                            </div>
                            @if($f['cliente'])<div class="se-g-c">{{ $f['cliente'] }}</div>@endif
                            <div class="se-g-r">{{ $f['repartidor'] ?: 'Sin repartidor' }} · {{ $f['cuando'] }}</div>
                        </div>
                    @endforeach
                </div>

                {{-- Computadora: la tabla --}}
                <div class="se-tabla overflow-x-auto">
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
                                    <td class="py-1.5 pr-3"><span class="se-est se-e{{ min(4, max(2, $f['etapa'])) }}">{{ $f['estado'] }}</span></td>
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
