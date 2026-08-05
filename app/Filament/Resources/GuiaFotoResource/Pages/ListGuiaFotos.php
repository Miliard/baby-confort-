<?php

namespace App\Filament\Resources\GuiaFotoResource\Pages;

use App\Filament\Resources\GuiaFotoResource;
use App\Models\GuiaFoto;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListGuiaFotos extends ListRecords
{
    protected static string $resource = GuiaFotoResource::class;

    /** Se llama al copiar el mensaje: deja la fila marcada como enviada. */
    public function marcarEnviado(int $id): void
    {
        $foto = GuiaFoto::find($id);
        if (! $foto) return;

        $foto->update(['enviado_at' => now()]);

        Notification::make()
            ->title('✓ Copiado y marcado como enviado')
            ->body('Guía ' . $foto->guia)
            ->success()->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('subir')
                ->label('📷 Subir fotos')
                ->url(fn () => \App\Filament\Pages\FotosPaquetes::getUrl()),
        ];
    }
}
