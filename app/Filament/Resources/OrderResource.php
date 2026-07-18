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
                Forms\Components\TextInput::make('cupon')->label('Cupón aplicado')->disabled()->placeholder('Sin cupón'),
                Forms\Components\TextInput::make('descuento')->label('Descuento')->prefix('$')->disabled(),
                Forms\Components\TextInput::make('shipping')->label('Envío')->prefix('$')->disabled(),
                Forms\Components\TextInput::make('total')->label('Total')->prefix('$')->disabled(),
            ])->columns(2),

            Forms\Components\Section::make('Envío y seguimiento')->schema([
                Forms\Components\TextInput::make('guia')->label('Número de guía (Express)')
                    ->helperText('Pega la guía que te da Express. La barra de seguimiento se actualiza sola leyendo su estado.')
                    ->placeholder('Ej: 5009506'),
                Forms\Components\Select::make('estado_envio')->label('Estado manual (opcional)')
                    ->options([1 => '1 · Pedido confirmado', 2 => '2 · Recolectado', 3 => '3 · En camino', 4 => '4 · Entregado'])
                    ->placeholder('Automático (leer de Express)')
                    ->helperText('Déjalo vacío para que use el estado real de Express. Úsalo solo si quieres forzar una etapa.'),
                Forms\Components\Actions::make([
                    Forms\Components\Actions\Action::make('enviar_seguimiento')
                        ->label('Enviar seguimiento por WhatsApp')
                        ->icon('heroicon-o-chat-bubble-left-right')
                        ->color('success')
                        ->modalHeading('Mensaje de seguimiento')
                        ->modalSubmitAction(false)
                        ->modalCancelActionLabel('Cerrar')
                        ->modalContent(fn (?Order $record) => $record ? view('filament.seguimiento-modal', static::seguimientoData($record)) : null),
                    Forms\Components\Actions\Action::make('ver_seguimiento')
                        ->label('Ver página de seguimiento')
                        ->icon('heroicon-o-eye')->color('gray')
                        ->url(fn (?Order $record) => $record ? route('store.rastreo', $record) : null)
                        ->openUrlInNewTab(),
                ]),
            ])->columns(2),

            Forms\Components\Section::make('🛒 Lo que pidió el cliente')
                ->schema([
                    Forms\Components\Placeholder::make('detalle')->label('')
                        ->content(function (?Order $record) {
                            if (! $record) return '—';
                            $items = collect($record->items ?? []);
                            if ($items->isEmpty()) {
                                return new \Illuminate\Support\HtmlString('<span style="color:#888">Sin productos registrados.</span>');
                            }
                            $rows = $items->map(function ($it) {
                                $cant  = (int) ($it['cantidad'] ?? 0);
                                $prod  = e($it['producto'] ?? '');
                                $talla = e($it['talla'] ?? '');
                                $sub   = number_format($it['subtotal'] ?? 0, 2);
                                return "<tr>"
                                    . "<td style='padding:7px 14px 7px 0;border-bottom:1px solid #eee'><b>{$cant}×</b> {$prod}</td>"
                                    . "<td style='padding:7px 14px;border-bottom:1px solid #eee;color:#666'>Talla: <b>{$talla}</b></td>"
                                    . "<td style='padding:7px 0;border-bottom:1px solid #eee;text-align:right;font-weight:700'>\${$sub}</td>"
                                    . "</tr>";
                            })->implode('');
                            $html = "<table style='width:100%;border-collapse:collapse;font-size:14px'>{$rows}</table>";
                            return new \Illuminate\Support\HtmlString($html);
                        }),
                ]),
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
                Tables\Columns\TextColumn::make('items')->label('Productos')
                    ->formatStateUsing(fn ($state) => collect(is_array($state) ? $state : [])
                        ->map(fn ($it) => ((int) ($it['cantidad'] ?? 0)) . '× ' . ($it['producto'] ?? '') . ' (' . ($it['talla'] ?? '') . ')')
                        ->implode(', ') ?: '—')
                    ->wrap()->toggleable(),
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
            ->actions([
                Tables\Actions\Action::make('guia')
                    ->label('Guía')->icon('heroicon-o-truck')->color('info')
                    ->form([
                        Forms\Components\TextInput::make('guia')->label('Número de guía (Express)')->placeholder('Ej: 5009506'),
                    ])
                    ->fillForm(fn (Order $record) => ['guia' => $record->guia])
                    ->action(fn (Order $record, array $data) => $record->update(['guia' => $data['guia'] ?: null]))
                    ->modalHeading('Número de guía')->modalSubmitActionLabel('Guardar'),
                Tables\Actions\Action::make('enviar')
                    ->label('Enviar seguimiento')->icon('heroicon-o-chat-bubble-left-right')->color('success')
                    ->modalHeading('Mensaje de seguimiento')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Cerrar')
                    ->modalContent(fn (Order $record) => view('filament.seguimiento-modal', static::seguimientoData($record))),
                Tables\Actions\EditAction::make()->label('Ver'),
            ]);
    }

    public static function seguimientoData(Order $record): array
    {
        $phone = preg_replace('/\D/', '', $record->phone ?? '');
        if (strlen($phone) === 8) $phone = '503' . $phone;
        $link = route('store.rastreo', $record);
        $msg = "\u{A1}Sigue tu pedido, Baby-Confort!\n\nPedido #{$record->id}\nRastr\u{E9}alo aqu\u{ED}: {$link}\n\n\u{A1}Gracias por tu preferencia!";
        $wa  = 'https://wa.me/' . $phone . '?text=' . rawurlencode($msg);
        return ['msg' => $msg, 'wa' => $wa];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrders::route('/'),
            'edit'  => Pages\EditOrder::route('/{record}/edit'),
        ];
    }
}
