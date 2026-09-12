<?php

namespace App\Http\Controllers;

use App\Models\WaConversacion;
use App\Models\WaMensaje;
use App\Services\AutoRespuestas;
use App\Services\WhatsappApi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * La puerta por donde entran los mensajes de WhatsApp.
 *
 * Meta llama acá dos veces distintas:
 *  · GET  → una sola vez, al registrar el webhook, para comprobar que es tuyo
 *  · POST → cada vez que pasa algo: mensaje nuevo, entregado, leído
 *
 * Regla de oro: SIEMPRE responder 200 y rápido. Si Meta recibe un error o
 * tardamos, reintenta una y otra vez y termina desactivando el webhook.
 */
class WhatsappWebhookController extends Controller
{
    /** Verificación inicial: Meta manda un reto y hay que devolverlo tal cual. */
    public function verificar(Request $request)
    {
        // config() y no env(): al desplegar, la configuración queda en caché y
        // env() deja de devolver nada fuera de los archivos de config.
        $esperado = config('whatsapp.verify_token');

        if ($request->query('hub_mode') === 'subscribe'
            && filled($esperado)
            && hash_equals((string) $esperado, (string) $request->query('hub_verify_token'))) {
            return response($request->query('hub_challenge'), 200)
                ->header('Content-Type', 'text/plain');
        }

        return response('Token de verificación incorrecto', 403);
    }

    /** Todo lo que pasa después entra por acá. */
    public function recibir(Request $request)
    {
        if (! $this->firmaValida($request)) {
            Log::warning('WhatsApp: llegó un webhook con firma inválida.');
            return response()->json(['ok' => true]);   // 200 igual: no dar pistas
        }

        try {
            foreach ($request->input('entry', []) as $entrada) {
                foreach ($entrada['changes'] ?? [] as $cambio) {
                    $valor = $cambio['value'] ?? [];

                    foreach ($valor['messages'] ?? [] as $m) {
                        $this->guardarEntrante($m, $valor);
                    }

                    foreach ($valor['statuses'] ?? [] as $s) {
                        $this->actualizarEstado($s);
                    }
                }
            }
        } catch (\Throwable $e) {
            // Se anota y se sigue: un mensaje raro no puede tumbar el webhook.
            Log::error('WhatsApp webhook: ' . $e->getMessage());
        }

        return response()->json(['ok' => true]);
    }

    /**
     * Comprueba que el aviso viene de Meta y no de cualquiera.
     *
     * Si no hay App Secret configurado se deja pasar, para poder probar antes de
     * terminar el trámite. En cuanto se configure, empieza a exigirse.
     */
    private function firmaValida(Request $request): bool
    {
        $secreto = config('whatsapp.app_secret');
        if (blank($secreto)) return true;

        $firma = (string) $request->header('X-Hub-Signature-256');
        if (! str_starts_with($firma, 'sha256=')) return false;

        $calculada = 'sha256=' . hash_hmac('sha256', $request->getContent(), $secreto);

        return hash_equals($calculada, $firma);
    }

    /** Guarda un mensaje que mandó el cliente. */
    private function guardarEntrante(array $m, array $valor): void
    {
        $waId = $m['from'] ?? null;
        if (! $waId) return;

        // Meta puede repetir el mismo aviso: si ya está, no se duplica.
        $idMeta = $m['id'] ?? null;
        if ($idMeta && WaMensaje::where('wa_message_id', $idMeta)->exists()) return;

        $nombre = $valor['contacts'][0]['profile']['name'] ?? null;
        $conv   = WaConversacion::deNumero($waId, $nombre);

        $tipo  = $m['type'] ?? 'text';
        $texto = null;
        $mediaId = null;
        $ruta = null;

        if ($tipo === 'text') {
            $texto = $m['text']['body'] ?? '';
        } elseif (in_array($tipo, ['image', 'video', 'document', 'audio'], true)) {
            $mediaId = $m[$tipo]['id'] ?? null;
            $texto   = $m[$tipo]['caption'] ?? null;

            // Solo se bajan las imágenes: son los comprobantes de pago.
            if ($tipo === 'image' && $mediaId) {
                $ruta = WhatsappApi::bajarMedia($mediaId);
            }
        } else {
            $texto = '[' . $tipo . ']';
        }

        WaMensaje::create([
            'conversacion_id' => $conv->id,
            'wa_message_id'   => $idMeta,
            'direccion'  => 'entrante',
            'tipo'       => $tipo,
            'texto'      => $texto,
            'media_id'   => $mediaId,
            'media_ruta' => $ruta,
            'estado'     => 'entregado',
        ]);

        // Se reabre la ventana de 24 horas y sube en la lista del inbox.
        $conv->ultimo_del_cliente_at = now();
        $conv->ultimo_mensaje_at     = now();
        $conv->ultimo_texto = mb_substr(trim((string) ($texto ?: '[' . $tipo . ']')), 0, 300);
        $conv->sin_leer     = $conv->sin_leer + 1;
        $conv->archivada    = false;
        $conv->save();

        // Respuesta automática (por ahora, solo la tabla de tallas).
        if ($tipo === 'text' && filled($texto)) {
            AutoRespuestas::quizasResponder($conv, $texto);
        }
    }

    /** Entregado / leído / falló: se refleja en el globo del mensaje. */
    private function actualizarEstado(array $s): void
    {
        $id = $s['id'] ?? null;
        if (! $id) return;

        $mensaje = WaMensaje::where('wa_message_id', $id)->first();
        if (! $mensaje) return;

        $estado = match ($s['status'] ?? '') {
            'sent'      => 'enviado',
            'delivered' => 'entregado',
            'read'      => 'leido',
            'failed'    => 'fallido',
            default     => null,
        };

        if (! $estado) return;

        $datos = ['estado' => $estado];

        if ($estado === 'fallido') {
            $datos['error'] = mb_substr((string) ($s['errors'][0]['title'] ?? 'Meta rechazó el mensaje'), 0, 300);
        }

        $mensaje->update($datos);
    }
}
