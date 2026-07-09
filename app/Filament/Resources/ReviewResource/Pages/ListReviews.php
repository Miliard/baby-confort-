<?php

namespace App\Filament\Resources\ReviewResource\Pages;

use App\Filament\Resources\ReviewResource;
use App\Models\Review;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListReviews extends ListRecords
{
    protected static string $resource = ReviewResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('bulk')
                ->label('Subir varias capturas')
                ->icon('heroicon-o-photo')
                ->color('success')
                ->form([
                    Forms\Components\FileUpload::make('imagenes')
                        ->label('Capturas de reseñas')
                        ->image()->multiple()->reorderable()
                        ->disk('public')->directory('resenas')
                        ->helperText('Arrastra TODAS las capturas de una vez. Cada una se convierte en una reseña.')
                        ->required(),
                ])
                ->modalHeading('Subir varias capturas de reseñas')
                ->modalSubmitActionLabel('Agregar todas')
                ->action(function (array $data) {
                    foreach (($data['imagenes'] ?? []) as $path) {
                        Review::create(['image_upload' => $path, 'active' => true]);
                    }
                    Notification::make()->title('Reseñas agregadas')->success()->send();
                }),
            Actions\CreateAction::make()->label('Agregar una'),
        ];
    }
}
