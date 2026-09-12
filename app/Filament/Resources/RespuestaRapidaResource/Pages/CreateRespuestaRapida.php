<?php

namespace App\Filament\Resources\RespuestaRapidaResource\Pages;

use App\Filament\Resources\RespuestaRapidaResource;
use Filament\Resources\Pages\CreateRecord;

class CreateRespuestaRapida extends CreateRecord
{
    protected static string $resource = RespuestaRapidaResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
