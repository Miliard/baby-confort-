<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use Filament\Actions;
use Filament\Resources\Pages\ContentTabPosition;
use Filament\Resources\Pages\EditRecord;

/**
 * Editar un producto: dos pestañas arriba.
 *
 *   [ 🏷 Tallas, precios y existencia ]  [ 📝 Datos y fotos ]
 *
 * Se abre en Tallas, que es lo que se cambia casi siempre (precio y
 * existencia). Los datos del producto, que se tocan poco, quedan en la otra.
 * Guardar queda fijo abajo de la pantalla: no hay que bajar a buscarlo.
 */
class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;

    public static bool $formActionsAreSticky = true;

    public function mount(int | string $record): void
    {
        parent::mount($record);

        // Primera vez que se abre (sin ?activeRelationManager en la dirección):
        // directo a las tallas.
        if ($this->activeRelationManager === null && count($this->getRelationManagers())) {
            $this->activeRelationManager = (string) array_key_first($this->getRelationManagers());
        }
    }

    public function hasCombinedRelationManagerTabsWithContent(): bool
    {
        return true;
    }

    public function getContentTabLabel(): ?string
    {
        return 'Datos y fotos';
    }

    public function getContentTabIcon(): ?string
    {
        return 'heroicon-o-pencil-square';
    }

    public function getContentTabPosition(): ?ContentTabPosition
    {
        return ContentTabPosition::After;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('verEnTienda')
                ->label('Ver en la tienda')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->color('gray')
                ->url(fn () => route('store.show', $this->record))
                ->openUrlInNewTab(),
            Actions\DeleteAction::make(),
        ];
    }
}
