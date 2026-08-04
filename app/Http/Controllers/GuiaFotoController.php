<?php

namespace App\Http\Controllers;

use App\Models\GuiaFoto;
use Illuminate\Http\Request;

class GuiaFotoController extends Controller
{
    /** Guarda una foto de paquete y la asocia al número de guía leído del QR. */
    public function subir(Request $request)
    {
        $data = $request->validate([
            'guia'   => ['required', 'string', 'max:40'],
            'foto'   => ['required', 'image', 'max:8192'], // hasta 8 MB
            'nombre'   => ['nullable', 'string', 'max:80'],  // leído de la etiqueta
            'telefono' => ['nullable', 'string', 'max:30'],
        ]);

        $guia = preg_replace('/\D/', '', $data['guia']);
        if ($guia === '') {
            return response()->json(['ok' => false, 'error' => 'Guía no válida'], 422);
        }

        // Una sola foto por guía: si ya había, se reemplaza (evita repetidas).
        foreach (GuiaFoto::where('guia', $guia)->get() as $vieja) {
            try {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($vieja->ruta);
            } catch (\Throwable $e) {
            }
            $vieja->delete();
        }

        $ruta = $request->file('foto')->store('paquetes', 'public');

        $foto = GuiaFoto::create([
            'guia'     => $guia,
            'ruta'     => $ruta,
            'nombre'   => trim((string) ($data['nombre'] ?? '')) ?: null,
            'telefono' => trim((string) ($data['telefono'] ?? '')) ?: null,
        ]);

        // Limpieza: borra fotos con más de 5 días para no llenar el servidor.
        static::limpiarViejas();

        // Si esa guía ya está en un pedido, se devuelve el teléfono del cliente
        // para poder copiarlo y buscarlo rápido.
        $telefono = null;
        $nombre   = null;
        try {
            $pedido = \App\Models\Order::where('guia', $guia)->first();
            if ($pedido) {
                $d = preg_replace('/\D/', '', (string) $pedido->phone);
                if (strlen($d) === 11 && str_starts_with($d, '503')) $d = substr($d, 3);
                $telefono = strlen($d) === 8 ? substr($d, 0, 4) . ' ' . substr($d, 4) : ($d ?: null);
                $nombre   = $pedido->customer_name;
            }
        } catch (\Throwable $e) {
        }

        return response()->json([
            'ok'       => true,
            'guia'     => $guia,
            'url'      => $foto->url(),
            'rastreo'  => route('store.rastreo.guia') . '?guia=' . $guia,
            'telefono' => $telefono,
            'nombre'   => $nombre,
        ]);
    }

    /**
     * Borra las fotos con más de 5 días (archivo + registro). Se ejecuta sola
     * cada vez que se sube una foto, así no hace falta una tarea programada.
     */
    public static function limpiarViejas(int $dias = 5): int
    {
        $borradas = 0;
        try {
            $viejas = GuiaFoto::where('created_at', '<', now()->subDays($dias))->get();
            foreach ($viejas as $f) {
                try {
                    \Illuminate\Support\Facades\Storage::disk('public')->delete($f->ruta);
                } catch (\Throwable $e) {
                }
                $f->delete();
                $borradas++;
            }
        } catch (\Throwable $e) {
        }
        return $borradas;
    }

    /** Quita una foto (por si se subió una equivocada). */
    public function eliminar(GuiaFoto $foto)
    {
        try {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($foto->ruta);
        } catch (\Throwable $e) {
        }
        $foto->delete();

        return response()->json(['ok' => true]);
    }
}
