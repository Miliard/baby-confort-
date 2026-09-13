<?php

namespace App\Filament\Resources\WaFotoResource\Pages;

use App\Filament\Resources\WaFotoResource;
use App\Models\WaFoto;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateWaFoto extends CreateRecord
{
    protected static string $resource = WaFotoResource::class;

    /**
     * Guarda una foto por cada archivo subido.
     *
     * El formulario deja arrastrar varias juntas, pero cada una tiene que ser
     * un renglón propio: en el chat se manda de a una, y cada una puede llevar
     * su nombre y su pie.
     *
     * Si se subieron varias con el mismo nombre, se numeran solas para poder
     * distinguirlas después en la lista.
     */
    protected function handleRecordCreation(array $data): Model
    {
        $rutas = array_values(array_filter((array) ($data['ruta'] ?? [])));

        if (empty($rutas)) {
            // No debería pasar porque el campo es obligatorio, pero si pasa,
            // mejor un aviso claro que un error de base de datos.
            Notification::make()->title('No se subió ninguna foto')->danger()->send();
            $this->halt();
        }

        $varias = count($rutas) > 1;
        $primera = null;

        foreach ($rutas as $i => $ruta) {
            $foto = WaFoto::create([
                'titulo' => $varias ? trim($data['titulo']) . ' ' . ($i + 1) : trim($data['titulo']),
                'pie'    => $data['pie'] ?? null,
                'ruta'   => $ruta,
                // Se respeta el orden en que quedaron en el formulario.
                'orden'  => (int) ($data['orden'] ?? 0) + $i,
                'activa' => $data['activa'] ?? true,
            ]);

            $primera ??= $foto;
        }

        if ($varias) {
            Notification::make()
                ->title('Se guardaron ' . count($rutas) . ' fotos')
                ->success()->send();
        }

        return $primera;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
