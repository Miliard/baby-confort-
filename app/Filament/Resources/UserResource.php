<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Hash;

/**
 * Las cuentas que pueden entrar.
 *
 * Vive solo en el panel /admin: el panel de mensajes no la descubre, así que un
 * colaborador no puede llegar acá ni darse permisos a sí mismo.
 */
class UserResource extends Resource
{
    protected static ?string $model = User::class;
    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static ?string $navigationLabel = 'Usuarios';
    protected static ?string $modelLabel = 'usuario';
    protected static ?string $pluralModelLabel = 'Usuarios';
    protected static ?int $navigationSort = 90;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')
                ->label('Nombre')->required()->maxLength(80)
                ->helperText('Aparece firmando los mensajes que manda esta persona.'),

            Forms\Components\TextInput::make('email')
                ->label('Correo (con esto entra)')
                ->email()->required()->unique(ignoreRecord: true),

            Forms\Components\TextInput::make('password')
                ->label('Contraseña')
                ->password()->revealable()
                // Solo se pide al crear; al editar, en blanco = no se cambia.
                ->required(fn (string $operation) => $operation === 'create')
                ->dehydrated(fn ($state) => filled($state))
                ->dehydrateStateUsing(fn ($state) => Hash::make($state))
                ->helperText('Al editar, dejala en blanco si no querés cambiarla.'),

            Forms\Components\Toggle::make('solo_chat')
                ->label('Solo puede atender WhatsApp')
                ->helperText('Encendido: entra a baby-confort.shop/whatsapp y nada más. '
                    . 'No ve Cierre del día, remuneraciones ni productos. '
                    . 'Es lo que corresponde para los colaboradores.')
                ->default(false),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Nombre')
                    ->weight('bold')->searchable(),

                Tables\Columns\TextColumn::make('email')->label('Correo')
                    ->searchable()->color('gray'),

                Tables\Columns\IconColumn::make('solo_chat')
                    ->label('Solo WhatsApp')->boolean()
                    ->trueIcon('heroicon-o-chat-bubble-left-right')
                    ->falseIcon('heroicon-o-key')
                    ->trueColor('success')->falseColor('warning')
                    ->tooltip(fn (User $record) => $record->solo_chat
                        ? 'Solo entra al panel de mensajes'
                        : 'Entra a todo el admin'),

                Tables\Columns\TextColumn::make('created_at')->label('Creado')
                    ->date('d/m/Y')->color('gray')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),

                // No se puede borrar la propia cuenta: sería quedarse afuera.
                Tables\Actions\DeleteAction::make()
                    ->visible(fn (User $record) => $record->id !== auth()->id()),
            ])
            ->emptyStateHeading('Solo está tu cuenta')
            ->emptyStateDescription('Creá una por cada colaborador y encendé "Solo puede atender WhatsApp".');
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/crear'),
            'edit'   => Pages\EditUser::route('/{record}/editar'),
        ];
    }
}
