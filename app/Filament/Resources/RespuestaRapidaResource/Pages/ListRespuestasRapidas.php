<?php

namespace App\Filament\Resources\RespuestaRapidaResource\Pages;

use App\Filament\Resources\RespuestaRapidaResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListRespuestasRapidas extends ListRecords
{
    protected static string $resource = RespuestaRapidaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Nueva respuesta'),
        ];
    }
}
