<?php

namespace App\Filament\Resources\CuponResource\Pages;

use App\Filament\Resources\CuponResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCupon extends CreateRecord
{
    protected static string $resource = CuponResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['codigo'] = strtoupper(trim($data['codigo']));
        return $data;
    }
}
