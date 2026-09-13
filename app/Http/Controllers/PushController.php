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
        try {
            $n = WaConversacion::hayTabla()
                ? WaConversacion::where('sin_leer', '>', 0)->where('archivada', false)->count()
                : 0;
        } catch (\Throwable $e) {
            $n = 0;
        }

        return response()->json(['sin_leer' => $n]);
    }
}
