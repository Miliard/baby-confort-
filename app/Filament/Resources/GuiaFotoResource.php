<?php

namespace App\Filament\Resources;

use App\Filament\Resources\GuiaFotoResource\Pages;
use App\Models\GuiaFoto;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;

/**
 * Listado de las fotos de paquetes ya guardadas, con la tabla nativa de Filament
 * (buscador, orden y paginación). Desde aquí se envía el seguimiento al cliente.
 */
class GuiaFotoResource extends Resource
{
    protected static ?string $model = GuiaFoto::class;
    protected static ?string $navigationIcon = 'heroicon-o-photo';
    protected static ?string $navigationLabel = 'Guías con foto';
    protected static ?string $modelLabel = 'Guía con foto';
    protected static ?string $pluralModelLabel = 'Guías con foto';
    protected static ?int $navigationSort = 3;

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('guia')->label('Guía')
                    ->weight('bold')->searchable()->copyable()
                    ->copyMessage('Guía copiada'),

                Tables\Columns\TextColumn::make('created_at')->label('Fecha')
                    ->dateTime('d/m/Y H:i')->sortable(),

                Tables\Columns\TextColumn::make('nombre')->label('Cliente')
                    ->searchable()->placeholder('—')->wrap(),

                Tables\Columns\TextColumn::make('telefono')->label('Teléfono')
                    ->searchable()->copyable()->copyMessage('Teléfono copiado')
                    ->placeholder('sin teléfono'),

                Tables\Columns\ImageColumn::make('ruta')->label('Foto')
                    ->disk('public')->height(42)->square(),
            ])
            ->actions([
                Tables\Actions\Action::make('whatsapp')
                    ->label('Enviar')->icon('heroicon-o-chat-bubble-left-right')->color('success')
                    ->url(fn (GuiaFoto $record) => $record->whatsapp())
                    ->openUrlInNewTab()
                    ->visible(fn (GuiaFoto $record) => filled($record->whatsapp())),

                Tables\Actions\Action::make('enlace')
                    ->label('Ver rastreo')->icon('heroicon-o-link')->color('gray')
                    ->url(fn (GuiaFoto $record) => $record->enlaceRastreo())
                    ->openUrlInNewTab(),

                Tables\Actions\DeleteAction::make()->label('Borrar')
                    ->after(function (GuiaFoto $record) {
                        try { Storage::disk('public')->delete($record->ruta); } catch (\Throwable $e) {}
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('Aún no hay fotos de paquetes')
            ->emptyStateDescription('Subí las etiquetas desde "Fotos de paquetes" y aparecerán aquí.');
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListGuiaFotos::route('/')];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
