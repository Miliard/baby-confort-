<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RevendedorResource\Pages;
use App\Models\Revendedor;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class RevendedorResource extends Resource
{
    protected static ?string $model = Revendedor::class;
    protected static ?string $navigationIcon = 'heroicon-o-user-group';
    protected static ?string $navigationLabel = 'Revendedores';
    protected static ?string $modelLabel = 'Revendedor';
    protected static ?string $pluralModelLabel = 'Revendedores';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('nombre')->label('Nombre del revendedor')->required()->maxLength(255),
            Forms\Components\TextInput::make('telefono')->label('Teléfono / WhatsApp')->maxLength(50)
                ->helperText('Opcional. Para tenerlo a mano cuando le deposites su comisión.'),
            Forms\Components\TextInput::make('codigo')->label('Código')
                ->helperText('El código que comparte con sus clientes. Se genera solo al crear; puedes cambiarlo.')
                ->maxLength(40)->extraInputAttributes(['style' => 'text-transform:uppercase']),
            Forms\Components\TextInput::make('porcentaje')->label('Comisión (%)')->numeric()
                ->required()->minValue(1)->maxValue(100)->suffix('%')
                ->helperText('Porcentaje de cada venta que le corresponde. Ej: 15'),
            Forms\Components\Toggle::make('activo')->label('Activo')->default(true)
                ->helperText('Apágalo para desactivar su código sin borrarlo.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('codigo')->label('Código')->weight('bold')->copyable()->searchable(),
                Tables\Columns\TextColumn::make('nombre')->label('Nombre')->searchable(),
                Tables\Columns\TextColumn::make('telefono')->label('Teléfono')->toggleable(),
                Tables\Columns\TextColumn::make('porcentaje')->label('Comisión')->formatStateUsing(fn ($state) => $state . '%'),
                Tables\Columns\TextColumn::make('ventas')->label('Ventas')
                    ->getStateUsing(function (Revendedor $record) {
                        try { return $record->pedidos()->count(); } catch (\Throwable $e) { return 0; }
                    }),
                Tables\Columns\TextColumn::make('a_depositar')->label('A depositar')
                    ->getStateUsing(function (Revendedor $record) {
                        try { return '$' . number_format((float) $record->pedidos()->sum('comision'), 2); }
                        catch (\Throwable $e) { return '$0.00'; }
                    })
                    ->color('success')->weight('bold')
                    ->tooltip('Suma de comisiones de todos sus pedidos.'),
                Tables\Columns\IconColumn::make('activo')->label('Activo')->boolean(),
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
            'index'  => Pages\ListRevendedores::route('/'),
            'create' => Pages\CreateRevendedor::route('/create'),
            'edit'   => Pages\EditRevendedor::route('/{record}/edit'),
        ];
    }
}
