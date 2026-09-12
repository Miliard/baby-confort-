<?php

namespace App\Filament\Resources\WaEtiquetaResource\Pages;

use App\Filament\Resources\WaEtiquetaResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditWaEtiqueta extends EditRecord
{
    protected static string $resource = WaEtiquetaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
