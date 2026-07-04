<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OrderResource\Pages;
use App\Models\Order;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;
    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';
    protected static ?string $navigationLabel = 'Pedidos';
    protected static ?string $modelLabel = 'Pedido';
    protected static ?string $pluralModelLabel = 'Pedidos';

    public static function getNavigationBadge(): ?string
    {
        return (string) static::getModel()::where('status', 'nuevo')->count() ?: null;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Cliente')->schema([
                Forms\Components\TextInput::make('customer_name')->label('Nombre')->disabled(),
                Forms\Components\TextInput::make('phone')->label('Teléfono')->disabled(),
                Forms\Components\TextInput::make('municipio')->label('Municipio')->disabled(),
                Forms\Components\Textarea::make('address')->label('Dirección')->disabled()->columnSpanFull(),
                Forms\Components\TextInput::make('payment')->label('Forma de pago')->disabled(),
            ])->columns(2),

            Forms\Components\Section::make('Estado y montos')->schema([
                Forms\Components\Select::make('status')->label('Estado')->options([
                    'nuevo' => 'Nuevo', 'contactado' => 'Contactado',
                    'entregado' => 'Entregado', 'cancelado' => 'Cancelado',
                ])->required(),
                Forms\Components\TextInput::make('subtotal')->label('Productos')->prefix('$')->disabled(),
                Forms\Components\TextInput::make('shipping')->label('Envío')->prefix('$')->disabled(),
                Forms\Components\TextInput::make('total')->label('Total')->prefix('$')->disabled(),
            ])->columns(2),

            Forms\Components\Placeholder::make('detalle')->label('Productos del pedido')
                ->content(function (?Order $record) {
                    if (! $record) return '—';
                    return collect($record->items ?? [])->map(function ($it) {
                        return ($it['cantidad'] ?? '?') . ' x ' . ($it['producto'] ?? '') .
                            ' (' . ($it['talla'] ?? '') . ') — $' . number_format($it['subtotal'] ?? 0, 2);
                    })->implode("\n") ?: '—';
                }),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('id')->label('#')->sortable(),
                Tables\Columns\TextColumn::make('created_at')->label('Fecha')->dateTime('d/m/Y H:i')->sortable(),
                Tables\Columns\TextColumn::make('customer_name')->label('Cliente')->searchable(),
                Tables\Columns\TextColumn::make('phone')->label('Teléfono')->searchable(),
                Tables\Columns\TextColumn::make('municipio')->label('Municipio')->searchable(),
                Tables\Columns\TextColumn::make('shipping')->label('Envío')->money('USD'),
                Tables\Columns\TextColumn::make('total')->label('Total')->money('USD')->sortable(),
                Tables\Columns\TextColumn::make('status')->label('Estado')->badge()->color(fn ($state) => match ($state) {
                    'nuevo' => 'warning', 'contactado' => 'info', 'entregado' => 'success', 'cancelado' => 'danger', default => 'gray',
                }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->label('Estado')->options([
                    'nuevo' => 'Nuevo', 'contactado' => 'Contactado', 'entregado' => 'Entregado', 'cancelado' => 'Cancelado',
                ]),
            ])
            ->actions([Tables\Actions\EditAction::make()->label('Ver / Editar')]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrders::route('/'),
            'edit'  => Pages\EditOrder::route('/{record}/edit'),
        ];
    }
}
