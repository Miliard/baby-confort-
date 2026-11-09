<?php

namespace App\Http\Controllers;

use App\Models\WaConversacion;
use App\Models\WaSuscripcion;
use App\Services\WebPush;
use Illuminate\Http\Request;

/**
 * Lo que necesita el navegador para recibir avisos con la aplicación cerrada.
 */
class PushController extends Controller
{
    /** La clave pública con la que el navegador se registra. */
    public function clave()
    {
        return response()->json([
            'clave' => WebPush::clavePublica(),
        ]);
    }

    /** El navegador manda acá la dirección donde quiere recibir los avisos. */
    public function suscribir(Request $request)
    {
        $datos = $request->validate([
            'endpoint' => ['required', 'string', 'max:1000'],
            'p256dh'   => ['nullable', 'string', 'max:255'],
            'auth'     => ['nullable', 'string', 'max:255'],
        ]);

        try {
            WaSuscripcion::registrar(
                (int) auth()->id(),
                $datos['endpoint'],
                $datos['p256dh'] ?? null,
                $datos['auth'] ?? null
            );
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 500);
        }

        return response()->json(['ok' => true]);
    }

    /** Deja de recibir avisos en este dispositivo. */
    public function desuscribir(Request $request)
    {
        $endpoint = (string) $request->input('endpoint');

        if ($endpoint !== '') {
            WaSuscripcion::where('huella', WaSuscripcion::huellaDe($endpoint))->delete();
        }

        return response()->json(['ok' => true]);
    }

    /**
     * Cuántas conversaciones hay sin leer.
     *
     * Lo consulta el trabajador en segundo plano cuando llega un aviso, para
     * poder decir "tenés 3 sin leer" en vez de solo "llegó algo".
     */
    public function sinLeer()
    {
        $n = 0;
        $ultimo = null;

        try {
            if (WaConversacion::hayTabla()) {
                $n = WaConversacion::where('sin_leer', '>', 0)->where('archivada', false)->count();

                // Quién escribió de último: el aviso dice su nombre y lo que
                // escribió, y al tocarlo se abre ese chat.
                $c = WaConversacion::where('sin_leer', '>', 0)->where('archivada', false)
                    ->orderByDesc('ultimo_mensaje_at')->first();

                if ($c) {
                    $tel = $c->telefonoLegible();
                    // El nombre que le pusiste; si no, el de su perfil de WhatsApp.
                    $nom = $c->nombrePropio() ? trim((string) $c->alias) : trim((string) $c->nombre);
                    $texto = trim((string) $c->ultimo_texto);

                    $ultimo = [
                        'id'     => $c->id,
                        'nombre' => $nom !== '' && $nom !== $tel ? $nom . ' · ' . $tel : $tel,
                        'texto'  => $texto !== '' ? mb_substr($texto, 0, 140) : 'Mensaje nuevo',
                    ];
                }
            }
        } catch (\Throwable $e) {
        }

        return response()->json(['sin_leer' => $n, 'ultimo' => $ultimo]);
    }
}
