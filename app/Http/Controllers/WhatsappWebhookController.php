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

                    // Lo que Wil escribe desde el teléfono, en la app de
                    // WhatsApp Business. Con Coexistencia el número vive en los
                    // dos lados, así que sin esto el panel mostraría solo la
                    // mitad de la conversación.
                    foreach ($valor['message_echoes'] ?? [] as $e) {
                        $this->guardarDelTelefono($e);
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
        } elseif (in_array($tipo, ['image', 'video', 'document', 'audio', 'voice'], true)) {
            $mediaId = $m[$tipo]['id'] ?? null;
            $texto   = $m[$tipo]['caption'] ?? null;
        } else {
            $texto = '[' . $tipo . ']';
        }

        /*
         * PRIMERO SE GUARDA EL MENSAJE. DESPUÉS SE BAJA LA FOTO.
         *
         * Acá estaba el mensaje perdido. Antes se bajaba la imagen —y se
         * transcribía el audio— ANTES de guardar nada. Las dos cosas son
         * lentas: bajar una foto de Meta son dos viajes, y transcribir un
         * audio puede tardar varios segundos.
         *
         * Y Meta no espera. Su webhook tiene un límite de paciencia corto: si
         * no le contestamos rápido, corta la conexión y da el aviso por
         * fallido. Con la conexión cortada a media descarga, el mensaje NUNCA
         * llegaba a guardarse — y como el aviso ya se dio por entregado en
         * algún reintento, tampoco volvía.
         *
         * Por eso pasaba solo con las fotos y los audios: un mensaje de texto
         * se guarda en milisegundos y nunca alcanzó a chocar con el límite.
         *
         * Guardando primero, aunque la descarga falle o tarde, el mensaje ya
         * está en el chat. La foto se puede traer después con el botón "Ver la
         * imagen", que ya existe y funciona con el media_id.
         */
        $mensaje = WaMensaje::create([
            'conversacion_id' => $conv->id,
            'wa_message_id'   => $idMeta,
            // Si el cliente citó un mensaje nuestro, Meta lo dice acá. Guardarlo
            // hace que la conversación se lea igual que en el teléfono.
            'responde_a'      => $m['context']['id'] ?? null,
            'direccion'  => 'entrante',
            'tipo'       => $tipo,
            'texto'      => $texto,
            'media_id'   => $mediaId,
            'media_ruta' => null,
            'estado'     => 'entregado',
        ]);

        // Ahora sí, con el mensaje a salvo. Si algo de esto falla o se corta,
        // lo único que se pierde es la comodidad, no el mensaje.
        try {
            if ($mediaId && in_array($tipo, ['image', 'audio', 'voice'], true)) {
                $ruta = WhatsappApi::bajarMedia($mediaId);

                if ($ruta) {
                    $mensaje->media_ruta = $ruta;

                    // Nota de voz: se pasa a texto. Si no se puede (sin clave,
                    // audio cortado, lo que sea), queda el reproductor igual.
                    if (in_array($tipo, ['audio', 'voice'], true)) {
                        $dicho = \App\Services\Transcribir::deArchivo($ruta);
                        if ($dicho) {
                            $mensaje->texto = $dicho;
                            $texto = $dicho;
                        }
                    }

                    $mensaje->save();
                }
            }
        } catch (\Throwable $e) {
            Log::warning('WhatsApp: media del mensaje ' . $mensaje->id . ': ' . $e->getMessage());
        }

        // Se reabre la ventana de 24 horas y sube en la lista del inbox.
        $conv->ultimo_del_cliente_at = now();
        $conv->anotarUltimo((string) ($texto ?: '[' . $tipo . ']'), false);
        $conv->sin_leer  = $conv->sin_leer + 1;
        $conv->archivada = false;
        $conv->save();

        // Respuesta automática (por ahora, solo la tabla de tallas).
        if ($tipo === 'text' && filled($texto)) {
            AutoRespuestas::quizasResponder($conv, $texto);
        }

        // Y el aviso a los teléfonos del equipo, aunque tengan el panel
        // cerrado. Si falla, no importa: el mensaje ya quedó guardado.
        try {
            \App\Services\WebPush::avisarATodos();
        } catch (\Throwable $e) {
            Log::warning('Push desde el webhook: ' . $e->getMessage());
        }
    }

    /**
     * Un mensaje que salió del teléfono, no del panel.
     *
     * Llega por el aviso "smb_message_echoes", que solo existe cuando el número
     * está en Coexistencia. Se guarda como saliente y sin agente: así el panel
     * lo muestra del lado derecho y firmado "Desde el teléfono".
     *
     * Importante: NO toca la ventana de 24 horas. Esa la abre el cliente
     * cuando escribe, no nosotros cuando contestamos.
     */
    private function guardarDelTelefono(array $e): void
    {
        $para = $e['to'] ?? null;
        if (! $para) return;

        // Meta reenvía avisos: si ya está guardado, no se duplica.
        $idMeta = $e['id'] ?? null;
        if ($idMeta && WaMensaje::where('wa_message_id', $idMeta)->exists()) return;

        $conv = WaConversacion::deNumero($para);

        $tipo  = $e['type'] ?? 'text';
        $texto = null;
        $mediaId = null;
        $ruta = null;

        if ($tipo === 'text') {
            $texto = $e['text']['body'] ?? '';
        } elseif (in_array($tipo, ['image', 'video', 'document', 'audio'], true)) {
            $mediaId = $e[$tipo]['id'] ?? null;
            $texto   = $e[$tipo]['caption'] ?? null;
        } else {
            $texto = '[' . $tipo . ']';
        }

        // Igual que con los entrantes: primero se guarda, después se baja.
        // Meta corta el webhook si tardamos, y con la conexión cortada a media
        // descarga el mensaje se perdía entero.
        $mensaje = WaMensaje::create([
            'conversacion_id' => $conv->id,
            'wa_message_id'   => $idMeta,
            'direccion'  => 'saliente',
            'tipo'       => $tipo,
            'texto'      => $texto,
            'media_id'   => $mediaId,
            'media_ruta' => null,
            'estado'     => 'entregado',
            'user_id'    => null,      // salió del teléfono, no de una cuenta
            'automatico' => false,
        ]);

        try {
            if ($tipo === 'image' && $mediaId) {
                $ruta = WhatsappApi::bajarMedia($mediaId);

                if ($ruta) {
                    $mensaje->media_ruta = $ruta;
                    $mensaje->save();
                }
            }
        } catch (\Throwable $ex) {
            Log::warning('WhatsApp: media saliente ' . $mensaje->id . ': ' . $ex->getMessage());
        }

        // Salió del teléfono: cuenta como respondido.
        $conv->anotarUltimo((string) ($texto ?: '[' . $tipo . ']'), true, 'entregado');
        $conv->archivada = false;
        $conv->save();
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

        // Si era el último de la conversación, la lista también tiene que
        // enterarse: es donde se ven las palomitas de un vistazo.
        try {
            $conv = $mensaje->conversacion;

            if ($conv) {
                $ultimo = WaMensaje::where('conversacion_id', $conv->id)
                    ->orderByDesc('id')->first();

                if ($ultimo && $ultimo->id === $mensaje->id) {
                    $conv->ultimo_estado = $estado;
                    $conv->save();
                }
            }
        } catch (\Throwable $e) {
            // Que no se actualice la palomita no justifica romper el webhook.
        }
    }
}
