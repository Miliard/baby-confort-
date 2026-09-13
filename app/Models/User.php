<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'solo_chat',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Quién entra a cada panel.
     *
     *  · /chat  → todos: es el panel de mensajes, para eso está
     *  · /admin → solo quien NO esté marcado como "solo chat"
     *
     * Así los colaboradores contestan WhatsApp sin poder llegar al Cierre del
     * día ni a las remuneraciones. Si la columna todavía no existe (porque la
     * migración no corrió), se deja pasar: nadie se queda afuera por eso.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        if ($panel->getId() !== 'admin') return true;

        return ! (bool) ($this->solo_chat ?? false);
    }

    /** Los colores que se reparten entre las personas del equipo. */
    public const COLORES = ['#4aa3df', '#e5a23f', '#b06cd6', '#e5695f', '#2fb0a0', '#d4679a'];

    /**
     * Un color fijo por persona, para reconocer de un vistazo quién atiende.
     *
     * Sale del identificador, así que no hay que elegirlo a mano y nunca
     * cambia. Se usa igual en la lista de conversaciones y en la firma de cada
     * mensaje, para que sea el mismo en los dos lados.
     */
    public function colorAgente(): string
    {
        return static::COLORES[$this->id % count(static::COLORES)];
    }
}
