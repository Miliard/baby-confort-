<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RespuestaRapidaResource\Pages;
use App\Models\RespuestaRapida;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Los textos que se mandan con un toque desde el chat.
 *
 * La idea es que Wil los cree y los corrija solo: cambiar un precio no debería
 * necesitar un despliegue ni pedirle nada a nadie.
 */
class RespuestaRapidaResource extends Resource
{
    protected static ?string $model = RespuestaRapida::class;

    protected static ?string $navigationIcon = 'heroicon-o-bolt';
    protected static ?string $navigationLabel = 'Respuestas rápidas';
    protected static ?string $navigationGroup = 'WhatsApp';
    protected static ?string $modelLabel = 'respuesta rápida';
    protected static ?string $pluralModelLabel = 'respuestas rápidas';
    protected static ?int $navigationSort = 2;

    /**
     * Los colaboradores de solo chat las usan, pero no las cambian: un precio
     * mal escrito acá se le manda a todos los clientes del día.
     */
    public static function canAccess(): bool
    {
        return ! (bool) (auth()->user()?->solo_chat ?? false);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('titulo')
                ->label('Título del botón')
                ->helperText('Corto, porque es lo que se lee en el chat. Ej: "Precios", "Formas de pago".')
                ->required()
                ->maxLength(60),

            Forms\Components\Textarea::make('texto')
                ->label('Mensaje que se manda')
                ->helperText('Podés usar *negrita* como en WhatsApp. Se copia al cuadro de texto '
                           . 'y se puede editar antes de mandarlo.')
                ->required()
                ->rows(8),

            Forms\Components\FileUpload::make('imagen')
                ->label('Foto (opcional)')
                ->helperText('Si le ponés foto, el mensaje sale COMO FOTO y el texto de arriba '
                           . 'va de pie. Hay respuestas que se explican mejor con una imagen '
                           . 'que con tres párrafos. Hasta 5 MB.')
                ->image()
                ->disk('public')
                ->directory('whatsapp/rapidas')
                ->imageEditor()
                ->maxSize(5120),

            Forms\Components\TextInput::make('orden')
                ->label('Orden')
                ->helperText('Las de número más bajo salen primero. Poné las que más usás arriba.')
                ->numeric()
                ->default(0),

            Forms\Components\Toggle::make('activa')
                ->label('Visible en el chat')
                ->helperText('Apagala para esconderla sin borrarla, por ejemplo una promoción que terminó.')
                ->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('orden')
            ->columns([
                Tables\Columns\TextColumn::make('orden')
                    ->label('#')
                    ->sortable()
                    ->width('60px'),

                Tables\Columns\ImageColumn::make('imagen')
                    ->label('Foto')
                    ->disk('public')
                    ->height(38)
                    ->width(38),

                Tables\Columns\TextColumn::make('titulo')
                    ->label('Botón')
                    ->weight('bold')
                    ->searchable(),

                Tables\Columns\TextColumn::make('texto')
                    ->label('Mensaje')
                    ->limit(70)
                    ->tooltip(fn ($record) => $record->texto)
                    ->searchable(),

                Tables\Columns\IconColumn::make('activa')
                    ->label('Visible')
                    ->boolean(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->emptyStateHeading('Todavía no hay respuestas rápidas')
            ->emptyStateDescription('Creá las que escribís diez veces al día: precios, formas de '
                . 'pago, hasta dónde llega el envío, horarios de entrega.');
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListRespuestasRapidas::route('/'),
            'create' => Pages\CreateRespuestaRapida::route('/create'),
            'edit'   => Pages\EditRespuestaRapida::route('/{record}/edit'),
        ];
    }
}
