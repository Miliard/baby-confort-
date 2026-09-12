<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Services\WhatsappApi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * El enchufe entre el número de WhatsApp y este panel.
 *
 * Se usa una sola vez. El botón de la pantalla abre el diálogo de Meta, el
 * dueño del número escanea un código QR con su teléfono, y Meta devuelve tres
 * cosas: un código de un solo uso, el identificador de la cuenta y el del
 * número. Con eso acá adentro se hace el resto:
 *
 *   1. Cambiar el código por un identificador de acceso duradero
 *   2. Suscribir la aplicación a la cuenta (sin esto no llegan los mensajes)
 *   3. Guardar todo en la base, para no depender de redesplegar
 *
 * Importante: el camino del código QR es el de Coexistencia. El número sigue
 * funcionando en el teléfono. El otro camino, el de verificación por SMS, se lo
 * lleva del teléfono y no es el que usamos acá.
 */
class WhatsappConexionController extends Controller
{
    public function guardar(Request $request)
    {
        $datos = $request->validate([
            'code'            => ['required', 'string'],
            'waba_id'         => ['required', 'string'],
            // Cuando se conecta por código QR, Meta devuelve solo la cuenta.
            // El número lo buscamos nosotros más abajo.
            'phone_number_id' => ['nullable', 'string'],
        ]);

        // ── 1. El código vale una sola vez y dura minutos: se canjea ya ───────
        $token = $this->canjearCodigo($datos['code']);

        if (! $token) {
            return response()->json([
                'ok'    => false,
                'error' => 'Meta no aceptó el código. Suele pasar si el diálogo '
                         . 'quedó abierto mucho rato. Cerralo y probá de nuevo.',
            ], 422);
        }

        // ── 2. El número ─────────────────────────────────────────────────────
        $phoneId = filled($datos['phone_number_id'] ?? null)
            ? $datos['phone_number_id']
            : $this->primerNumeroDe($datos['waba_id'], $token);

        if (! $phoneId) {
            return response()->json([
                'ok'    => false,
                'error' => 'Meta aceptó la conexión pero esa cuenta no tiene ningún '
                         . 'número asociado. Revisá que hayas elegido la cuenta correcta.',
            ], 422);
        }

        // ── 3. Suscribir la app a la cuenta ──────────────────────────────────
        // Este es el paso que hace que Meta nos mande los mensajes. Si falla, el
        // panel podría enviar pero nunca recibiría, así que se avisa.
        $suscrita = $this->suscribirApp($datos['waba_id'], $token);

        // ── 4. Guardar ───────────────────────────────────────────────────────
        Setting::put('whatsapp_token', $token);
        Setting::put('whatsapp_phone_id', $phoneId);
        Setting::put('whatsapp_waba_id', $datos['waba_id']);
        Setting::put('whatsapp_conectado_at', now()->toDateTimeString());

        // El número en sí es solo para mostrarlo en pantalla y que se vea que
        // conectó el correcto. Si no se puede leer, no es motivo de error.
        Setting::put('whatsapp_numero', $this->leerNumero($phoneId, $token) ?? '');

        WhatsappApi::olvidarGuardadas();

        return response()->json([
            'ok'       => true,
            'numero'   => WhatsappApi::numeroLegible(),
            'suscrita' => $suscrita,
            'aviso'    => $suscrita ? null
                : 'Quedó conectado, pero no se pudo suscribir la aplicación a la '
                . 'cuenta. Vas a poder enviar, pero quizá no recibir. Avisame '
                . 'para revisarlo.',
        ]);
    }

    /** Cambia el código de un solo uso por un identificador de acceso. */
    private function canjearCodigo(string $code): ?string
    {
        try {
            $r = Http::timeout(20)->get(
                'https://graph.facebook.com/' . config('whatsapp.version', 'v21.0') . '/oauth/access_token',
                [
                    'client_id'     => config('whatsapp.app_id'),
                    'client_secret' => config('whatsapp.app_secret'),
                    'code'          => $code,
                ]
            );

            if (! $r->successful()) {
                Log::error('WhatsApp conexión: Meta rechazó el código. ' . $r->body());
                return null;
            }

            return $r->json('access_token') ?: null;
        } catch (\Throwable $e) {
            Log::error('WhatsApp conexión: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Busca el número de la cuenta.
     *
     * Hace falta porque el camino del código QR devuelve la cuenta pero no el
     * número. Como acá siempre va a haber uno solo, se toma el primero.
     */
    private function primerNumeroDe(string $wabaId, string $token): ?string
    {
        try {
            $r = Http::withToken($token)->timeout(20)->get(
                'https://graph.facebook.com/' . config('whatsapp.version', 'v21.0')
                . '/' . $wabaId . '/phone_numbers',
                ['fields' => 'id,display_phone_number']
            );

            if (! $r->successful()) {
                Log::error('WhatsApp conexión: no se pudieron leer los números. ' . $r->body());
                return null;
            }

            return $r->json('data.0.id') ?: null;
        } catch (\Throwable $e) {
            Log::error('WhatsApp conexión (números): ' . $e->getMessage());
            return null;
        }
    }

    /** Le dice a Meta que esta aplicación atiende esa cuenta. */
    private function suscribirApp(string $wabaId, string $token): bool
    {
        try {
            $r = Http::withToken($token)->timeout(20)->post(
                'https://graph.facebook.com/' . config('whatsapp.version', 'v21.0')
                . '/' . $wabaId . '/subscribed_apps'
            );

            if (! $r->successful()) {
                Log::error('WhatsApp conexión: no se pudo suscribir. ' . $r->body());
                return false;
            }

            return (bool) $r->json('success', true);
        } catch (\Throwable $e) {
            Log::error('WhatsApp conexión (suscripción): ' . $e->getMessage());
            return false;
        }
    }

    /** Lee el número de teléfono, solo para mostrarlo. */
    private function leerNumero(string $phoneId, string $token): ?string
    {
        try {
            $r = Http::withToken($token)->timeout(15)->get(
                'https://graph.facebook.com/' . config('whatsapp.version', 'v21.0') . '/' . $phoneId,
                ['fields' => 'display_phone_number,verified_name']
            );

            return $r->successful() ? $r->json('display_phone_number') : null;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
