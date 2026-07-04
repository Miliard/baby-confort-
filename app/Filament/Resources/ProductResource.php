<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages;
use App\Models\Product;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;
    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';
    protected static ?string $navigationLabel = 'Productos';
    protected static ?string $modelLabel = 'Producto';
    protected static ?string $pluralModelLabel = 'Productos';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Datos del producto')->schema([
                Forms\Components\TextInput::make('name')->label('Nombre')->required()->maxLength(255),
                Forms\Components\TextInput::make('brand')->label('Marca')->maxLength(255),
                Forms\Components\Textarea::make('description')->label('Descripción corta')->rows(3)->columnSpanFull(),
                Forms\Components\TextInput::make('image')->label('Foto por link (pega la URL)')->url()->columnSpanFull()
                    ->helperText('Pega el link de una imagen. Si prefieres, sube tu propia foto abajo.'),
                Forms\Components\FileUpload::make('image_upload')->label('…o sube tu propia foto')
                    ->image()->disk('public')->directory('productos')->imageEditor()->columnSpanFull()
                    ->helperText('Si subes una foto aquí, se usará esta en lugar del link.'),
                Forms\Components\TextInput::make('oferta')->label('Etiqueta de oferta (burbuja)')
                    ->placeholder('¡Oferta!  ·  -20%  ·  2x1')->helperText('Si escribes algo, aparece una burbuja en el producto. Déjalo vacío para quitarla.'),
                Forms\Components\Toggle::make('active')->label('Activo (visible en la tienda)')->default(true),
            ])->columns(2),

            Forms\Components\Section::make('Ficha de producto (página individual)')
                ->description('Esto aparece en la página de cada producto.')
                ->collapsible()
                ->schema([
                    Forms\Components\FileUpload::make('gallery')->label('Galería de fotos (varias)')
                        ->image()->multiple()->reorderable()->disk('public')->directory('productos')
                        ->columnSpanFull(),

                    Forms\Components\Repeater::make('features')->label('Características (ícono + texto)')
                        ->schema([
                            Forms\Components\TextInput::make('icon')->label('Ícono (emoji)')->placeholder('🪶')->maxLength(4),
                            Forms\Components\TextInput::make('text')->label('Texto')->placeholder('Tacto suave y ligero como una pluma.'),
                        ])->columns(4)->addActionLabel('Agregar característica')->columnSpanFull()
                        ->itemLabel(fn (array $state): ?string => ($state['icon'] ?? '') . ' ' . ($state['text'] ?? '')),

                    Forms\Components\TextInput::make('made_in')->label('Fabricado en')->placeholder('AUSTRALIA'),
                    Forms\Components\TextInput::make('badge')->label('Distintivo')->placeholder('Producto galardonado'),
                    Forms\Components\Textarea::make('stock_warning')->label('Aviso de stock')->rows(2)
                        ->placeholder('Este producto se agotó 32 veces el año pasado…')->columnSpanFull(),
                ])->columns(2),

            Forms\Components\Section::make('Tallas / presentaciones')
                ->description('Cada talla tiene su propio peso, precio, cantidad y (opcional) combo.')
                ->schema([
                    Forms\Components\Repeater::make('sizes')->relationship()->label('Tallas')
                        ->schema([
                            Forms\Components\TextInput::make('size')->label('Talla / presentación')->required()->placeholder('Ej: M, S/M, 180 ML'),
                            Forms\Components\TextInput::make('weight')->label('Peso')->placeholder('Ej: 6-11 kg'),
                            Forms\Components\Textarea::make('details')->label('Detalle de esta talla')->rows(6)->columnSpanFull()
                                ->placeholder("Descripción que se muestra al elegir esta talla (puedes usar emojis y saltos de línea)."),

                            Forms\Components\TextInput::make('image')->label('Foto de esta talla (link)')->url()->columnSpanFull()
                                ->helperText('Al elegir esta talla, la imagen principal cambia a esta.'),
                            Forms\Components\FileUpload::make('image_upload')->label('…o sube la foto de esta talla')
                                ->image()->disk('public')->directory('productos/tallas')->columnSpanFull(),
                            Forms\Components\TextInput::make('price')->label('Precio')->numeric()->prefix('$')->required(),
                            Forms\Components\TextInput::make('price_before')->label('Precio antes (oferta)')->numeric()->prefix('$')
                                ->helperText('Opcional. Si es mayor al precio, se muestra tachado.'),
                            Forms\Components\TextInput::make('quantity')->label('Cantidad (stock)')->numeric()->default(0)->required(),
                            Forms\Components\TextInput::make('combo_qty')->label('Combo: cantidad')->numeric()->minValue(2)->helperText('Opcional'),
                            Forms\Components\TextInput::make('combo_price')->label('Combo: precio')->numeric()->prefix('$')->helperText('Opcional'),
                        ])->columns(3)->defaultItems(1)->addActionLabel('Agregar talla')->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('image')->label('Foto')->circular(),
                Tables\Columns\TextColumn::make('name')->label('Nombre')->searchable()->wrap(),
                Tables\Columns\TextColumn::make('brand')->label('Marca')->toggleable(),
                Tables\Columns\TextColumn::make('sizes_count')->counts('sizes')->label('Tallas'),
                Tables\Columns\IconColumn::make('active')->label('Activo')->boolean(),
            ])
            ->filters([Tables\Filters\TernaryFilter::make('active')->label('Activo')])
            ->actions([Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make()])
            ->bulkActions([Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit'   => Pages\EditProduct::route('/{record}/edit'),
        ];
    }
}
