<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;

/**
 * Un teléfono o navegador que pidió recibir avisos.
 */
class WaSuscripcion extends Model
{
    protected $table = 'wa_suscripciones';

    protected $fillable = ['user_id', 'endpoint', 'huella', 'p256dh', 'auth', 'ultimo_error_at'];

    protected $casts = ['ultimo_error_at' => 'datetime'];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public static function huellaDe(string $endpoint): string
    {
        return hash('sha256', $endpoint);
    }

    /** Guarda o actualiza el dispositivo, sin duplicarlo. */
    public static function registrar(int $userId, string $endpoint, ?string $p256dh, ?string $auth): self
    {
        return static::updateOrCreate(
            ['huella' => static::huellaDe($endpoint)],
            [
                'user_id'         => $userId,
                'endpoint'        => $endpoint,
                'p256dh'          => $p256dh,
                'auth'            => $auth,
                'ultimo_error_at' => null,
            ]
        );
    }

    /** Todos los dispositivos a los que hay que avisarles. */
    public static function activas()
    {
        try {
            if (! Schema::hasTable('wa_suscripciones')) return collect();

            return static::all();
        } catch (\Throwable $e) {
            return collect();
        }
    }
}
