<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CategoriaResource\Pages;
use App\Models\Categoria;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CategoriaResource extends Resource
{
    protected static ?string $model = Categoria::class;
    protected static ?string $navigationIcon = 'heroicon-o-tag';
    protected static ?string $navigationLabel = 'Categorías';
    protected static ?string $modelLabel = 'Categoría';
    protected static ?string $pluralModelLabel = 'Categorías';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('nombre')->label('Nombre de la categoría')->required()->maxLength(60)
                ->helperText('Es el texto que ve el cliente en el menú y los botones. Ej: Para bebé, Juguetes, Ofertas.'),
            Forms\Components\TextInput::make('icono')->label('Ícono (emoji)')->maxLength(8)
                ->placeholder('🛍️')->helperText('Un emoji que aparece junto al nombre. Opcional.'),
            Forms\Components\Toggle::make('activo')->label('Activa (visible en la tienda)')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->reorderable('orden')
            ->defaultSort('orden')
            ->columns([
                Tables\Columns\TextColumn::make('icono')->label('Ícono'),
                Tables\Columns\TextColumn::make('nombre')->label('Nombre')->searchable()->weight('bold'),
                Tables\Columns\TextColumn::make('slug')->label('Clave interna')->color('gray'),
                Tables\Columns\IconColumn::make('activo')->label('Activa')->boolean(),
            ])
            ->actions([Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make()])
            ->bulkActions([Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListCategorias::route('/'),
            'create' => Pages\CreateCategoria::route('/create'),
            'edit'   => Pages\EditCategoria::route('/{record}/edit'),
        ];
    }
}
