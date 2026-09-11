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
}
