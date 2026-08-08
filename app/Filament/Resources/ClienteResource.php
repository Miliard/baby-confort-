<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ClienteResource\Pages;
use App\Models\Cliente;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Libreta de clientes: se llena sola al crear guías. Sirve para que la próxima
 * vez baste con escribir el teléfono y se completen los datos.
 */
class ClienteResource extends Resource
{
    protected static ?string $model = Cliente::class;
    // La libreta se usa desde la pestaña "Guías" (sección Clientes). Esta pantalla
    // queda disponible para editar a fondo, pero fuera del menú para no duplicar.
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static ?string $navigationLabel = 'Clientes';
    protected static ?string $modelLabel = 'Cliente';
    protected static ?string $pluralModelLabel = 'Clientes';
    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('telefono')->label('Teléfono')->required()
                ->helperText('Solo los 8 dígitos. Es la llave para encontrarlo.'),
            Forms\Components\TextInput::make('nombre')->label('Nombre'),
            Forms\Components\Select::make('departamento')->label('Departamento')
                ->options(fn () => array_combine(array_keys(config('municipios_sv', [])), array_keys(config('municipios_sv', []))))
                ->searchable()->live(),
            Forms\Components\Select::make('municipio')->label('Municipio')
                ->options(function (Forms\Get $get) {
                    $m = config('municipios_sv', [])[$get('departamento')] ?? [];
                    return $m ? array_combine($m, $m) : [];
                })->searchable(),
            Forms\Components\Textarea::make('direccion')->label('Dirección')->rows(2)->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('updated_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('telefono')->label('Teléfono')
                    ->weight('bold')->searchable()->copyable()->copyMessage('✓ Teléfono copiado'),

                Tables\Columns\TextColumn::make('nombre')->label('Nombre')->searchable()->wrap(),

                Tables\Columns\TextColumn::make('municipio')->label('Municipio')->searchable()
                    ->description(fn (Cliente $record) => $record->departamento),

                Tables\Columns\TextColumn::make('direccion')->label('Dirección')
                    ->limit(40)->tooltip(fn (Cliente $record) => $record->direccion)->toggleable(),

                Tables\Columns\TextColumn::make('veces')->label('Envíos')
                    ->badge()->color('success')->sortable(),

                Tables\Columns\TextColumn::make('updated_at')->label('Último envío')
                    ->since()->sortable(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()]),
            ])
            ->emptyStateHeading('Todavía no hay clientes guardados')
            ->emptyStateDescription('Se van guardando solos cada vez que creás una guía.');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListClientes::route('/'),
            'edit'  => Pages\EditCliente::route('/{record}/edit'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
