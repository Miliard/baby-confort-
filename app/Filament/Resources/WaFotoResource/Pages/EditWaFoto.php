<?php

namespace App\Filament\Resources\WaFotoResource\Pages;

use App\Filament\Resources\WaFotoResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditWaFoto extends EditRecord
{
    protected static string $resource = WaFotoResource::class;

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
