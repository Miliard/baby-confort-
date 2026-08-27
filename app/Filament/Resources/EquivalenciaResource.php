<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EquivalenciaResource\Pages;
use App\Models\Equivalencia;
use App\Models\Product;
use App\Models\ProductSize;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * "Cuando el texto diga esto, es este producto."
 *
 * El ranking de productos lee lo que escribís al armar la guía. Muchos
 * renglones no nombran el producto —"12 paquetes de 8 a 15 años"— y ahí no hay
 * nada que adivinar. Acá se le enseña una vez y lo entiende siempre.
 */
class EquivalenciaResource extends Resource
{
    protected static ?string $model = Equivalencia::class;
    protected static ?string $navigationIcon = 'heroicon-o-language';
    protected static ?string $navigationLabel = 'Equivalencias';
    protected static ?string $modelLabel = 'equivalencia';
    protected static ?string $pluralModelLabel = 'Equivalencias';
    protected static ?int $navigationSort = 5;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('texto')
                ->label('Cuando el texto diga…')
                ->required()
                ->maxLength(120)
                ->placeholder('8 a 15')
                ->helperText('Escribí solo el trozo que lo identifica, no la frase entera. '
                    . 'No importan mayúsculas, tildes ni signos.'),

            Forms\Components\Select::make('product_id')
                ->label('…es este producto')
                ->required()
                ->searchable()
                ->options(fn () => Product::where('active', true)
                    ->orderBy('name')->pluck('name', 'id')->all())
                ->live(),

            // Las tallas se traen del producto elegido: así no hay que
            // escribirlas a mano ni arriesgarse a un nombre que no existe.
            Forms\Components\Select::make('talla')
                ->label('Talla (opcional)')
                ->placeholder('Sin talla concreta')
                ->options(function (Forms\Get $get) {
                    $id = $get('product_id');
                    if (! $id) return [];

                    return ProductSize::where('product_id', $id)
                        ->orderBy('id')->pluck('size', 'size')->all();
                })
                ->helperText('Ponela cuando el texto implique una talla. '
                    . 'Ejemplo: "8 a 15" es la talla "8 a 14 años" del catálogo.'),

            Forms\Components\TextInput::make('nota')
                ->label('Nota para vos (opcional)')
                ->maxLength(200),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('texto')
                    ->label('Cuando diga…')
                    ->weight('bold')->searchable()->wrap(),

                Tables\Columns\TextColumn::make('product.name')
                    ->label('Es este producto')
                    ->searchable()->wrap()
                    ->placeholder('— sin producto —'),

                Tables\Columns\TextColumn::make('talla')
                    ->label('Talla')->badge()->color('gray')
                    ->placeholder('cualquiera'),

                Tables\Columns\TextColumn::make('nota')
                    ->label('Nota')->color('gray')->wrap()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ])
            ->emptyStateHeading('Todavía no hay equivalencias')
            ->emptyStateDescription('Agregá una cuando veas un renglón en "sin identificar" '
                . 'dentro del ranking de productos.');
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListEquivalencias::route('/'),
            'create' => Pages\CreateEquivalencia::route('/crear'),
            'edit'   => Pages\EditEquivalencia::route('/{record}/editar'),
        ];
    }
}
