<?php

namespace App\Filament\Resources\EquivalenciaResource\Pages;

use App\Filament\Resources\EquivalenciaResource;
use Filament\Resources\Pages\CreateRecord;

class CreateEquivalencia extends CreateRecord
{
    protected static string $resource = EquivalenciaResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
