<?php

namespace App\Filament\Resources\RevendedorResource\Pages;

use App\Filament\Resources\RevendedorResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditRevendedor extends EditRecord
{
    protected static string $resource = RevendedorResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (! empty($data['codigo'])) {
            $data['codigo'] = strtoupper(trim($data['codigo']));
        }
        return $data;
    }
}
