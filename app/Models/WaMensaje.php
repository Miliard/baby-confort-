<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un mensaje dentro de una conversación de WhatsApp.
 */
class WaMensaje extends Model
{
    protected $table = 'wa_mensajes';

    protected $fillable = [
        'conversacion_id', 'wa_message_id', 'direccion', 'tipo', 'texto',
        'media_id', 'media_ruta', 'estado', 'error', 'user_id', 'automatico',
    ];

    protected $casts = ['automatico' => 'boolean'];

    public function conversacion(): BelongsTo
    {
        return $this->belongsTo(WaConversacion::class, 'conversacion_id');
    }

    public function agente(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'user_id');
    }

    public function esDelCliente(): bool
    {
        return $this->direccion === 'entrante';
    }

    /** Dirección pública de la imagen guardada, si la hay. */
    public function url(): ?string
    {
        return $this->media_ruta ? '/storage/' . ltrim($this->media_ruta, '/') : null;
    }

    /** Quién lo mandó, para mostrarlo bajo el globo del mensaje. */
    public function firma(): string
    {
        if ($this->esDelCliente()) return '';
        if ($this->automatico)     return 'Automático';

        return $this->agente?->name ?: 'Baby-Confort';
    }

    /** ✓ enviado · ✓✓ entregado · ✓✓ leído · ⚠ falló */
    public function marcaEstado(): string
    {
        return match ($this->estado) {
            'enviando'  => '···',
            'enviado'   => '✓',
            'entregado' => '✓✓',
            'leido'     => '✓✓',
            'fallido'   => '⚠',
            default     => '',
        };
    }
}
