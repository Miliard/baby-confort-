<?php

namespace App\Filament\Resources\ProductResource\RelationManagers;

use App\Models\ProductSize;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Las tallas de un producto, como tabla y no como formulario largo.
 *
 * Antes cada talla eran trece campos uno debajo del otro, y lo que más se
 * toca —precio y existencia— quedaba al fondo de cada una, debajo del
 * texto y de tres campos de fotos. Con cinco tallas era bajar y bajar.
 *
 * Ahora:
 *  · cada talla es UN renglón, con foto, precio y existencia a la vista;
 *  · precio, precio antes y existencia se cambian ahí mismo, sin abrir nada
 *    (se guarda solo al salir del cuadrito);
 *  · lo demás (texto, fotos, combo) se abre en una ventana con "Editar",
 *    ordenado de lo que más se cambia a lo que menos.
 */
class SizesRelationManager extends RelationManager
{
    protected static string $relationship = 'sizes';

    protected static ?string $title = 'Tallas, precios y existencia';

    protected static ?string $modelLabel = 'talla';

    protected static ?string $pluralModelLabel = 'tallas';

    protected static ?string $icon = 'heroicon-o-tag';

    // En la pestaña, cuántas tallas tiene y cuántas están agotadas.
    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        $total = $ownerRecord->sizes()->count();
        return $total ? (string) $total : null;
    }

    public static function getBadgeColor(Model $ownerRecord, string $pageClass): ?string
    {
        return $ownerRecord->sizes()->where('quantity', '<=', 0)->exists() ? 'danger' : 'success';
    }

    public static function getBadgeTooltip(Model $ownerRecord, string $pageClass): ?string
    {
        $agotadas = $ownerRecord->sizes()->where('quantity', '<=', 0)->count();
        return $agotadas ? $agotadas . ' agotada(s)' : 'Todas con existencia';
    }

    // La ventana de "Editar" / "Nueva talla".
    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Precio y existencia')
                ->icon('heroicon-o-currency-dollar')
                ->schema([
                    Forms\Components\TextInput::make('size')->label('Talla')->required()
                        ->placeholder('M, XL, 180 ML'),
                    Forms\Components\TextInput::make('price')->label('Precio')->numeric()->prefix('$')->required(),
                    Forms\Components\TextInput::make('price_before')->label('Precio antes')->numeric()->prefix('$')
                        ->hint('Tachado, si es mayor'),
                    Forms\Components\TextInput::make('quantity')->label('Existencia')->numeric()->default(0)->required(),

                    Forms\Components\TextInput::make('weight')->label('Peso del bebé')->placeholder('6-11 kg'),
                    Forms\Components\TextInput::make('unidades')->label('Unidades que trae')->numeric()->minValue(1)
                        ->placeholder('42'),
                    Forms\Components\TextInput::make('combo_qty')->label('Combo: cuántos')->numeric()->minValue(2)
                        ->placeholder('Opcional'),
                    Forms\Components\TextInput::make('combo_price')->label('Combo: precio')->numeric()->prefix('$')
                        ->placeholder('Opcional'),
                ])->columns(['default' => 2, 'md' => 4]),

            Forms\Components\Section::make('Fotos de esta talla')
                ->icon('heroicon-o-photo')
                ->collapsible()
                ->schema([
                    Forms\Components\FileUpload::make('image_upload')->label('Foto (al elegir esta talla, la principal cambia a esta)')
                        ->image()->disk('public')->directory('productos/tallas'),
                    Forms\Components\TextInput::make('image')->label('…o el link de una foto')->url()
                        ->placeholder('https://…'),
                    Forms\Components\FileUpload::make('fotos_uso')
                        ->label('📷 Cómo queda puesto — solo para WhatsApp, no salen en la página')
                        ->image()->multiple()->reorderable()
                        ->disk('public')->directory('productos/uso')
                        ->maxSize(5120)->panelLayout('grid')
                        ->columnSpanFull(),
                ])->columns(['default' => 1, 'md' => 2]),

            Forms\Components\Section::make('Texto que ve el cliente al elegir esta talla')
                ->icon('heroicon-o-document-text')
                ->collapsible()
                ->collapsed(fn (?ProductSize $record) => filled($record?->details))
                ->schema([
                    Forms\Components\Textarea::make('details')->hiddenLabel()->rows(6)
                        ->placeholder('Se puede usar emojis y saltos de línea.'),
                ]),
        ]);
    }

    public function table(Table $table): Table
    {
        // Precio antes vacío tiene que quedar NULL, no "" (la columna es número).
        $guardar = fn (string $campo) => function (ProductSize $record, $state) use ($campo) {
            $state = is_string($state) ? trim($state) : $state;
            $record->update([$campo => ($state === '' || $state === null) ? null : $state]);
            return $state;
        };

        return $table
            ->recordTitleAttribute('size')
            ->defaultSort('id')
            ->paginated(false)
            ->columns([
                Tables\Columns\ImageColumn::make('foto')->label('')
                    ->getStateUsing(function (ProductSize $r) {
                        $u = $r->imageUrl();
                        return $u ? (str_starts_with($u, 'http') ? $u : url($u)) : null;
                    })
                    ->square()->size(44),

                Tables\Columns\TextColumn::make('size')->label('Talla')
                    ->weight('bold')
                    ->description(fn (ProductSize $r) => collect([
                        $r->weight,
                        $r->unidades ? $r->unidades . ' unid.' : null,
                    ])->filter()->implode(' · ') ?: null),

                Tables\Columns\TextInputColumn::make('price')->label('Precio $')
                    ->type('number')->step('0.01')
                    ->rules(['required', 'numeric', 'min:0'])
                    ->extraAttributes(['style' => 'min-width:6rem']),

                Tables\Columns\TextInputColumn::make('price_before')->label('Antes $')
                    ->type('number')->step('0.01')
                    ->rules(['nullable', 'numeric', 'min:0'])
                    ->updateStateUsing($guardar('price_before'))
                    ->extraAttributes(['style' => 'min-width:6rem'])
                    ->visibleFrom('md'),

                Tables\Columns\TextInputColumn::make('quantity')->label('Existencia')
                    ->type('number')->step('1')
                    ->rules(['required', 'integer', 'min:0'])
                    ->extraAttributes(['style' => 'min-width:5.5rem']),

                Tables\Columns\TextColumn::make('estado')->label('')
                    ->getStateUsing(fn (ProductSize $r) => $r->quantity <= 0 ? 'Agotada' : ($r->quantity <= 5 ? 'Quedan pocas' : 'Hay'))
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'Agotada'      => 'danger',
                        'Quedan pocas' => 'warning',
                        default        => 'success',
                    }),

                Tables\Columns\TextColumn::make('combo')->label('Combo')
                    ->getStateUsing(fn (ProductSize $r) => ($r->combo_qty && (float) $r->combo_price > 0)
                        ? $r->combo_qty . ' × $' . number_format((float) $r->combo_price, 2)
                        : null)
                    ->placeholder('—')
                    ->visibleFrom('lg'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Nueva talla')->icon('heroicon-m-plus')
                    ->modalHeading('Nueva talla')
                    ->modalWidth('4xl')
                    ->createAnother(false),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label('Editar')->iconButton()->tooltip('Editar todo: fotos, texto, combo')
                    ->modalHeading(fn (ProductSize $r) => 'Talla ' . $r->size)
                    ->modalWidth('4xl'),

                Tables\Actions\ReplicateAction::make()
                    ->label('Duplicar')->iconButton()->tooltip('Hacer una copia para crear otra talla parecida')
                    ->modalHeading('Duplicar talla')
                    ->modalDescription('Se crea una copia con todo igual; después le cambiás la talla y el precio.')
                    ->beforeReplicaSaved(function (ProductSize $replica) {
                        $replica->size = $replica->size . ' (copia)';
                    })
                    ->successNotificationTitle('Talla duplicada'),

                Tables\Actions\DeleteAction::make()
                    ->iconButton()->tooltip('Borrar esta talla'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('agotar')
                        ->label('Marcar agotadas (existencia 0)')
                        ->icon('heroicon-o-no-symbol')
                        ->requiresConfirmation()
                        ->action(fn ($records) => $records->each->update(['quantity' => 0]))
                        ->deselectRecordsAfterCompletion(),
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('Este producto todavía no tiene tallas')
            ->emptyStateDescription('Tocá "Nueva talla" para agregar la primera.')
            ->emptyStateIcon('heroicon-o-tag');
    }
}
