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
            'guia'  => ['required', 'string', 'max:40'],
            'foto'  => ['required', 'image', 'max:8192'], // hasta 8 MB
        ]);

        $guia = preg_replace('/\D/', '', $data['guia']);
        if ($guia === '') {
            return response()->json(['ok' => false, 'error' => 'Guía no válida'], 422);
        }

        $ruta = $request->file('foto')->store('paquetes', 'public');

        $foto = GuiaFoto::create(['guia' => $guia, 'ruta' => $ruta]);

        return response()->json([
            'ok'      => true,
            'guia'    => $guia,
            'url'     => $foto->url(),
            'rastreo' => route('store.rastreo.guia') . '?guia=' . $guia,
        ]);
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
