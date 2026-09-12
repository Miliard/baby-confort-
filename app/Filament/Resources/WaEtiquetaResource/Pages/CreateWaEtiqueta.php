<?php

namespace App\Filament\Resources\WaEtiquetaResource\Pages;

use App\Filament\Resources\WaEtiquetaResource;
use Filament\Resources\Pages\CreateRecord;

class CreateWaEtiqueta extends CreateRecord
{
    protected static string $resource = WaEtiquetaResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
