<?php

namespace App\Filament\Resources\RevendedorResource\Pages;

use App\Filament\Resources\RevendedorResource;
use App\Models\Revendedor;
use Filament\Resources\Pages\CreateRecord;

class CreateRevendedor extends CreateRecord
{
    protected static string $resource = RevendedorResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $codigo = trim((string) ($data['codigo'] ?? ''));
        $data['codigo'] = $codigo !== ''
            ? strtoupper($codigo)
            : Revendedor::generarCodigo($data['nombre'] ?? '');
        return $data;
    }
}
