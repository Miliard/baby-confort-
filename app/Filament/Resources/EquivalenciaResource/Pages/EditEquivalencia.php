<?php

namespace App\Filament\Resources\EquivalenciaResource\Pages;

use App\Filament\Resources\EquivalenciaResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditEquivalencia extends EditRecord
{
    protected static string $resource = EquivalenciaResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
