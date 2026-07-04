<x-filament-panels::page>
    <form wire:submit="guardar">
        {{ $this->form }}
        <div style="margin-top:1rem">
            <x-filament::button type="submit">Guardar</x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
