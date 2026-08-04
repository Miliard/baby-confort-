<?php

namespace App\Filament\Pages;

use App\Models\GuiaFoto;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Sube varias fotos de etiquetas de golpe. Lee el QR de cada foto para saber
 * a qué guía pertenece y la guarda, de modo que el cliente vea la foto de SU
 * paquete al abrir su enlace de seguimiento.
 *
 * Las fotos subidas quedan listadas aquí (vienen de la base de datos), así que
 * no se pierden aunque se cierre la pantalla o se cambie de aplicación.
 */
class FotosPaquetes extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-camera';
    protected static ?string $navigationLabel = 'Fotos de paquetes';
    protected static ?string $title = 'Fotos de paquetes';
    protected static ?int $navigationSort = 2;

    protected static string $view = 'filament.pages.fotos-paquetes';

    /** Fotos guardadas, de la más nueva a la más vieja (últimos 15 días). */
    public function getGuardadasProperty()
    {
        try {
            if (! Schema::hasTable('guia_fotos')) return collect();

            return GuiaFoto::where('created_at', '>=', now()->subDays(15))
                ->orderByDesc('id')
                ->take(120)
                ->get()
                ->groupBy('guia');
        } catch (\Throwable $e) {
            return collect();
        }
    }

    public function eliminarFoto(int $id): void
    {
        try {
            $foto = GuiaFoto::find($id);
            if ($foto) {
                Storage::disk('public')->delete($foto->ruta);
                $foto->delete();
                Notification::make()->title('Foto eliminada')->success()->send();
            }
        } catch (\Throwable $e) {
            Notification::make()->title('No se pudo eliminar')->danger()->send();
        }
    }
}
