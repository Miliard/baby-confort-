<?php

namespace App\Filament\Resources\RespuestaRapidaResource\Pages;

use App\Filament\Resources\RespuestaRapidaResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditRespuestaRapida extends EditRecord
{
    protected static string $resource = RespuestaRapidaResource::class;

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
