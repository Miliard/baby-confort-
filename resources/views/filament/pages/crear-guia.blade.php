<x-filament-panels::page>
    <div class="mx-auto w-full max-w-xl">

        <form wire:submit="agregar">
            {{ $this->form }}

            <x-filament::button type="submit" size="lg" color="success" class="mt-4 w-full justify-center">
                ➕ Agregar a la lista
            </x-filament::button>
        </form>

        {{-- Lista acumulada (guardada en la base: no se pierde) --}}
        <div class="mt-8">
            <div class="mb-3 flex items-center justify-between">
                <h3 class="text-base font-bold text-gray-950 dark:text-white">
                    📦 Guías listas
                    <span class="ml-1 rounded-full bg-primary-600 px-2 py-0.5 text-xs font-bold text-white">
                        {{ count($lista) }}
                    </span>
                </h3>
                @if(count($lista))
                    <x-filament::link color="danger" tag="button" wire:click="vaciar" wire:confirm="¿Vaciar toda la lista?" size="sm">
                        Vaciar
                    </x-filament::link>
                @endif
            </div>

            @php
                // Cuenta cuántas veces aparece cada teléfono, para avisar de repetidos.
                $conteoTel = collect($lista)
                    ->map(fn ($x) => preg_replace('/\D/', '', (string) ($x['telefono'] ?? '')))
                    ->countBy();
            @endphp

            <div class="space-y-2">
                @forelse($lista as $g)
                    @php
                        $tel = preg_replace('/\D/', '', (string) ($g['telefono'] ?? ''));
                        $rep = $tel !== '' && ($conteoTel[$tel] ?? 0) > 1;
                    @endphp

                    <div @class([
                        'flex items-start gap-3 rounded-xl border p-3 shadow-sm transition',
                        'bg-white dark:bg-gray-900',
                        'border-danger-300 dark:border-danger-500/50'   => $rep,
                        'border-primary-300 dark:border-primary-500/50' => $loop->first && ! $rep,
                        'border-gray-200 dark:border-white/10'          => ! $loop->first && ! $rep,
                    ])>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-1.5">
                                <span class="truncate text-sm font-semibold text-gray-950 dark:text-white">
                                    {{ $g['nombre'] }}
                                </span>
                                @if($loop->first)
                                    <x-filament::badge color="primary" size="xs">última</x-filament::badge>
                                @endif
                                @if($rep)
                                    <x-filament::badge color="danger" size="xs">repetido</x-filament::badge>
                                @endif
                            </div>

                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                {{ $g['telefono'] }} · {{ $g['municipio'] }}, {{ $g['departamento'] }}
                            </p>

                            <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                {{ $g['descripcion'] }}
                                @if($g['cobrar'] > 0)
                                    · <span class="font-semibold text-success-600 dark:text-success-400">Cobrar ${{ number_format($g['cobrar'], 2) }}</span>
                                @else
                                    · <span class="font-medium">Pagado</span>
                                @endif
                            </p>
                        </div>

                        <x-filament::icon-button
                            icon="heroicon-m-x-mark"
                            color="danger"
                            size="sm"
                            wire:click="quitar({{ $g['id'] ?? 0 }})"
                            label="Quitar de la lista"
                        />
                    </div>
                @empty
                    <div class="rounded-xl border border-dashed border-gray-300 p-6 text-center text-sm text-gray-500 dark:border-white/10 dark:text-gray-400">
                        Aún no has agregado guías.<br>
                        Pegá una orden arriba y tocá <span class="font-semibold">"Agregar a la lista"</span>.
                    </div>
                @endforelse
            </div>

            @if(count($lista))
                <x-filament::button wire:click="descargar" size="lg" icon="heroicon-m-arrow-down-tray"
                    class="mt-4 w-full justify-center">
                    Descargar Excel ({{ count($lista) }})
                </x-filament::button>

                <p class="mt-2 text-center text-xs text-gray-500 dark:text-gray-400">
                    Subí este archivo en Sistrack → <span class="font-semibold">Importación masiva</span>
                    para crear todas las guías de una vez.
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
