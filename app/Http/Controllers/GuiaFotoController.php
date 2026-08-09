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
            'lote'     => ['nullable', 'string', 'max:40'],  // agrupa las subidas juntas
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
            'lote'     => trim((string) ($data['lote'] ?? '')) ?: null,
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

    /**
     * Registra las guías leídas del PDF de etiquetas de Sistrack.
     * Cada una trae su guía, cliente y teléfono exactos (sin OCR), así la foto
     * que se suba después se pega sola a la guía correcta.
     *
     * No toca la libreta de clientes: para llegar al PDF, el cliente ya se guardó
     * al armar la guía.
     */
    public function importarPdf(Request $request)
    {
        $data = $request->validate([
            'guias'              => ['required', 'array', 'min:1'],
            'guias.*.guia'       => ['required', 'string', 'max:40'],
            'guias.*.nombre'     => ['nullable', 'string', 'max:120'],
            'guias.*.telefono'   => ['nullable', 'string', 'max:30'],
            'guias.*.contenido'  => ['nullable', 'string', 'max:400'],
            'guias.*.cobrar'     => ['nullable', 'numeric'],
            'guias.*.direccion'  => ['nullable', 'string', 'max:400'],
            'guias.*.municipio'  => ['nullable', 'string', 'max:80'],
            'guias.*.departamento' => ['nullable', 'string', 'max:80'],
            'lote'               => ['nullable', 'string', 'max:40'],
        ]);

        $nuevas = 0; $actualizadas = 0;
        $lote = trim((string) ($data['lote'] ?? '')) ?: now()->format('Y-m-d H:i:s');

        foreach ($data['guias'] as $g) {
            $guia = preg_replace('/\D/', '', $g['guia']);
            if ($guia === '') continue;

            $nombre    = trim((string) ($g['nombre'] ?? '')) ?: null;
            $telefono  = trim((string) ($g['telefono'] ?? '')) ?: null;
            $contenido = trim((string) ($g['contenido'] ?? '')) ?: null;
            $cobrar    = isset($g['cobrar']) && $g['cobrar'] !== '' ? (float) $g['cobrar'] : null;

            $registro = GuiaFoto::where('guia', $guia)->first();

            if ($registro) {
                $registro->nombre    = $registro->nombre    ?: $nombre;
                $registro->telefono  = $registro->telefono  ?: $telefono;
                $registro->contenido = $registro->contenido ?: $contenido;
                $registro->cobrar    = $registro->cobrar    ?: $cobrar;
                $registro->lote      = $registro->lote      ?: $lote;
                $registro->save();
                $actualizadas++;
            } else {
                GuiaFoto::create([
                    'guia'      => $guia,
                    'ruta'      => null,        // la foto llega después
                    'nombre'    => $nombre,
                    'telefono'  => $telefono,
                    'contenido' => $contenido,
                    'cobrar'    => $cobrar,
                    'lote'      => $lote,
                ]);
                $nuevas++;
            }

            // No se toca la libreta: el cliente ya se guardó al armar la guía.
        }

        return response()->json([
            'ok'           => true,
            'nuevas'       => $nuevas,
            'actualizadas' => $actualizadas,
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
