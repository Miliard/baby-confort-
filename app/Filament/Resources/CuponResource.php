<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CuponResource\Pages;
use App\Models\Cupon;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CuponResource extends Resource
{
    protected static ?string $model = Cupon::class;
    protected static ?string $navigationIcon = 'heroicon-o-ticket';
    protected static ?string $navigationLabel = 'Cupones';
    protected static ?string $modelLabel = 'Cupón';
    protected static ?string $pluralModelLabel = 'Cupones';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('codigo')->label('Código del cupón')->required()
                ->maxLength(40)->unique(ignoreRecord: true)
                ->helperText('El cliente lo escribe en el carrito. Ej: MAMA10')
                ->extraInputAttributes(['style' => 'text-transform:uppercase']),
            Forms\Components\TextInput::make('porcentaje')->label('Descuento (%)')->numeric()
                ->required()->minValue(1)->maxValue(100)->suffix('%')
                ->helperText('Porcentaje que se descuenta del total de productos. Ej: 10'),
            Forms\Components\Toggle::make('activo')->label('Activo')->default(true)
                ->helperText('Apágalo para desactivar el cupón sin borrarlo.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('codigo')->label('Código')->searchable()->weight('bold')->copyable(),
                Tables\Columns\TextColumn::make('porcentaje')->label('Descuento')->formatStateUsing(fn ($state) => $state . '%'),
                Tables\Columns\IconColumn::make('activo')->label('Activo')->boolean(),
                Tables\Columns\TextColumn::make('usos')->label('Veces usado')->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('activo')->label('Activo'),
            ])
            ->actions([Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make()])
            ->bulkActions([Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListCupones::route('/'),
            'create' => Pages\CreateCupon::route('/create'),
            'edit'   => Pages\EditCupon::route('/{record}/edit'),
        ];
    }
}
