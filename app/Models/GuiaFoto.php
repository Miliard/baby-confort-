<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

/**
 * Foto del paquete asociada a un número de guía.
 * La guía se lee del código QR de la etiqueta, así la foto siempre queda
 * emparejada con el envío correcto y el cliente la ve en su página de rastreo.
 */
class GuiaFoto extends Model
{
    protected $table = 'guia_fotos';

    protected $fillable = [
        'guia', 'ruta', 'nombre', 'telefono', 'contenido', 'cobrar',
        'lote', 'enviado_at', 'enviado',
    ];

    protected $casts = ['enviado_at' => 'datetime', 'cobrar' => 'decimal:2'];

    /** Datos del pedido (contenido y monto) asociados a una guía. */
    public static function datosDeGuia(?string $guia): ?self
    {
        $g = trim((string) $guia);
        if ($g === '') return null;

        try {
            if (! Schema::hasTable('guia_fotos')) return null;
            return static::where('guia', $g)
                ->orderByRaw('CASE WHEN contenido IS NULL THEN 1 ELSE 0 END')
                ->first();
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Deja un teléfono en sus últimos 8 dígitos, que es lo que identifica
     * a la persona en El Salvador. Así "+503 7123-4567", "7123 4567" y
     * "50371234567" se reconocen como el mismo número.
     */
    public static function telefonoCorto(?string $telefono): ?string
    {
        $d = preg_replace('/\D/', '', (string) $telefono);
        return strlen($d) >= 8 ? substr($d, -8) : ($d !== '' ? $d : null);
    }

    /**
     * Todos los paquetes de un teléfono, del más nuevo al más viejo.
     * Es la llave con la que el cliente rastrea sin necesitar código.
     */
    public static function porTelefono(?string $telefono)
    {
        $corto = static::telefonoCorto($telefono);
        if (! $corto || strlen($corto) < 8) return collect();

        try {
            if (! Schema::hasTable('guia_fotos')) return collect();

            // Se limpian los separadores dentro de la consulta: en la base hay
            // números guardados como "7123-4567", "+503 7123 4567", etc.
            $limpio = "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(telefono,''),' ',''),'-',''),'(',''),')',''),'+','')";

            return static::whereRaw("$limpio LIKE ?", ['%' . $corto])
                ->whereNotNull('guia')->where('guia', '!=', '')
                ->orderByDesc('id')
                ->take(15)
                ->get()
                ->unique('guia')
                ->values();
        } catch (\Throwable $e) {
            return collect();
        }
    }

    /**
     * Pedido ya armado que todavía no tiene número de guía (aún no se sube el
     * Excel a Sistrack ni se importa el PDF). Sirve para decirle al cliente
     * "tu pedido está confirmado" desde el primer momento.
     */
    public static function pendientePorTelefono(?string $telefono): ?self
    {
        $corto = static::telefonoCorto($telefono);
        if (! $corto || strlen($corto) < 8) return null;

        try {
            if (! Schema::hasTable('guia_fotos')) return null;

            $limpio = "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(telefono,''),' ',''),'-',''),'(',''),')',''),'+','')";

            return static::whereRaw("$limpio LIKE ?", ['%' . $corto])
                ->where(function ($q) { $q->whereNull('guia')->orWhere('guia', ''); })
                ->orderByDesc('id')
                ->first();
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Registra el pedido apenas se arma la guía. Si ya hay uno pendiente del
     * mismo teléfono, lo actualiza en vez de duplicar.
     */
    public static function registrarPendiente(array $datos): void
    {
        $telefono = trim((string) ($datos['telefono'] ?? ''));
        if ($telefono === '') return;

        try {
            if (! Schema::hasTable('guia_fotos')) return;

            $pendiente = static::pendientePorTelefono($telefono) ?? new static();

            $pendiente->guia      = null;
            $pendiente->telefono  = $telefono;
            $pendiente->nombre    = trim((string) ($datos['nombre'] ?? '')) ?: $pendiente->nombre;
            $pendiente->contenido = trim((string) ($datos['contenido'] ?? '')) ?: $pendiente->contenido;
            if (isset($datos['cobrar']) && $datos['cobrar'] !== '') {
                $pendiente->cobrar = (float) $datos['cobrar'];
            }
            $pendiente->save();
        } catch (\Throwable $e) {
            // Nunca debe impedir que se agregue la guía al lote.
        }
    }

    /** Interruptor "Enviado" de la tabla: guarda/limpia la fecha de envío. */
    public function getEnviadoAttribute(): bool
    {
        return ! is_null($this->enviado_at);
    }

    public function setEnviadoAttribute($valor): void
    {
        $this->attributes['enviado_at'] = $valor ? now() : null;
    }

    /** Nombre legible del lote: "04/08 7:15 a. m." */
    public function loteBonito(): string
    {
        if (! $this->lote) return 'Sin lote';
        try {
            return \Illuminate\Support\Carbon::parse($this->lote)->format('d/m h:i A');
        } catch (\Throwable $e) {
            return $this->lote;
        }
    }

    /**
     * Enlace de seguimiento. Si conocemos el teléfono se usa ese, porque le
     * sirve al cliente para todos sus pedidos, no solo para este paquete.
     */
    public function enlaceRastreo(): string
    {
        $corto = static::telefonoCorto($this->telefono);
        if ($corto && strlen($corto) === 8) {
            return route('store.rastreo.guia') . '?tel=' . $corto;
        }
        return route('store.rastreo.guia') . '?guia=' . $this->guia;
    }

    /**
     * Mensaje listo para pegarle al cliente. La leyenda va escrita en el texto,
     * para que se entienda aunque WhatsApp no muestre la vista previa del enlace.
     */
    public function mensajeParaCliente(): string
    {
        $saludo = $this->nombre
            ? '¡Hola ' . \Illuminate\Support\Str::of($this->nombre)->trim()->before(' ')->title() . '! '
            : '';

        return $saludo . "\u{1F4E6} Aqu\u{ED} pod\u{E9}s ver el progreso de tu paquete:\n"
            . $this->enlaceRastreo() . "\n"
            . "Gu\u{ED}a: {$this->guia}\n\n"
            . "Ah\u{ED} mismo aparece la foto de tu pedido. \u{A1}Gracias por tu preferencia! \u{1F499}";
    }

    /** Enlace de WhatsApp con el mensaje listo (si se conoce el teléfono). */
    public function whatsapp(): ?string
    {
        $d = preg_replace('/\D/', '', (string) $this->telefono);
        if (strlen($d) === 11 && str_starts_with($d, '503')) $d = substr($d, 3);
        if (strlen($d) !== 8) return null;

        return 'https://wa.me/503' . $d . '?text=' . rawurlencode($this->mensajeParaCliente());
    }

    /** Nombre del cliente asociado a una guía (del PDF o leído de la etiqueta). */
    public static function nombreDeGuia(?string $guia): ?string
    {
        $g = trim((string) $guia);
        if ($g === '') return null;

        try {
            if (! Schema::hasTable('guia_fotos')) return null;
            return static::where('guia', $g)->whereNotNull('nombre')->value('nombre');
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function url(): ?string
    {
        return $this->ruta ? '/storage/' . ltrim($this->ruta, '/') : null;
    }

    public function tieneFoto(): bool
    {
        return filled($this->ruta);
    }

    /** Fotos de una guía (vacío si la tabla aún no existe). */
    public static function deGuia(?string $guia)
    {
        $guia = trim((string) $guia);
        if ($guia === '') return collect();

        try {
            if (! Schema::hasTable('guia_fotos')) return collect();
            // Solo las que ya tienen foto (las del PDF entran sin imagen).
            return static::where('guia', $guia)->whereNotNull('ruta')->orderBy('id')->get();
        } catch (\Throwable $e) {
            return collect();
        }
    }
}
