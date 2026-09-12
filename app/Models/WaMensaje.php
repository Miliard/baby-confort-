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

    /**
     * Dirección pública de la imagen, si la hay.
     *
     * Las que manda el cliente se guardan en el disco y vienen como ruta
     * suelta. Las que mandamos nosotros salen del catálogo y ya son una
     * dirección del sitio, así que se dejan tal cual.
     */
    public function url(): ?string
    {
        if (blank($this->media_ruta)) return null;

        if (str_starts_with($this->media_ruta, 'http') || str_starts_with($this->media_ruta, '/')) {
            return $this->media_ruta;
        }

        return '/storage/' . ltrim($this->media_ruta, '/');
    }

    /**
     * Quién lo mandó, para mostrarlo bajo el globo del mensaje.
     *
     * Saliente, sin agente y sin ser automático solo puede significar una cosa:
     * salió del teléfono, escrito a mano en la app de WhatsApp Business.
     */
    public function firma(): string
    {
        if ($this->esDelCliente()) return '';
        if ($this->automatico)     return 'Automático';

        return $this->agente?->name ?: 'Desde el teléfono';
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
