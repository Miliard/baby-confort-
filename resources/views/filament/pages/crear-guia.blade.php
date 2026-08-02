<x-filament-panels::page>
    <div style="max-width:560px;margin:0 auto;width:100%">

        <form wire:submit="agregar">
            {{ $this->form }}

            <x-filament::button type="submit" size="lg" color="success"
                style="width:100%;justify-content:center;margin-top:16px;padding-top:14px;padding-bottom:14px;font-size:16px">
                ➕ Agregar a la lista
            </x-filament::button>
        </form>

        {{-- Lista acumulada --}}
        <div style="margin-top:24px">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px">
                <b style="font-size:15px">📦 Guías listas: {{ count($lista) }}</b>
                @if(count($lista))
                    <button type="button" wire:click="vaciar"
                        wire:confirm="¿Vaciar toda la lista?"
                        style="background:none;border:none;color:#dc2626;font-weight:600;font-size:13px;cursor:pointer">
                        Vaciar
                    </button>
                @endif
            </div>

            @php
                // Cuenta cuántas veces aparece cada teléfono, para avisar de repetidos.
                $conteoTel = collect($lista)
                    ->map(fn ($x) => preg_replace('/\D/', '', (string) ($x['telefono'] ?? '')))
                    ->countBy();
            @endphp
            @forelse($lista as $i => $g)
                @php
                    $tel = preg_replace('/\D/', '', (string) ($g['telefono'] ?? ''));
                    $rep = $tel !== '' && ($conteoTel[$tel] ?? 0) > 1;
                @endphp
                <div style="display:flex;gap:10px;align-items:flex-start;background:{{ $loop->first ? '#eff6ff' : '#f9fafb' }};border:1px solid {{ $rep ? '#fca5a5' : ($loop->first ? '#bfdbfe' : '#e5e7eb') }};border-radius:10px;padding:11px 13px;margin-bottom:8px">
                    <div style="flex:1;min-width:0">
                        <div style="font-weight:700;font-size:14px">
                            {{ $g['nombre'] }}
                            @if($loop->first)<span style="background:#2563eb;color:#fff;font-size:10.5px;padding:2px 7px;border-radius:999px;margin-left:6px;vertical-align:middle">última</span>@endif
                            @if($rep)<span style="background:#dc2626;color:#fff;font-size:10.5px;padding:2px 7px;border-radius:999px;margin-left:4px;vertical-align:middle">repetido</span>@endif
                        </div>
                        <div style="color:#6b7280;font-size:12.5px;margin-top:2px">
                            {{ $g['telefono'] }} · {{ $g['municipio'] }}, {{ $g['departamento'] }}
                        </div>
                        <div style="color:#6b7280;font-size:12.5px">
                            {{ $g['descripcion'] }}
                            @if($g['cobrar'] > 0)
                                · <b style="color:#059669">Cobrar ${{ number_format($g['cobrar'], 2) }}</b>
                            @else
                                · <span style="color:#6b7280">Pagado</span>
                            @endif
                        </div>
                    </div>
                    <button type="button" wire:click="quitar({{ $i }})"
                        style="background:none;border:none;color:#dc2626;font-size:18px;cursor:pointer;line-height:1;padding:2px 4px">×</button>
                </div>
            @empty
                <div style="color:#6b7280;font-size:13.5px;background:#f9fafb;border:1px dashed #e5e7eb;border-radius:10px;padding:16px;text-align:center">
                    Aún no has agregado guías. Pega una orden arriba y toca "Agregar a la lista".
                </div>
            @endforelse

            @if(count($lista))
                <x-filament::button wire:click="descargar" size="lg"
                    style="width:100%;justify-content:center;margin-top:14px;padding-top:14px;padding-bottom:14px;font-size:16px">
                    ⬇️ Descargar Excel ({{ count($lista) }})
                </x-filament::button>
                <p style="color:#6b7280;font-size:12.5px;margin-top:8px;text-align:center;line-height:1.5">
                    Sube este archivo en Sistrack → <b>Importación masiva</b> para crear todas las guías de una vez.
                </p>
            @endif
        </div>
    </div>
</x-filament-panels::page>

{{-- Si falta un campo, lo resalta y salta a él (útil en el teléfono) --}}
@push('scripts')
    <script>
        document.addEventListener('livewire:init', () => {
            Livewire.on('enfocar-campo', (e) => {
                const campo = (e && (e.campo ?? e[0]?.campo)) || null;
                if (!campo) return;
                setTimeout(() => {
                    const el = document.querySelector('[wire\\:model="data.' + campo + '"], [id$="data.' + campo + '"]')
                        || document.querySelector('[name="data.' + campo + '"]');
                    if (!el) return;
                    el.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    try { el.focus({ preventScroll: true }); } catch (err) {}
                    const caja = el.closest('.fi-fo-field-wrp') || el;
                    caja.style.transition = 'box-shadow .2s';
                    caja.style.boxShadow = '0 0 0 3px rgba(239,68,68,.45)';
                    setTimeout(() => { caja.style.boxShadow = ''; }, 1800);
                }, 60);
            });
        });
    </script>
@endpush
