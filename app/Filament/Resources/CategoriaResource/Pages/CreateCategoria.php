<?php

namespace App\Filament\Resources\CategoriaResource\Pages;

use App\Filament\Resources\CategoriaResource;
use App\Models\Categoria;
use Filament\Resources\Pages\CreateRecord;

class CreateCategoria extends CreateRecord
{
    protected static string $resource = CategoriaResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['slug'] = Categoria::generarSlug($data['nombre'] ?? '');
        return $data;
    }
}
