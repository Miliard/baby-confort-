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
        'conversacion_id', 'wa_message_id', 'responde_a', 'direccion', 'tipo', 'texto',
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
     * La hora como la ve quien está atendiendo, no como la guarda la base.
     *
     * Se guarda en UTC y El Salvador va seis horas atrás: sin esta conversión,
     * un mensaje de las ocho de la mañana aparecía como las dos de la tarde.
     */
    public function hora(): string
    {
        if (! $this->created_at) return '';

        // De 12 horas y con a.m./p.m. en minúscula, como se escribe acá.
        return strtolower(
            $this->created_at->timezone(config('app.zona_local'))->format('g:i a')
        );
    }

    /** Con el día, para cuando la conversación es de otra fecha. */
    public function fechaYHora(): string
    {
        if (! $this->created_at) return '';

        return strtolower(
            $this->created_at->timezone(config('app.zona_local'))->format('d/m/Y g:i a')
        );
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

    public function esAudio(): bool
    {
        return in_array($this->tipo, ['audio', 'voice'], true);
    }

    /** El mensaje al que responde, si es una respuesta a uno puntual. */
    public function citado(): ?self
    {
        if (blank($this->responde_a)) return null;

        try {
            return static::where('wa_message_id', $this->responde_a)->first();
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** Un pedacito del texto, para mostrarlo dentro de la cita. */
    public function resumen(int $largo = 90): string
    {
        $t = trim((string) $this->texto);

        if ($t === '') {
            return $this->tipo === 'image' ? '📷 Foto' : '[' . $this->tipo . ']';
        }

        return \Illuminate\Support\Str::limit(preg_replace('/\s+/u', ' ', $t), $largo);
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

    /**
     * Un color fijo por persona, para saber de un vistazo quién contestó.
     *
     * Sale del identificador del usuario, así que cada colaborador tiene
     * siempre el mismo y no hay que configurarlo en ningún lado.
     */
    public function colorFirma(): string
    {
        if ($this->automatico) return '#8ea0b8';
        if (! $this->user_id)  return '#7fa88c';   // salió del teléfono

        // La misma cuenta que usa el modelo de usuarios, para que el color
        // coincida con el de la lista de conversaciones.
        return \App\Models\User::COLORES[$this->user_id % count(\App\Models\User::COLORES)];
    }

    /**
     * La luz del semáforo.
     *
     * Misma lógica que las palomitas de WhatsApp —uno si salió, dos si le
     * llegó— pero con puntos en vez de palomitas y con color. Dos palomitas
     * grises y dos palomitas azules son casi el mismo dibujo y hay que
     * fijarse; un punto verde y uno amarillo se distinguen sin mirar.
     *
     * Así se lee de dos maneras a la vez: por la cantidad y por el color.
     * Quien esté acostumbrado a las palomitas no pierde nada.
     */
    public function marcaEstado(): string
    {
        return match ($this->estado) {
            'enviando'  => '◌',     // todavía en camino
            'enviado'   => '●',     // un punto: salió
            'entregado' => '●●',    // dos puntos: le llegó
            'leido'     => '●●',    // dos puntos, y en verde: lo leyó
            'fallido'   => '⚠',
            default     => '',
        };
    }

    /**
     * El color del semáforo. Acá está toda la información.
     *
     * Verde:    lo leyó.
     * Amarillo: le llegó al teléfono, pero no lo ha abierto.
     * Gris:     salió, todavía sin confirmar nada.
     * Rojo:     no se pudo mandar.
     *
     * Los tonos son fuertes a propósito: en el teléfono, al sol, un color
     * apagado no se distingue de otro.
     */
    public function colorEstado(): string
    {
        return match ($this->estado) {
            'leido'     => '#22c55e',   // verde
            'entregado' => '#eab308',   // amarillo
            'fallido'   => '#ef4444',   // rojo
            default     => '#94a3b8',   // gris
        };
    }

    /** Para el título emergente, que explica qué significa cada color. */
    public function queSignifica(): string
    {
        return match ($this->estado) {
            'enviando'  => 'Mandando…',
            'enviado'   => 'Salió, sin confirmar todavía',
            'entregado' => 'Le llegó al teléfono, pero no lo ha leído',
            'leido'     => 'Ya lo leyó',
            'fallido'   => 'No se pudo mandar',
            default     => '',
        };
    }
}
