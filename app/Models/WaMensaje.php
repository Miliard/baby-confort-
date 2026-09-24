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

    /**
     * El etiquetado automático se engancha acá y no en cada lugar que manda.
     *
     * Todos los caminos por los que entra o sale un mensaje —el panel, la app
     * del teléfono por el eco, el webhook del cliente, las respuestas
     * automáticas— terminan creando un WaMensaje. Enganchándolo en un solo
     * punto, ninguno se escapa, y el día que aparezca un camino nuevo tampoco.
     */
    protected static function booted(): void
    {
        static::created(function (WaMensaje $m) {
            try {
                \App\Services\Etiquetado::alGuardarMensaje($m->conversacion, $m->texto);
            } catch (\Throwable $e) {
                // Guardar el mensaje es lo que no puede fallar. Lo demás es
                // comodidad.
            }
        });
    }

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
     * El texto del mensaje con los enlaces ya tocables.
     *
     * Blade escapa todo lo que imprime, que es lo correcto —un cliente puede
     * mandar cualquier cosa y no queremos que se ejecute— pero eso dejaba los
     * enlaces como texto muerto. Acá se escapa igual, a mano, y solo después se
     * arman las etiquetas de enlace. El orden importa: escapar primero,
     * enlazar después.
     *
     * Solo http y https. Nada de "javascript:" ni esquemas raros: aunque el
     * cliente los mande, no se convierten en enlace.
     */
    public function textoHtml(): \Illuminate\Support\HtmlString
    {
        $t = (string) ($this->texto ?? '');

        if ($t === '') return new \Illuminate\Support\HtmlString('');

        $partes = preg_split(
            '~(https?://[^\s<>"\']+)~iu',
            $t,
            -1,
            PREG_SPLIT_DELIM_CAPTURE
        );

        if ($partes === false) return new \Illuminate\Support\HtmlString(e($t));

        $salida = '';

        foreach ($partes as $i => $p) {
            // Con un solo grupo de captura, los impares son los enlaces.
            if ($i % 2 === 0) {
                $salida .= e($p);
                continue;
            }

            // El punto final de la oración no es parte del enlace. Se le
            // devuelve al texto para que el enlace no se rompa.
            $cola = '';
            while ($p !== '' && str_contains('.,;:!?)]}', substr($p, -1))) {
                $cola = substr($p, -1) . $cola;
                $p = substr($p, 0, -1);
            }

            if ($p === '') {
                $salida .= e($cola);
                continue;
            }

            $u = e($p);

            $salida .= '<a href="' . $u . '" target="_blank" rel="noopener noreferrer" '
                     . 'class="wa-enlace">' . $u . '</a>' . e($cola);
        }

        return new \Illuminate\Support\HtmlString($salida);
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

    /**
     * La fecha sola, en hora de acá. Sirve para saber cuándo cambia el día.
     *
     * Se compara por este valor y no por created_at directo: created_at está
     * en UTC, así que un mensaje de las 8 de la noche ya cae en el día
     * siguiente y la separación quedaría corrida seis horas.
     */
    public function diaClave(): string
    {
        if (! $this->created_at) return '';

        return $this->created_at->timezone(config('app.zona_local'))->format('Y-m-d');
    }

    /**
     * Cómo se escribe ese día en la separación del chat.
     *
     * "Hoy" y "Ayer" primero, porque es lo que uno piensa. Después el nombre
     * del día, que para esta semana dice más que un número. Y de ahí para
     * atrás, la fecha completa.
     */
    public function diaLegible(): string
    {
        if (! $this->created_at) return '';

        $zona  = config('app.zona_local');
        $fecha = $this->created_at->copy()->timezone($zona);
        $hoy   = now()->timezone($zona);

        if ($fecha->isSameDay($hoy))                   return 'Hoy';
        if ($fecha->isSameDay($hoy->copy()->subDay())) return 'Ayer';

        $dias  = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];
        $meses = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio',
                  'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];

        $texto = $dias[(int) $fecha->format('w')] . ' ' . $fecha->format('j')
               . ' de ' . $meses[(int) $fecha->format('n') - 1];

        // De otro año, el año también: si no, un mensaje de septiembre pasado
        // se lee como si fuera de este.
        if ($fecha->format('Y') !== $hoy->format('Y')) {
            $texto .= ' de ' . $fecha->format('Y');
        }

        return $texto;
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
