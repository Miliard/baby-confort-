<x-filament-panels::page>
    @php
        $r = $this->resumen;
        $s = $this->saldoGuias;
        $entro = $r['depositado'] + $r['transferido'];
        $colGuia = ! $s['hay'] ? null : ($s['restantes'] <= 50 ? '#dc2626' : ($s['restantes'] <= 150 ? '#d97706' : null));
    @endphp

    {{-- Estilos propios: el CSS del panel no trae las rejillas que necesito --}}
    <style>
        .bc-kpis{display:grid;gap:12px;grid-template-columns:1fr}
        .bc-cols{display:grid;gap:12px;grid-template-columns:1fr;align-items:start}
        .bc-2{display:grid;gap:8px;grid-template-columns:1fr}
        .bc-dias{display:flex;flex-wrap:wrap;gap:6px;align-items:center}
        .bc-card{border-radius:16px;border:1px solid rgba(0,0,0,.08);background:#fff;padding:15px}
        .dark .bc-card{border-color:rgba(255,255,255,.10);background:#18202f}
        .bc-et{font-size:11px;text-transform:uppercase;letter-spacing:.4px;font-weight:600;opacity:.65}
        .bc-n{font-size:29px;font-weight:800;letter-spacing:-.6px;margin-top:2px;line-height:1.1}
        .bc-sub{font-size:11.5px;opacity:.65;margin-top:4px;line-height:1.4}
        .bc-barra{height:6px;border-radius:99px;background:rgba(125,140,160,.25);overflow:hidden;margin-top:8px}
        .bc-barra i{display:block;height:100%;border-radius:99px}
        .bc-campo{width:100%;border-radius:9px;padding:7px 10px;font-size:13px;
                  border:1px solid rgba(0,0,0,.15);background:#fff;color:inherit}
        .dark .bc-campo{border-color:rgba(255,255,255,.12);background:rgba(255,255,255,.04)}
        .bc-lbl{display:block;font-size:11px;opacity:.65;margin-bottom:3px}
        .bc-pend{border-radius:11px;padding:10px;border:1px solid #d97706;background:rgba(217,119,6,.07)}
        .bc-tabla{width:100%;font-size:13px;border-collapse:collapse}
        .bc-tabla th{text-align:left;font-size:10.5px;text-transform:uppercase;opacity:.6;
                     padding:6px 8px 6px 0;border-bottom:1px solid rgba(125,140,160,.25)}
        .bc-tabla td{padding:5px 8px 5px 0;border-bottom:1px solid rgba(125,140,160,.12)}
        @media(min-width:640px){ .bc-kpis{grid-template-columns:repeat(2,1fr)} .bc-2{grid-template-columns:1fr 1fr} }
        @media(min-width:1080px){ .bc-kpis{grid-template-columns:repeat(4,1fr)} .bc-cols{grid-template-columns:360px 1fr} }
    </style>

    @if(count($this->fechas) > 0)
        {{-- ── Barra de días ── --}}
        <div class="bc-dias">
            <span style="font-size:12px;opacity:.6;margin-right:2px">Día:</span>
            @foreach($this->fechas as $f)
                <x-filament::button size="xs" :color="$f === $fecha ? 'primary' : 'gray'"
                    wire:click="verFecha('{{ $f }}')">
                    {{ \Illuminate\Support\Carbon::parse($f)->format('d/m') }}
                </x-filament::button>
            @endforeach
        </div>

        {{-- ── Las cuatro tarjetas ── --}}
        <div class="bc-kpis">

            <div class="bc-card" style="background: {{ $r['resultado'] >= 0 ? '#16a34a' : '#dc2626' }}; border-color: transparent; color:#fff">
                <div class="bc-et" style="opacity:.85">Resultado {{ \Illuminate\Support\Carbon::parse($fecha)->format('d/m') }}</div>
                <div class="bc-n">${{ number_format($r['resultado'], 2) }}</div>
                <div class="bc-sub" style="opacity:.9">
                    {{ $r['bultos'] }} bultos · {{ $r['guias'] }} guías
                    @if($r['proveedor'] == 0)<span style="display:block;font-weight:700">⚠️ falta el proveedor</span>@endif
                </div>
            </div>

            <div class="bc-card">
                <div class="bc-et">Entró</div>
                <div class="bc-n">${{ number_format($entro, 2) }}</div>
                <div class="bc-sub">
                    Express cobró ${{ number_format($r['cobrado'], 2) }} y se quedó ${{ number_format($r['comision'], 2) }}
                    @if($r['transferido'] > 0)<span style="display:block">+ ${{ number_format($r['transferido'], 2) }} por transferencia</span>@endif
                </div>
            </div>

            <div class="bc-card" @if($colGuia) style="border-color: {{ $colGuia }}; background: {{ $colGuia }}1a" @endif>
                <div class="bc-et">Guías restantes</div>
                @if($s['hay'])
                    <div class="bc-n">{{ $s['restantes'] }}</div>
                    <div class="bc-barra">
                        <i style="width: {{ $s['porcentaje'] }}%; background: {{ $s['restantes'] <= 50 ? '#dc2626' : ($s['restantes'] <= 150 ? '#d97706' : '#16a34a') }}"></i>
                    </div>
                    <div class="bc-sub">
                        {{ $s['usadas'] }} de {{ $s['compradas'] }} usadas · ${{ number_format($s['costoBulto'], 2) }} c/u
                        @if($s['restantes'] <= 50)<span style="display:block;font-weight:700;color:#dc2626">¡Comprá más!</span>@endif
                    </div>
                @else
                    <div class="bc-sub" style="margin-top:8px">Cargá tu paquete de guías en el cuadro <b>3</b> de la izquierda.</div>
                @endif
            </div>

            <div class="bc-card" @if($this->pendientes->count() > 0) style="border-color:#d97706; background:rgba(217,119,6,.10)" @endif>
                <div class="bc-et">Sin monto</div>
                <div class="bc-n">{{ $this->pendientes->count() }}</div>
                <div class="bc-sub">
                    @if($this->pendientes->count() > 0)
                        Falta decir qué pasó con {{ $this->pendientes->count() === 1 ? 'este' : 'estos' }}
                    @else
                        Todo el día está explicado ✅
                    @endif
                </div>
            </div>
        </div>
    @endif

    {{-- ── Dos columnas ── --}}
    <div class="bc-cols">

        {{-- Izquierda: meter datos --}}
        <div style="display:flex;flex-direction:column;gap:12px">

            <x-filament::section compact collapsible :collapsed="count($this->fechas) > 0">
                <x-slot name="heading">1 · Pegar liquidación</x-slot>

                <textarea wire:model="pegado" rows="3" class="bc-campo"
                    style="font-family:ui-monospace,Consolas,monospace;font-size:11px;resize:vertical"
                    placeholder="13-ago&#9;BABY CONFORT -200&#9;5370975&#9;Luz Villatoro&#9;Corinto&#9;$ 52,00&#9;$ 1,04&#9;$ 50,96"></textarea>

                <div style="margin-top:8px">
                    <x-filament::button size="sm" wire:click="procesar" icon="heroicon-o-arrow-down-tray">
                        Procesar
                    </x-filament::button>
                </div>
            </x-filament::section>

            @if(count($this->fechas) > 0)
                <x-filament::section compact>
                    <x-slot name="heading">2 · Lo que salió ese día</x-slot>

                    <div class="bc-2">
                        <div>
                            <label class="bc-lbl">Proveedor ($)</label>
                            <input type="number" step="0.01" min="0" wire:model="proveedor" class="bc-campo">
                        </div>
                        <div>
                            <label class="bc-lbl">Otros gastos ($)</label>
                            <input type="number" step="0.01" min="0" wire:model="gastos" class="bc-campo">
                        </div>
                    </div>
                    <div style="margin-top:8px">
                        <input type="text" wire:model="nota" placeholder="Nota (opcional)" class="bc-campo">
                    </div>

                    <div style="margin-top:8px;display:flex;flex-wrap:wrap;gap:6px;align-items:flex-end">
                        <div>
                            <label class="bc-lbl">Día del depósito de esta fecha</label>
                            <input type="date" wire:model="fechaDeposito" class="bc-campo" style="width:150px">
                        </div>
                        <x-filament::button size="xs" color="gray" wire:click="fijarDeposito">Fijar</x-filament::button>
                    </div>

                    <div style="margin-top:8px">
                        <x-filament::button size="sm" wire:click="guardarDia" icon="heroicon-o-check">
                            Guardar
                        </x-filament::button>
                    </div>
                    <p style="margin-top:6px;font-size:11px;opacity:.55">Si te equivocaste, corregí el número y guardá otra vez.</p>
                </x-filament::section>
            @endif

            <x-filament::section compact collapsible :collapsed="$s['hay']">
                <x-slot name="heading">3 · Comprar guías</x-slot>

                <div style="display:flex;flex-wrap:wrap;gap:8px;align-items:flex-end">
                    <div>
                        <label class="bc-lbl">Cantidad</label>
                        <input type="number" min="1" wire:model="bloqueCantidad" placeholder="500" class="bc-campo" style="width:90px">
                    </div>
                    <div>
                        <label class="bc-lbl">Costo ($)</label>
                        <input type="number" step="0.01" min="0" wire:model="bloqueCosto" placeholder="1400" class="bc-campo" style="width:105px">
                    </div>
                    <x-filament::button size="sm" wire:click="agregarBloque" icon="heroicon-o-plus">Cargar</x-filament::button>
                </div>

                @if($this->bloques->count() > 0)
                    <div style="margin-top:10px;display:flex;flex-direction:column;gap:5px">
                        @foreach($this->bloques as $b)
                            <div wire:key="blo-{{ $b->id }}"
                                 style="display:flex;justify-content:space-between;align-items:center;gap:8px;
                                        font-size:12px;padding:6px 8px;border-radius:8px;
                                        border:1px solid rgba(125,140,160,.25)">
                                <span>
                                    <b>{{ $b->cantidad }}</b> guías · ${{ number_format($b->costo, 2) }}
                                    <span style="opacity:.6">
                                        ({{ number_format($b->costo / max(1, $b->cantidad), 2) }} c/u ·
                                        {{ $b->fecha->format('d/m/Y') }})
                                    </span>
                                </span>
                                <x-filament::icon-button icon="heroicon-m-trash" size="xs" color="danger"
                                    wire:click="borrarBloque({{ $b->id }})"
                                    wire:confirm="¿Borrar este bloque de guías?" label="Borrar bloque" />
                            </div>
                        @endforeach
                    </div>
                @endif
            </x-filament::section>
        </div>

        {{-- Derecha: ver --}}
        <div style="display:flex;flex-direction:column;gap:12px">

            @if(count($this->fechas) === 0)
                <x-filament::section>
                    <div style="padding:48px 0;text-align:center;font-size:13px;opacity:.6">
                        Pegá tu primera liquidación en el cuadro de la izquierda. 👈
                    </div>
                </x-filament::section>
            @else
                @if($this->pendientes->count() > 0)
                    <x-filament::section compact>
                        <x-slot name="heading">⚠️ Clientes a los que no les aparece el monto</x-slot>
                        <x-slot name="description">Decime qué pasó con cada uno para que la cuenta cierre.</x-slot>

                        <div class="bc-2">
                            @foreach($this->pendientes as $p)
                                <div wire:key="pend-{{ $p->id }}" x-data="{ monto: '' }" class="bc-pend">
                                    <div style="font-weight:700;font-size:13px">{{ $p->nombre }}</div>
                                    <div style="font-size:11px;opacity:.6;margin:1px 0 8px">Guía {{ $p->orden }} · {{ $p->zona }}</div>

                                    <div style="display:flex;flex-wrap:wrap;gap:5px;align-items:center">
                                        <input type="number" step="0.01" min="0" x-model="monto" placeholder="$ transferido"
                                            class="bc-campo" style="width:112px;padding:4px 8px;font-size:11.5px">
                                        <x-filament::button size="xs" color="success"
                                            x-on:click="$wire.marcarCaso({{ $p->id }}, 'transferencia', monto)">Transferido</x-filament::button>
                                        <x-filament::button size="xs" color="gray"
                                            wire:click="marcarCaso({{ $p->id }}, 'bulto_extra')">Bulto extra</x-filament::button>
                                        <x-filament::button size="xs" color="danger"
                                            wire:click="marcarCaso({{ $p->id }}, 'devolucion')">Devuelto</x-filament::button>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </x-filament::section>
                @endif

                {{-- ── Remuneración AIWIBI ── --}}
                @php $m = $this->remuneracion; @endphp
                <x-filament::section compact>
                    <x-slot name="heading">Remuneración AIWIBI</x-slot>
                    <x-slot name="description">Esa plata no es tuya: se cobra al entregar y hay que devolverla.</x-slot>

                    <div style="display:flex;flex-wrap:wrap;gap:6px;align-items:flex-end;margin-bottom:10px">
                        <div>
                            <label class="bc-lbl">Desde</label>
                            <input type="date" wire:model.live="remDesde" class="bc-campo" style="width:145px">
                        </div>
                        <div>
                            <label class="bc-lbl">Hasta</label>
                            <input type="date" wire:model.live="remHasta" class="bc-campo" style="width:145px">
                        </div>
                        <x-filament::button size="xs" color="gray" wire:click="remPeriodo('dia')">Este día</x-filament::button>
                        <x-filament::button size="xs" color="gray" wire:click="remPeriodo('semana')">Semana</x-filament::button>
                        <x-filament::button size="xs" color="gray" wire:click="remPeriodo('mes')">Mes</x-filament::button>
                        <x-filament::button size="xs" color="gray" wire:click="remPeriodo('anterior')">Mes pasado</x-filament::button>
                        <x-filament::button size="xs" color="gray" wire:click="remPeriodo('todo')">Todo</x-filament::button>

                        <div style="margin-left:auto;display:flex;gap:6px">
                            <div>
                                <label class="bc-lbl">Comisión %</label>
                                <input type="number" step="0.01" min="0" wire:model.live.debounce.600ms="remComision"
                                       class="bc-campo" style="width:80px">
                            </div>
                            <div>
                                <label class="bc-lbl">Por envío $</label>
                                <input type="number" step="0.01" min="0" wire:model.live.debounce.600ms="remPorEnvio"
                                       class="bc-campo" style="width:85px">
                            </div>
                        </div>
                    </div>

                    @if($m['envios'] === 0)
                        <div style="padding:18px 0;text-align:center;font-size:12.5px;opacity:.6">
                            No hay entregas de AIWIBI en ese período.
                        </div>
                    @else
                        <div class="bc-2" style="align-items:start">
                            <div class="bc-card" style="background:#16a34a;border-color:transparent;color:#fff">
                                <div class="bc-et" style="opacity:.85">Total a pagar</div>
                                <div class="bc-n">${{ number_format($m['aPagar'], 2) }}</div>
                                <div class="bc-sub" style="opacity:.9">
                                    {{ $m['envios'] }} envíos
                                    @if($m['sinCobro'] > 0) · {{ $m['sinCobro'] }} sin cobro @endif
                                    @if($m['devueltos'] > 0) · {{ $m['devueltos'] }} devueltos no cuentan @endif
                                </div>
                            </div>

                            <div style="font-size:12.5px">
                                <div style="display:flex;justify-content:space-between;padding:5px 0;border-bottom:1px solid rgba(125,140,160,.15)">
                                    <span>Cobrado</span><b>${{ number_format($m['cobrado'], 2) }}</b>
                                </div>
                                <div style="display:flex;justify-content:space-between;padding:5px 0;border-bottom:1px solid rgba(125,140,160,.15)">
                                    <span>Comisión {{ rtrim(rtrim(number_format($m['comisionPct'], 2), '0'), '.') }}%</span>
                                    <b style="color:#dc2626">− ${{ number_format($m['comision'], 2) }}</b>
                                </div>
                                <div style="display:flex;justify-content:space-between;padding:5px 0;border-bottom:1px solid rgba(125,140,160,.15)">
                                    <span>{{ $m['envios'] }} × ${{ number_format($m['porEnvio'], 2) }}</span>
                                    <b style="color:#dc2626">− ${{ number_format($m['descuento'], 2) }}</b>
                                </div>
                                <div style="display:flex;justify-content:space-between;padding:7px 0 0">
                                    <b>Total</b><b style="color:#16a34a">${{ number_format($m['aPagar'], 2) }}</b>
                                </div>
                            </div>
                        </div>

                        <div style="margin-top:10px;display:flex;flex-wrap:wrap;gap:8px"
                             x-data="{ ok: false, txt: @js($this->remTexto) }">
                            <x-filament::button size="sm"
                                x-on:click="navigator.clipboard.writeText(txt).then(() => { ok = true; setTimeout(() => ok = false, 2500) })">
                                <span x-text="ok ? '✅ ¡Copiado! Pegalo en el chat' : '📋 Copiar para WhatsApp'"></span>
                            </x-filament::button>

                            <x-filament::button size="sm" color="gray" tag="a" target="_blank"
                                icon="heroicon-o-printer"
                                :href="route('remuneracion.imprimir', [
                                    'desde'    => $remDesde ?: null,
                                    'hasta'    => $remHasta ?: null,
                                    'comision' => $remComision,
                                    'porEnvio' => $remPorEnvio,
                                ])">
                                Descargar PDF
                            </x-filament::button>
                        </div>

                        <details style="margin-top:10px">
                            <summary style="cursor:pointer;font-size:12.5px;opacity:.7">
                                Ver las {{ $m['envios'] }} entregas
                            </summary>
                            <div style="max-height:300px;overflow:auto;margin-top:8px">
                                <table class="bc-tabla">
                                    <thead>
                                        <tr><th>Fecha</th><th>Cliente</th><th>Zona</th><th style="text-align:right">Monto</th></tr>
                                    </thead>
                                    <tbody>
                                        @foreach($m['filas'] as $f)
                                            <tr>
                                                <td style="white-space:nowrap;opacity:.6">{{ $f->fecha->format('d/m') }}</td>
                                                <td>{{ $f->nombre }}<span style="display:block;font-size:11px;opacity:.55">{{ $f->orden }}</span></td>
                                                <td style="opacity:.6">{{ $f->zona }}</td>
                                                <td style="text-align:right;font-weight:600">${{ number_format($f->monto, 2) }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </details>
                    @endif
                </x-filament::section>

                <div class="bc-2">
                    @if(count($this->depositos) > 0)
                        <x-filament::section compact collapsible collapsed>
                            <x-slot name="heading">Depósitos recibidos</x-slot>
                            <div style="display:flex;flex-direction:column;gap:6px">
                                @foreach($this->depositos as $d)
                                    <div class="bc-card" style="padding:9px 11px;display:flex;justify-content:space-between;gap:8px;flex-wrap:wrap;align-items:center">
                                        <div>
                                            <div style="font-weight:700;font-size:13px">
                                                {{ \Illuminate\Support\Carbon::parse($d['fecha'])->format('d/m/Y') }}
                                            </div>
                                            <div style="font-size:11px;opacity:.6">
                                                {{ $d['bultos'] }} bultos · entregas del {{ implode(', ', $d['entregas']) }}
                                            </div>
                                        </div>
                                        <div style="font-weight:800;color:#16a34a">${{ number_format($d['monto'], 2) }}</div>
                                    </div>
                                @endforeach
                            </div>
                        </x-filament::section>
                    @endif

                    <x-filament::section compact collapsible collapsed>
                        <x-slot name="heading">Los {{ $this->entregas->count() }} bultos del día</x-slot>
                        <x-slot name="description">Corregí o borrá lo que quedó mal.</x-slot>

                        <div style="max-height:320px;overflow:auto">
                            <table class="bc-tabla">
                                <thead>
                                    <tr><th>Cliente</th><th style="text-align:right">Cobrado</th><th>Estado</th><th></th></tr>
                                </thead>
                                <tbody>
                                    @foreach($this->entregas as $e)
                                        <tr wire:key="ent-{{ $e->id }}" @if($e->aiwibi) style="opacity:.5" @endif>
                                            <td>
                                                {{ $e->nombre }}
                                                <span style="display:block;font-size:11px;opacity:.55">{{ $e->orden }}</span>
                                            </td>
                                            <td style="text-align:right;font-weight:600 @if($e->monto <= 0);opacity:.5 @endif">
                                                ${{ number_format($e->monto, 2) }}
                                            </td>
                                            <td style="font-size:11px;opacity:.7">
                                                @if($e->aiwibi) AIWIBI
                                                @elseif($e->caso === 'transferencia') Transferido ${{ number_format($e->transferido, 2) }}
                                                @elseif($e->caso) {{ \App\Models\ExpressEntrega::CASOS[$e->caso] ?? $e->caso }}
                                                @endif
                                            </td>
                                            <td style="text-align:right;white-space:nowrap">
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

                        <div style="margin-top:10px">
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
