<?php

namespace App\Services;

use App\Models\WaConversacion;
use App\Models\WaMensaje;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Todo lo que habla con la API de WhatsApp de Meta.
 *
 * Las credenciales SIEMPRE salen de variables de entorno, nunca del código:
 *   WHATSAPP_TOKEN         · token de acceso permanente
 *   WHATSAPP_PHONE_ID      · id del número (no el número en sí)
 *   WHATSAPP_VERIFY_TOKEN  · el que se escribe al registrar el webhook
 *   WHATSAPP_APP_SECRET    · para comprobar que el webhook viene de Meta
 *
 * Si faltan, el panel sigue abriendo y mostrando el historial: solo no puede
 * mandar. Así se puede construir y probar antes de tener el trámite con Meta.
 */
class WhatsappApi
{
    private const VERSION = 'v21.0';

    public static function token(): ?string
    {
        return env('WHATSAPP_TOKEN') ?: null;
    }

    public static function phoneId(): ?string
    {
        return env('WHATSAPP_PHONE_ID') ?: null;
    }

    /** ¿Está configurado como para poder mandar? */
    public static function configurado(): bool
    {
        return filled(static::token()) && filled(static::phoneId());
    }

    private static function url(string $recurso = 'messages'): string
    {
        return 'https://graph.facebook.com/' . self::VERSION . '/' . static::phoneId() . '/' . $recurso;
    }

    /**
     * Manda un texto y lo guarda en el chat.
     *
     * Devuelve el mensaje guardado. Si falla, queda con estado "fallido" y el
     * motivo a la vista: nunca se pierde en silencio.
     */
    public static function enviarTexto(
        WaConversacion $conv,
        string $texto,
        ?int $userId = null,
        bool $automatico = false,
    ): WaMensaje {
        $mensaje = WaMensaje::create([
            'conversacion_id' => $conv->id,
            'direccion'  => 'saliente',
            'tipo'       => 'text',
            'texto'      => $texto,
            'estado'     => 'enviando',
            'user_id'    => $userId,
            'automatico' => $automatico,
        ]);

        if (! static::configurado()) {
            $mensaje->update([
                'estado' => 'fallido',
                'error'  => 'Falta configurar WHATSAPP_TOKEN y WHATSAPP_PHONE_ID.',
            ]);
            return $mensaje;
        }

        try {
            $r = Http::withToken(static::token())
                ->timeout(20)
                ->post(static::url(), [
                    'messaging_product' => 'whatsapp',
                    'to'                => $conv->wa_id,
                    'type'              => 'text',
                    'text'              => ['preview_url' => true, 'body' => $texto],
                ]);

            if ($r->successful()) {
                $mensaje->update([
                    'estado'        => 'enviado',
                    'wa_message_id' => $r->json('messages.0.id'),
                ]);
            } else {
                $mensaje->update([
                    'estado' => 'fallido',
                    'error'  => mb_substr((string) $r->json('error.message', $r->body()), 0, 300),
                ]);
            }
        } catch (\Throwable $e) {
            $mensaje->update([
                'estado' => 'fallido',
                'error'  => mb_substr($e->getMessage(), 0, 300),
            ]);
        }

        static::refrescarConversacion($conv, $texto);

        return $mensaje;
    }

    /** Deja la conversación con su último mensaje y la sube en la lista. */
    public static function refrescarConversacion(WaConversacion $conv, string $texto): void
    {
        $conv->ultimo_texto      = mb_substr(trim($texto), 0, 300);
        $conv->ultimo_mensaje_at = now();
        $conv->save();
    }

    /**
     * Baja una imagen que mandó el cliente y la guarda en el disco público.
     * Son dos pasos: primero Meta da la dirección, después se descarga con el
     * mismo token.
     */
    public static function bajarMedia(string $mediaId): ?string
    {
        if (! static::configurado()) return null;

        try {
            $info = Http::withToken(static::token())->timeout(20)
                ->get('https://graph.facebook.com/' . self::VERSION . '/' . $mediaId);

            $url = $info->json('url');
            if (! $url) return null;

            $archivo = Http::withToken(static::token())->timeout(40)->get($url);
            if (! $archivo->successful()) return null;

            $ext = match ($info->json('mime_type')) {
                'image/png'  => 'png',
                'image/webp' => 'webp',
                default      => 'jpg',
            };

            $ruta = 'whatsapp/' . $mediaId . '.' . $ext;
            Storage::disk('public')->put($ruta, $archivo->body());

            // El disco puede estar lleno y guardar devuelve false en silencio.
            if (! Storage::disk('public')->exists($ruta)
                || (int) Storage::disk('public')->size($ruta) === 0) {
                return null;
            }

            return $ruta;
        } catch (\Throwable $e) {
            Log::warning('WhatsApp: no se pudo bajar la imagen ' . $mediaId . ': ' . $e->getMessage());
            return null;
        }
    }

    /** Le dice a Meta que el mensaje ya se leyó (las dos palomitas azules). */
    public static function marcarLeido(string $waMessageId): void
    {
        if (! static::configurado()) return;

        try {
            Http::withToken(static::token())->timeout(10)->post(static::url(), [
                'messaging_product' => 'whatsapp',
                'status'            => 'read',
                'message_id'        => $waMessageId,
            ]);
        } catch (\Throwable $e) {
            // Que no se lea no es motivo para romper nada.
        }
    }
}
