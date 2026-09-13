<?php

namespace App\Filament\Resources\WaFotoResource\Pages;

use App\Filament\Resources\WaFotoResource;
use Filament\Resources\Pages\CreateRecord;

class CreateWaFoto extends CreateRecord
{
    protected static string $resource = WaFotoResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
