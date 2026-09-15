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
        'wa_id', 'telefono', 'nombre', 'alias', 'ultimo_texto', 'ultimo_mensaje_at',
        'ultimo_saliente', 'ultimo_estado',
        'ultimo_del_cliente_at', 'agente_id', 'tomada_at', 'sin_leer', 'archivada',
        'fijada_at',
    ];

    protected $casts = [
        'ultimo_mensaje_at'     => 'datetime',
        'ultimo_del_cliente_at' => 'datetime',
        'tomada_at'             => 'datetime',
        'fijada_at'             => 'datetime',
        'archivada'             => 'boolean',
        'sin_leer'              => 'integer',
    ];

    /** ¿Está clavada arriba de la lista? */
    public function fijada(): bool
    {
        return ! is_null($this->fijada_at ?? null);
    }

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

        if ($local->isSameDay($hoy))                   return strtolower($local->format('g:i a'));
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

    /**
     * El teléfono en grupos de cuatro, que es como se lee en El Salvador.
     *
     * Es lo que se muestra como título de cada conversación: el nombre lo pone
     * el cliente en su perfil y suele ser un apodo o un emoji, así que no
     * sirve para reconocerlo ni para cruzarlo con las guías.
     */
    public function telefonoLegible(): string
    {
        $d = preg_replace('/\D/', '', (string) $this->telefono);

        // Si viene con el 503 adelante, se muestra sin él.
        if (strlen($d) === 11 && str_starts_with($d, '503')) $d = substr($d, 3);

        return strlen($d) === 8 ? substr($d, 0, 4) . ' ' . substr($d, 4) : ($d ?: '—');
    }

    /**
     * Cómo se lo reconoce, debajo del teléfono.
     *
     * Primero el nombre que le puso Wil, que es el que sirve: "Marta San
     * Miguel". Si no tiene, el del perfil del cliente, que suele ser un apodo
     * o un emoji y por eso va en segundo lugar.
     */
    public function apodo(): ?string
    {
        $mio = trim((string) ($this->alias ?? ''));
        if ($mio !== '') return $mio;

        $n = trim((string) $this->nombre);
        if ($n === '') return null;

        return preg_replace('/\D/', '', $n) === preg_replace('/\D/', '', (string) $this->telefono)
            ? null
            : $n;
    }

    /**
     * Deja anotado de quién fue el último mensaje y cómo le fue.
     *
     * Se llama cada vez que entra o sale uno. Con esto la lista puede mostrar
     * las palomitas sin ir a buscar el mensaje a la base.
     */
    public function anotarUltimo(string $texto, bool $saliente, ?string $estado = null): void
    {
        $this->ultimo_texto      = mb_substr(trim($texto), 0, 300);
        $this->ultimo_mensaje_at = now();
        $this->ultimo_saliente   = $saliente;
        $this->ultimo_estado     = $saliente ? $estado : null;
    }

    /** ¿Estoy esperando contestarle? */
    public function sinResponder(): bool
    {
        return ! (bool) ($this->ultimo_saliente ?? false);
    }

    /**
     * El semáforo del último mensaje, solo si fue nuestro.
     * Un punto salió, dos le llegó; el color dice si lo leyó.
     */
    public function marcaUltimo(): string
    {
        if ($this->sinResponder()) return '';

        return match ($this->ultimo_estado) {
            'enviando'  => '◌',
            'entregado' => '●●',
            'leido'     => '●●',
            'fallido'   => '⚠',
            default     => '●',
        };
    }

    /** Verde leyó · amarillo le llegó · gris salió · rojo falló. */
    public function colorUltimo(): string
    {
        return match ($this->ultimo_estado) {
            'leido'     => '#22c55e',
            'entregado' => '#eab308',
            'fallido'   => '#ef4444',
            default     => '#94a3b8',
        };
    }

    /** ¿El nombre que se muestra lo puso Wil o vino del perfil del cliente? */
    public function nombrePropio(): bool
    {
        return trim((string) ($this->alias ?? '')) !== '';
    }

    /**
     * Lo que va en grande arriba.
     *
     * Si le pusiste nombre, manda el nombre: ya no hace falta estar leyendo el
     * número. Si no, el número, que es lo único confiable — el nombre del
     * perfil lo elige el cliente y suele ser un apodo o un emoji.
     */
    public function titulo(): string
    {
        return $this->nombrePropio()
            ? trim((string) $this->alias)
            : $this->telefonoLegible();
    }

    /**
     * Lo que va abajo en chico. Null si no hay nada que agregar.
     *
     * Solo aparece cuando vos le pusiste nombre al contacto: ahí arriba va el
     * nombre y abajo el número, que sigue a mano para cruzarlo con una guía.
     *
     * El nombre del perfil de WhatsApp NO se muestra a propósito. Lo elige el
     * cliente, suele ser un apodo, un emoji o un apellido suelto, no sirve para
     * reconocer a nadie y llena la lista de ruido.
     */
    public function subtitulo(): ?string
    {
        return $this->nombrePropio() ? $this->telefonoLegible() : null;
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
