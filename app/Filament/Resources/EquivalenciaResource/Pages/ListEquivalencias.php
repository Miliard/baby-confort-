<?php

namespace App\Filament\Resources\EquivalenciaResource\Pages;

use App\Filament\Resources\EquivalenciaResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListEquivalencias extends ListRecords
{
    protected static string $resource = EquivalenciaResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()->label('Nueva equivalencia')];
    }
}
