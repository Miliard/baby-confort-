<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ReviewResource\Pages;
use App\Models\Review;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ReviewResource extends Resource
{
    protected static ?string $model = Review::class;
    protected static ?string $navigationIcon = 'heroicon-o-star';
    protected static ?string $navigationLabel = 'Reseñas';
    protected static ?string $modelLabel = 'Reseña';
    protected static ?string $pluralModelLabel = 'Reseñas';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Reseña')->schema([
                Forms\Components\FileUpload::make('image_upload')->label('Captura de Facebook / WhatsApp (recomendado)')
                    ->image()->disk('public')->directory('resenas')->imageEditor()->columnSpanFull()
                    ->helperText('Sube una captura del comentario real. Si subes captura, no necesitas escribir el texto.'),
                Forms\Components\TextInput::make('name')->label('Nombre del cliente')->placeholder('Ej: María G.'),
                Forms\Components\Select::make('rating')->label('Estrellas')
                    ->options([5 => '★★★★★', 4 => '★★★★', 3 => '★★★'])->default(5),
                Forms\Components\Textarea::make('text')->label('Comentario (si no subes captura)')->rows(3)->columnSpanFull(),
                Forms\Components\Toggle::make('active')->label('Activa (visible en la tienda)')->default(true),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->reorderable('orden')
            ->defaultSort('orden')
            ->columns([
                Tables\Columns\ImageColumn::make('image_upload')->label('Captura')->disk('public'),
                Tables\Columns\TextColumn::make('name')->label('Cliente')->searchable(),
                Tables\Columns\TextColumn::make('text')->label('Comentario')->limit(40)->wrap(),
                Tables\Columns\TextColumn::make('rating')->label('★')->formatStateUsing(fn ($s) => str_repeat('★', (int) $s)),
                Tables\Columns\IconColumn::make('active')->label('Activa')->boolean(),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->label('Editar'),
                Tables\Actions\DeleteAction::make()->label('Borrar'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListReviews::route('/'),
            'create' => Pages\CreateReview::route('/create'),
            'edit'   => Pages\EditReview::route('/{record}/edit'),
        ];
    }
}
