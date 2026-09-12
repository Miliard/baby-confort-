<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;

/**
 * Una conversación de WhatsApp con un cliente.
 */
class WaConversacion extends Model
{
    protected $table = 'wa_conversaciones';

    protected $fillable = [
        'wa_id', 'telefono', 'nombre', 'ultimo_texto', 'ultimo_mensaje_at',
        'ultimo_del_cliente_at', 'agente_id', 'tomada_at', 'sin_leer', 'archivada',
    ];

    protected $casts = [
        'ultimo_mensaje_at'     => 'datetime',
        'ultimo_del_cliente_at' => 'datetime',
        'tomada_at'             => 'datetime',
        'archivada'             => 'boolean',
        'sin_leer'              => 'integer',
    ];

    public function mensajes(): HasMany
    {
        return $this->hasMany(WaMensaje::class, 'conversacion_id');
    }

    public function agente(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'agente_id');
    }

    /**
     * Hora del último mensaje, en hora de El Salvador.
     *
     * Si fue hoy muestra la hora; si fue antes, la fecha. Es lo que hace
     * WhatsApp y evita confundir un mensaje de ayer con uno de hace un rato.
     */
    public function horaUltimo(): string
    {
        if (! $this->ultimo_mensaje_at) return '';

        $local = $this->ultimo_mensaje_at->timezone(config('app.zona_local'));
        $hoy   = now()->timezone(config('app.zona_local'));

        if ($local->isSameDay($hoy))               return $local->format('H:i');
        if ($local->isSameDay($hoy->copy()->subDay())) return 'Ayer';

        return $local->format('d/m');
    }

    /** Las etiquetas que lleva puestas: puede tener varias a la vez. */
    public function etiquetas(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(
            WaEtiqueta::class,
            'wa_conversacion_etiqueta',
            'conversacion_id',
            'etiqueta_id'
        );
    }

    public static function hayTabla(): bool
    {
        try {
            return Schema::hasTable('wa_conversaciones');
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** Los últimos 8 dígitos: así se identifica a la gente en El Salvador. */
    public static function telefonoCorto(?string $numero): string
    {
        $d = preg_replace('/\D/', '', (string) $numero);
        return strlen($d) >= 8 ? substr($d, -8) : $d;
    }

    /** Busca la conversación de ese número, o la crea. */
    public static function deNumero(string $waId, ?string $nombre = null): self
    {
        $conv = static::firstOrNew(['wa_id' => preg_replace('/\D/', '', $waId)]);

        $conv->telefono = static::telefonoCorto($waId);
        if ($nombre && ! $conv->nombre) $conv->nombre = mb_substr($nombre, 0, 120);

        if (! $conv->exists) $conv->sin_leer = 0;

        $conv->save();

        return $conv;
    }

    /**
     * Cuánto queda de la ventana de 24 horas de Meta, en minutos.
     * Devuelve 0 si ya se cerró: ahí solo se pueden mandar plantillas.
     */
    public function minutosDeVentana(): int
    {
        if (! $this->ultimo_del_cliente_at) return 0;

        $cierra = $this->ultimo_del_cliente_at->copy()->addHours(24);

        return $cierra->isFuture() ? (int) now()->diffInMinutes($cierra) : 0;
    }

    public function ventanaAbierta(): bool
    {
        return $this->minutosDeVentana() > 0;
    }

    /** "18 h 20 min" o "quedan 35 min", para mostrarlo en el chat. */
    public function ventanaLegible(): string
    {
        $m = $this->minutosDeVentana();
        if ($m <= 0) return 'cerrada';

        $h = intdiv($m, 60);
        $r = $m % 60;

        return $h > 0 ? "{$h} h {$r} min" : "{$r} min";
    }

    /** Nombre para mostrar: el del cliente, o el teléfono con formato. */
    public function comoSeLlama(): string
    {
        if (trim((string) $this->nombre) !== '') return $this->nombre;

        $d = $this->telefono;
        return strlen($d) === 8 ? substr($d, 0, 4) . ' ' . substr($d, 4) : $d;
    }

    /** Si ese número ya está en la libreta, traemos sus datos. */
    public function cliente(): ?array
    {
        try {
            return \App\Models\Cliente::buscar($this->telefono);
        } catch (\Throwable $e) {
            return null;
        }
    }
}
