<?php

namespace App\Filament\Resources\RevendedorResource\Pages;

use App\Filament\Resources\RevendedorResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListRevendedores extends ListRecords
{
    protected static string $resource = RevendedorResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()->label('Nuevo revendedor')];
    }
}
