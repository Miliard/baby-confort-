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
    /** Memoria de la petición, para no ir a la base en cada mensaje. */
    private static array $guardadas = [];

    /**
     * Las credenciales salen de dos lados, en este orden:
     *
     *  1. La base de datos, si el botón "Conectar mi WhatsApp" ya se usó. Así
     *     reconectar no obliga a redesplegar ni a tocar Railway.
     *  2. config/whatsapp.php, que lee las variables de Railway.
     *
     * Nunca con env() directo: al desplegar, Laravel guarda la configuración en
     * caché y a partir de ahí env() devuelve null fuera de los archivos de
     * config. Ese error ya nos costó una tarde.
     */
    private static function guardado(string $clave): ?string
    {
        if (array_key_exists($clave, static::$guardadas)) {
            return static::$guardadas[$clave];
        }

        try {
            $valor = \App\Models\Setting::get($clave);
        } catch (\Throwable $e) {
            // Sin base (migraciones a medias, por ejemplo) seguimos con config.
            $valor = null;
        }

        return static::$guardadas[$clave] = (filled($valor) ? (string) $valor : null);
    }

    /** Se llama después de conectar, para que el valor nuevo se use ya. */
    public static function olvidarGuardadas(): void
    {
        static::$guardadas = [];
    }

    public static function token(): ?string
    {
        return static::guardado('whatsapp_token') ?: (config('whatsapp.token') ?: null);
    }

    public static function phoneId(): ?string
    {
        return static::guardado('whatsapp_phone_id') ?: (config('whatsapp.phone_id') ?: null);
    }

    /** Identificador de la cuenta de WhatsApp Business (no del número). */
    public static function wabaId(): ?string
    {
        return static::guardado('whatsapp_waba_id');
    }

    /** El número tal como lo muestra Meta, solo para enseñarlo en pantalla. */
    public static function numeroLegible(): ?string
    {
        return static::guardado('whatsapp_numero');
    }

    private static function version(): string
    {
        return config('whatsapp.version', 'v21.0');
    }

    /** ¿Está configurado como para poder mandar? */
    public static function configurado(): bool
    {
        return filled(static::token()) && filled(static::phoneId());
    }

    private static function url(string $recurso = 'messages'): string
    {
        return 'https://graph.facebook.com/' . static::version() . '/' . static::phoneId() . '/' . $recurso;
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
        ?string $respondeA = null,
    ): WaMensaje {
        $mensaje = WaMensaje::create([
            'conversacion_id' => $conv->id,
            'direccion'  => 'saliente',
            'tipo'       => 'text',
            'texto'      => $texto,
            'responde_a' => $respondeA,
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
            $cuerpo = [
                'messaging_product' => 'whatsapp',
                'to'                => $conv->wa_id,
                'type'              => 'text',
                // La vista previa del enlace va apagada. Cuando estaba
                // encendida, WhatsApp recortaba la imagen ancha del sitio a un
                // cuadradito y salía un borrón ilegible, y de paso el globo
                // quedaba angosto y desalineado contra los demás. Apagada, el
                // enlace se sigue viendo y se sigue tocando: lo único que se
                // pierde es la tarjeta.
                'text' => [
                    'preview_url' => (bool) config('whatsapp.vista_previa', false),
                    'body'        => $texto,
                ],
            ];

            // Con esto, al cliente le llega citado el mensaje al que respondés,
            // igual que cuando uno responde desde el teléfono.
            if (filled($respondeA)) {
                $cuerpo['context'] = ['message_id' => $respondeA];
            }

            $r = Http::withToken(static::token())
                ->timeout(20)
                ->post(static::url(), $cuerpo);

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

        static::refrescarConversacion($conv, $texto, $mensaje->estado);

        return $mensaje;
    }

    /**
     * Manda una imagen por su dirección pública, con un texto al pie.
     *
     * Meta descarga la foto de nuestro sitio, así que la dirección tiene que
     * ser absoluta y alcanzable desde fuera. Por eso se arma con url().
     */
    public static function enviarImagen(
        WaConversacion $conv,
        string $urlPublica,
        ?string $pie = null,
        ?int $userId = null,
    ): WaMensaje {
        $mensaje = WaMensaje::create([
            'conversacion_id' => $conv->id,
            'direccion'  => 'saliente',
            'tipo'       => 'image',
            'texto'      => $pie,
            // Se guarda la dirección para que el globo la muestre en el panel.
            'media_ruta' => $urlPublica,
            'estado'     => 'enviando',
            'user_id'    => $userId,
        ]);

        if (! static::configurado()) {
            $mensaje->update([
                'estado' => 'fallido',
                'error'  => 'Falta configurar el enlace con Meta.',
            ]);
            return $mensaje;
        }

        try {
            $imagen = ['link' => $urlPublica];
            if (filled($pie)) $imagen['caption'] = $pie;

            $r = Http::withToken(static::token())
                ->timeout(30)
                ->post(static::url(), [
                    'messaging_product' => 'whatsapp',
                    'to'    => $conv->wa_id,
                    'type'  => 'image',
                    'image' => $imagen,
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

        static::refrescarConversacion($conv, $pie ?: '📷 Foto', $mensaje->estado);

        return $mensaje;
    }

    /**
     * Deja la conversación con su último mensaje y la sube en la lista.
     *
     * Anota además que el último fue nuestro y en qué estado quedó, para que
     * la lista pueda mostrar las palomitas sin consultar los mensajes.
     */
    public static function refrescarConversacion(
        WaConversacion $conv,
        string $texto,
        ?string $estado = null,
    ): void {
        $conv->anotarUltimo($texto, true, $estado);
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
                ->get('https://graph.facebook.com/' . static::version() . '/' . $mediaId);

            $url = $info->json('url');
            if (! $url) return null;

            $archivo = Http::withToken(static::token())->timeout(40)->get($url);
            if (! $archivo->successful()) return null;

            // El tipo viene como "audio/ogg; codecs=opus": nos quedamos con la
            // primera parte para elegir la extensión.
            $mime = trim(explode(';', (string) $info->json('mime_type'))[0]);

            $ext = match ($mime) {
                'image/png'   => 'png',
                'image/webp'  => 'webp',
                'image/jpeg'  => 'jpg',
                'audio/ogg'   => 'ogg',   // las notas de voz de WhatsApp
                'audio/mpeg'  => 'mp3',
                'audio/mp4'   => 'm4a',
                'audio/aac'   => 'aac',
                'audio/amr'   => 'amr',
                'audio/wav'   => 'wav',
                'video/mp4'   => 'mp4',
                'application/pdf' => 'pdf',
                default       => 'bin',
            };

            $carpeta = str_starts_with($mime, 'audio/') ? 'whatsapp/audios' : 'whatsapp';
            $ruta = $carpeta . '/' . $mediaId . '.' . $ext;
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
