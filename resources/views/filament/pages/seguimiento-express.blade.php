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
                <p class="text-sm text-gray-600 dark:text-gray-300">Falta la variable <b>TNT_API_KEY</b> en Railway. Mientras tanto, use el botón del marcador (abajo).</p>
            @endif
        </x-filament::section>


        {{-- ─────────── EL BOTÓN ─────────── --}}
        <x-filament::section>
            <x-slot name="heading">Plan B: botón del marcador (solo si la API falla) — 1. Instalarlo</x-slot>

            <p class="text-sm text-gray-600 dark:text-gray-300">
                <b>Arrastre</b> este botón a la <b>barra de marcadores</b> de su navegador
                (si no la ve, presione <b>Ctrl + Shift + B</b>).
            </p>

            <div class="mt-4">
                <a id="boton-express" href="#"
                   onclick="event.preventDefault(); alert('No lo toque aquí: arrástrelo a la barra de marcadores. Después tóquelo con Express abierto.');"
                   class="inline-flex items-center gap-2 rounded-xl bg-primary-600 px-5 py-3 text-base font-bold text-white shadow hover:bg-primary-500"
                   draggable="true">📦 Actualizar guías</a>
            </div>

            <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                El botón lleva una clave propia de su panel. No lo comparta.
            </p>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Plan B — 2. Usarlo</x-slot>
            <ol class="list-decimal space-y-1 pl-5 text-sm text-gray-600 dark:text-gray-300">
                <li>Abra <a href="https://xpress.facilitoerp.com/tnt" target="_blank" rel="noopener" class="font-semibold text-primary-600 hover:underline">el sistema de Express</a> con su usuario.</li>
                <li>Toque <b>📦 Actualizar guías</b> en la barra de marcadores.</li>
                <li>En unos segundos sale el resumen. Sus clientes ya ven el estado nuevo al rastrear, y los entregados pasan solos a la etiqueta de Entregados.</li>
            </ol>
            <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">
                Última actualización: <b class="text-gray-950 dark:text-white">{{ $ultima ?? 'todavía ninguna' }}</b>
            </p>
        </x-filament::section>

        {{-- ─────────── RESUMEN ─────────── --}}
        @if(! $hayTabla)
            <x-filament::section>
                <p class="text-sm text-danger-600">Falta crear la tabla de estados. Se crea sola al desplegar (migración).</p>
            </x-filament::section>
        @elseif($filas->isNotEmpty())
            <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
                @foreach([1 => ['📝','Creadas'], 2 => ['🏬','En bodega'], 3 => ['🚚','En ruta'], 4 => ['✅','Entregadas']] as $n => [$ico, $txt])
                    <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-white/10 dark:bg-white/5">
                        <div class="text-sm text-gray-500 dark:text-gray-400">{{ $ico }} {{ $txt }}</div>
                        <div class="mt-1 text-2xl font-bold text-gray-950 dark:text-white">{{ $resumen[$n] }}</div>
                    </div>
                @endforeach
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

    <script>
    (() => {
        // Código que corre en la página de Express cuando se toca el marcador.
        // La sesión de Express nunca sale de ese navegador: solo se manda a
        // Baby-Confort el número de guía, el estado y el repartidor.
        const B = @js($base), K = @js($clave);
        const codigo = `(async()=>{const B=${JSON.stringify(B)},K=${JSON.stringify(K)};
if(!/facilitoerp\\.com$/.test(location.host)){alert('Abra primero el sistema de Express (xpress.facilitoerp.com) y vuelva a tocar este botón.');return;}
const tk=localStorage.getItem('authToken');if(!tk){alert('Inicie sesión en Express y vuelva a tocar el botón.');return;}
const caja=document.createElement('div');caja.style.cssText='position:fixed;top:16px;right:16px;z-index:999999;background:#1e3a5f;color:#fff;padding:14px 18px;border-radius:12px;font:600 14px system-ui;box-shadow:0 6px 24px rgba(0,0,0,.3)';caja.textContent='📦 Baby-Confort: buscando guías…';document.body.appendChild(caja);
const fin=(t)=>{caja.remove();alert(t);};
const post=async(u,d)=>{const r=await fetch(B+u,{method:'POST',headers:{'content-type':'text/plain'},body:JSON.stringify(Object.assign({k:K},d))});if(!r.ok)throw new Error('Baby-Confort respondió '+r.status);return r.json();};
try{const p=await post('/express/pendientes',{});const codes=p.guias||[];
if(!codes.length){fin('📦 Baby-Confort: no hay guías pendientes de seguimiento.');return;}
const rows=[],missing=[];
for(let i=0;i<codes.length;i+=40){caja.textContent='📦 Consultando a Express… '+Math.min(i+40,codes.length)+' de '+codes.length;
const r=await fetch('/api/trpc/tnt.shipment.lookupByCodes?batch=1',{method:'POST',headers:{'content-type':'application/json',Authorization:'Bearer '+tk},body:JSON.stringify({0:{json:{codes:codes.slice(i,i+40)}}})});
if(r.status===401||r.status===403){fin('Su sesión de Express venció. Vuelva a iniciar sesión y toque el botón otra vez.');return;}
if(!r.ok){fin('Express no respondió ('+r.status+'). Intente en un momento.');return;}
const d=(await r.json())[0].result.data.json;
(d.rows||[]).forEach(x=>{const o=x.order||{};rows.push({guia:x.code,estado:o.status,entregado:o.deliveredAt,cambio:o.updatedAt,repartidor:o.driver?o.driver.name:null,pago:o.paymentStatus});});
(d.missing||[]).forEach(m=>missing.push(m));}
caja.textContent='📦 Guardando en Baby-Confort…';
const s=await post('/express/estados',{rows,missing});
fin('✅ Baby-Confort actualizado\\n\\n'+s.actualizadas+' guías revisadas\\n✅ Entregadas: '+s.entregadas+'\\n🚚 En ruta: '+s.en_ruta+'\\n🏬 En bodega: '+s.bodega+(s.no_encontradas?'\\n❓ No están en Express: '+s.no_encontradas:''));
}catch(e){fin('No se pudo actualizar: '+e.message);}})()`;

        const a = document.getElementById('boton-express');
        if (a) a.setAttribute('href', 'javascript:' + encodeURIComponent(codigo.replace(/\n/g, '')));
    })();
    </script>
</x-filament-panels::page>
