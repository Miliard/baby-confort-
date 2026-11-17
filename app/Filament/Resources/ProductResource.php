<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages;
use App\Filament\Resources\ProductResource\RelationManagers;
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
    protected static ?string $navigationGroup = 'Tienda';
    protected static ?string $modelLabel = 'Producto';
    protected static ?string $pluralModelLabel = 'Productos';

    /*
     * El producto en tarjetas, como en las tiendas grandes (Shopify, etc.):
     *
     *   ┌──────────────────────────────┬─────────────────┐
     *   │ Datos (nombre, marca, …)     │ Foto principal  │
     *   │ Página del producto (plegada)│ Visible / oferta│
     *   └──────────────────────────────┴─────────────────┘
     *
     * Antes era una sola columna larguísima: datos, dos campos de foto con
     * sus textos de ayuda, la ficha abierta con galería y características,
     * y hasta el final las tallas. Ahora las tallas tienen su propia pestaña
     * (SizesRelationManager), que es la que se abre primero, y acá queda lo
     * que se toca poco. En el teléfono las tarjetas se apilan.
     *
     * Al CREAR todavía no hay producto donde colgar la tabla de tallas, así
     * que ahí se piden las tallas con lo básico (talla, precio, existencia);
     * fotos y texto de cada talla se ponen después, en la pestaña de tallas.
     */
    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Grid::make(['default' => 1, 'lg' => 3])->schema([

                // ── Columna ancha ──
                Forms\Components\Group::make([
                    Forms\Components\Section::make('Datos del producto')
                        ->icon('heroicon-o-shopping-bag')
                        ->schema([
                            Forms\Components\TextInput::make('name')->label('Nombre')->required()->maxLength(255)
                                ->columnSpanFull(),
                            Forms\Components\TextInput::make('brand')->label('Marca')->maxLength(255),
                            Forms\Components\Select::make('categoria')->label('Categoría del menú ☰')
                                ->options(\App\Models\Product::categoriaLabels())
                                ->placeholder('Sin categoría'),
                            Forms\Components\Textarea::make('description')->label('Descripción corta')->rows(3)
                                ->columnSpanFull(),
                        ])->columns(2),

                    Forms\Components\Section::make('Tallas')
                        ->icon('heroicon-o-tag')
                        ->description('Lo básico de cada talla. Las fotos y el texto de cada una se agregan después de guardar, en la pestaña "Tallas".')
                        ->visibleOn('create')
                        ->schema([
                            Forms\Components\Repeater::make('sizes')->relationship()->hiddenLabel()
                                ->schema([
                                    Forms\Components\TextInput::make('size')->label('Talla')->required()->placeholder('M, XL, 180 ML'),
                                    Forms\Components\TextInput::make('price')->label('Precio')->numeric()->prefix('$')->required(),
                                    Forms\Components\TextInput::make('quantity')->label('Existencia')->numeric()->default(0)->required(),
                                    Forms\Components\TextInput::make('weight')->label('Peso')->placeholder('6-11 kg'),
                                    Forms\Components\TextInput::make('unidades')->label('Unidades')->numeric()->minValue(1),
                                ])
                                ->columns(['default' => 2, 'md' => 5])
                                ->defaultItems(1)->addActionLabel('Agregar otra talla'),
                        ]),

                    Forms\Components\Section::make('Página del producto')
                        ->icon('heroicon-o-document-text')
                        ->description('Galería, características y avisos que se ven al abrir el producto en la tienda.')
                        ->collapsible()->collapsed()
                        ->schema([
                            Forms\Components\FileUpload::make('gallery')->label('Galería de fotos (varias, se pueden ordenar)')
                                ->image()->multiple()->reorderable()->disk('public')->directory('productos')
                                ->panelLayout('grid')->columnSpanFull(),

                            Forms\Components\Repeater::make('features')->label('Características (ícono + texto)')
                                ->schema([
                                    Forms\Components\TextInput::make('icon')->label('Ícono')->placeholder('🪶')->maxLength(4),
                                    Forms\Components\TextInput::make('text')->label('Texto')->placeholder('Tacto suave y ligero como una pluma.')
                                        ->columnSpan(3),
                                ])->columns(4)->addActionLabel('Agregar característica')->columnSpanFull()
                                ->collapsible()->collapsed()
                                ->itemLabel(fn (array $state): ?string => trim(($state['icon'] ?? '') . ' ' . ($state['text'] ?? '')) ?: null),

                            Forms\Components\TextInput::make('made_in')->label('Fabricado en')->placeholder('AUSTRALIA'),
                            Forms\Components\TextInput::make('badge')->label('Distintivo')->placeholder('Producto galardonado'),
                            Forms\Components\Textarea::make('stock_warning')->label('Aviso de existencia')->rows(2)
                                ->placeholder('Este producto se agotó 32 veces el año pasado…')->columnSpanFull(),
                        ])->columns(2),
                ])->columnSpan(['lg' => 2]),

                // ── Columna angosta ──
                Forms\Components\Group::make([
                    Forms\Components\Section::make('Foto principal')
                        ->icon('heroicon-o-photo')
                        ->schema([
                            Forms\Components\FileUpload::make('image_upload')->hiddenLabel()
                                ->image()->disk('public')->directory('productos')->imageEditor(),
                            Forms\Components\TextInput::make('image')->label('…o pegá el link de una foto')->url()
                                ->placeholder('https://…')
                                ->helperText('Si hay foto subida, se usa esa.'),
                        ]),

                    Forms\Components\Section::make('En la tienda')
                        ->icon('heroicon-o-eye')
                        ->schema([
                            Forms\Components\Toggle::make('active')->label('Visible en la tienda')->default(true),
                            Forms\Components\TextInput::make('oferta')->label('Burbuja de oferta')
                                ->placeholder('¡Oferta!  ·  -20%  ·  2x1')
                                ->helperText('Vacío = sin burbuja.'),
                        ]),
                ])->columnSpan(['lg' => 1]),
            ]),
        ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\SizesRelationManager::class,
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                // La foto que de verdad se ve en la tienda: la subida, o si no el link.
                Tables\Columns\ImageColumn::make('foto')->label('Foto')->square()->size(48)
                    ->getStateUsing(function (Product $r) {
                        $u = $r->imageUrl();
                        return $u ? (str_starts_with($u, 'http') ? $u : url($u)) : null;
                    }),
                // El buscador mira nombre, marca Y tallas: escribir "XXL" trae los
                // productos que existen en esa talla.
                Tables\Columns\TextColumn::make('name')->label('Nombre')->wrap()
                    ->searchable(query: function (\Illuminate\Database\Eloquent\Builder $query, string $search) {
                        return $query->where(function ($q) use ($search) {
                            $q->where('name', 'like', "%{$search}%")
                              ->orWhere('brand', 'like', "%{$search}%")
                              ->orWhereHas('sizes', fn ($s) => $s->where('size', 'like', "%{$search}%"));
                        });
                    }),
                Tables\Columns\TextColumn::make('brand')->label('Marca')->toggleable(),
                Tables\Columns\SelectColumn::make('categoria')->label('Categoría')
                    ->options(\App\Models\Product::categoriaLabels())->placeholder('Sin categoría'),
                // Cada talla con su existencia, en color: verde hay, naranja
                // quedan pocas, rojo agotada. Se ve qué reponer sin entrar.
                Tables\Columns\TextColumn::make('existencia')->label('Tallas · existencia')
                    ->getStateUsing(fn (Product $r) => $r->sizes
                        ->map(fn ($s) => $s->size . ' · ' . ((int) $s->quantity > 0 ? (int) $s->quantity : 'agotada'))
                        ->all())
                    ->badge()->limitList(6)->expandableLimitedList()
                    ->color(function (string $state) {
                        $n = (int) trim((string) strrchr($state, '·'), '· ');
                        return str_ends_with($state, 'agotada') ? 'danger' : ($n <= 5 ? 'warning' : 'success');
                    }),
                Tables\Columns\TextColumn::make('desde')->label('Desde')
                    ->getStateUsing(fn (Product $r) => $r->sizes->count() ? '$' . number_format($r->precioDesde(), 2) : null)
                    ->placeholder('—')
                    ->toggleable(),
                Tables\Columns\IconColumn::make('active')->label('Activo')->boolean(),
            ])
            ->modifyQueryUsing(fn (\Illuminate\Database\Eloquent\Builder $q) => $q->with('sizes'))
            ->reorderable('orden')
            ->defaultSort('orden')
            ->filters([
                Tables\Filters\TernaryFilter::make('active')->label('Activo'),
                Tables\Filters\SelectFilter::make('categoria')->label('Categoría')->options(\App\Models\Product::categoriaLabels()),

                // Filtro por talla: se arma solo con las tallas que existen.
                Tables\Filters\SelectFilter::make('talla')
                    ->label('Talla')
                    ->options(function () {
                        try {
                            return \App\Models\ProductSize::query()
                                ->select('size')->distinct()->orderBy('size')
                                ->pluck('size', 'size')->all();
                        } catch (\Throwable $e) {
                            return [];
                        }
                    })
                    ->query(fn ($query, array $data) => filled($data['value'] ?? null)
                        ? $query->whereHas('sizes', fn ($s) => $s->where('size', $data['value']))
                        : $query),

                // Para encontrar rápido lo que hay que reponer.
                Tables\Filters\TernaryFilter::make('agotado')
                    ->label('Existencia')
                    ->placeholder('Todos')
                    ->trueLabel('Sin existencia')
                    ->falseLabel('Con existencia')
                    ->queries(
                        true:  fn ($query) => $query->whereDoesntHave('sizes', fn ($s) => $s->where('quantity', '>', 0)),
                        false: fn ($query) => $query->whereHas('sizes', fn ($s) => $s->where('quantity', '>', 0)),
                        blank: fn ($query) => $query,
                    ),
            ])
            ->actions([Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make()])
            ->bulkActions([Tables\Actions\BulkActionGroup::make([
                Tables\Actions\BulkAction::make('setCategoria')
                    ->label('Asignar categoría')
                    ->icon('heroicon-o-tag')
                    ->form([Forms\Components\Select::make('categoria')->label('Categoría')->options(\App\Models\Product::categoriaLabels())->required()])
                    ->action(fn ($records, array $data) => $records->each->update(['categoria' => $data['categoria']]))
                    ->deselectRecordsAfterCompletion(),
                Tables\Actions\DeleteBulkAction::make(),
            ])]);
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
