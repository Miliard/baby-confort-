<?php

namespace App\Filament\Resources;

use App\Filament\Resources\WaEtiquetaResource\Pages;
use App\Models\WaEtiqueta;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Las etiquetas para clasificar conversaciones.
 *
 * No son las de WhatsApp Business del teléfono — Meta no las expone a la API,
 * así que estas son propias del panel. A cambio las ven los colaboradores.
 */
class WaEtiquetaResource extends Resource
{
    protected static ?string $model = WaEtiqueta::class;

    protected static ?string $navigationIcon = 'heroicon-o-tag';
    protected static ?string $navigationLabel = 'Etiquetas';
    protected static ?string $navigationGroup = 'WhatsApp';
    protected static ?string $modelLabel = 'etiqueta';
    protected static ?string $pluralModelLabel = 'etiquetas';
    protected static ?int $navigationSort = 3;

    public static function canAccess(): bool
    {
        return ! (bool) (auth()->user()?->solo_chat ?? false);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('nombre')
                ->label('Nombre')
                ->helperText('Corto, porque se muestra en la lista de conversaciones. Ej: "Pedidos".')
                ->required()
                ->maxLength(40),

            Forms\Components\Select::make('rol')
                ->label('¿Se pone sola?')
                ->helperText('Si elegís un papel acá, el panel pone esta etiqueta sin que nadie '
                           . 'se acuerde de hacerlo. Solo una etiqueta puede tener cada papel: '
                           . 'si se lo das a esta, la que lo tenía lo suelta.')
                ->options([
                    'pedido'    => 'Cuando llega una orden de envío',
                    'procesada' => 'Cuando ya se mandó el enlace de rastreo',
                ])
                ->placeholder('No, esta la pongo yo a mano')
                // Al guardar, se le quita el papel a la que lo tuviera antes:
                // dos etiquetas de "pedido" no querrían decir nada.
                ->afterStateUpdated(function ($state, $livewire) {
                    if (blank($state)) return;

                    try {
                        \App\Models\WaEtiqueta::where('rol', $state)
                            ->when($livewire->record ?? null, fn ($q) => $q->whereKeyNot($livewire->record->getKey()))
                            ->update(['rol' => null]);
                    } catch (\Throwable $e) {
                    }
                })
                ->live(),

            Forms\Components\Select::make('color')
                ->label('Color')
                ->options([
                    'verde'    => 'Verde',
                    'azul'     => 'Azul',
                    'amarillo' => 'Amarillo',
                    'rojo'     => 'Rojo',
                    'morado'   => 'Morado',
                    'gris'     => 'Gris',
                ])
                ->default('verde')
                ->required(),

            Forms\Components\TextInput::make('orden')
                ->label('Orden')
                ->helperText('Número más bajo, más a la izquierda.')
                ->numeric()
                ->default(0),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('orden')
            ->columns([
                Tables\Columns\TextColumn::make('orden')->label('#')->width('60px')->sortable(),

                Tables\Columns\TextColumn::make('nombre')
                    ->label('Etiqueta')
                    ->weight('bold')
                    ->badge()
                    ->color(fn (WaEtiqueta $record) => match ($record->color) {
                        'verde'    => 'success',
                        'azul'     => 'info',
                        'amarillo' => 'warning',
                        'rojo'     => 'danger',
                        default    => 'gray',
                    })
                    ->searchable(),

                Tables\Columns\TextColumn::make('conversaciones_count')
                    ->label('Conversaciones')
                    ->counts('conversaciones'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->emptyStateHeading('No hay etiquetas')
            ->emptyStateDescription('Sirven para clasificar conversaciones igual que en '
                . 'WhatsApp Business, pero visibles para todo el equipo.');
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListWaEtiquetas::route('/'),
            'create' => Pages\CreateWaEtiqueta::route('/create'),
            'edit'   => Pages\EditWaEtiqueta::route('/{record}/edit'),
        ];
    }
}
