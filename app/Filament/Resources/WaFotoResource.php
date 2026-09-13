<?php

namespace App\Filament\Resources;

use App\Filament\Resources\WaFotoResource\Pages;
use App\Models\WaFoto;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Las fotos que se mandan seguido por WhatsApp.
 *
 * No son las del catálogo de la tienda: acá van las de venta — el producto
 * puesto, el empaque abierto, lo que haga falta mostrar en una conversación.
 */
class WaFotoResource extends Resource
{
    protected static ?string $model = WaFoto::class;

    protected static ?string $navigationIcon = 'heroicon-o-photo';
    protected static ?string $navigationLabel = 'Fotos para mandar';
    protected static ?string $navigationGroup = 'WhatsApp';
    protected static ?string $modelLabel = 'foto';
    protected static ?string $pluralModelLabel = 'fotos';
    protected static ?int $navigationSort = 4;

    public static function canAccess(): bool
    {
        return ! (bool) (auth()->user()?->solo_chat ?? false);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\FileUpload::make('ruta')
                ->label('La foto')
                ->image()
                ->disk('public')
                ->directory('whatsapp/galeria')
                ->imageEditor()
                ->maxSize(5120)
                ->helperText('Hasta 5 MB. Se manda tal cual, así que conviene que ya venga recortada.')
                ->required(),

            Forms\Components\TextInput::make('titulo')
                ->label('Nombre')
                ->helperText('Solo lo ves vos al elegirla. Ej: "Talla M puesta", "Empaque abierto".')
                ->required()
                ->maxLength(60),

            Forms\Components\Textarea::make('pie')
                ->label('Texto que va debajo de la foto')
                ->helperText('Lo que lee el cliente. Podés dejarlo vacío y mandar solo la foto.')
                ->rows(3),

            Forms\Components\TextInput::make('orden')
                ->label('Orden')
                ->helperText('Número más bajo, más arriba en la lista.')
                ->numeric()
                ->default(0),

            Forms\Components\Toggle::make('activa')
                ->label('Disponible en el chat')
                ->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('orden')
            ->columns([
                Tables\Columns\ImageColumn::make('ruta')
                    ->label('Foto')
                    ->disk('public')
                    ->height(54),

                Tables\Columns\TextColumn::make('titulo')
                    ->label('Nombre')
                    ->weight('bold')
                    ->searchable(),

                Tables\Columns\TextColumn::make('pie')
                    ->label('Texto')
                    ->limit(60)
                    ->tooltip(fn ($record) => $record->pie),

                Tables\Columns\IconColumn::make('activa')
                    ->label('Disponible')
                    ->boolean(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->emptyStateHeading('Todavía no hay fotos')
            ->emptyStateDescription('Subí las que mandás seguido: el producto puesto, '
                . 'el empaque abierto, lo que te pidan ver antes de comprar.');
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListWaFotos::route('/'),
            'create' => Pages\CreateWaFoto::route('/create'),
            'edit'   => Pages\EditWaFoto::route('/{record}/edit'),
        ];
    }
}
