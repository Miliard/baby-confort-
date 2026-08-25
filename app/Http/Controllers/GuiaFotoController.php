<?php

namespace App\Http\Controllers;

use App\Models\GuiaFoto;
use Illuminate\Http\Request;

class GuiaFotoController extends Controller
{
    /**
     * Sello de versión del módulo de fotos. Se muestra en pantalla para saber
     * si el navegador está usando el código nuevo o una copia vieja guardada
     * en caché, que es lo más difícil de detectar a ojo.
     */
    public const VERSION = '2026-08-25-a';


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

        $ruta = $request->file('foto')->store('paquetes', 'public');

        $nombreOcr   = trim((string) ($data['nombre'] ?? '')) ?: null;
        $telefonoOcr = trim((string) ($data['telefono'] ?? '')) ?: null;
        $loteFoto    = trim((string) ($data['lote'] ?? '')) ?: null;

        // Si la guía ya existe (por ejemplo, vino del PDF), la foto se PEGA a ese
        // registro. Así no se pierde el nombre ni el contenido del pedido.
        $foto = GuiaFoto::where('guia', $guia)->first();

        if ($foto) {
            // Reemplaza la imagen anterior, si tenía.
            if ($foto->ruta) {
                try { \Illuminate\Support\Facades\Storage::disk('public')->delete($foto->ruta); } catch (\Throwable $e) {}
            }
            $foto->ruta = $ruta;
            // Lo leído por OCR solo rellena lo que falte: nunca pisa los datos del PDF.
            $foto->nombre   = $foto->nombre   ?: $nombreOcr;
            $foto->telefono = $foto->telefono ?: $telefonoOcr;
            $foto->lote     = $foto->lote     ?: $loteFoto;
            $foto->save();
        } else {
            $foto = GuiaFoto::create([
                'guia'     => $guia,
                'ruta'     => $ruta,
                'nombre'   => $nombreOcr,
                'telefono' => $telefonoOcr,
                'lote'     => $loteFoto,
            ]);
        }

        // Limpieza: quita del disco las imágenes ya vencidas (una vez al día).
        static::limpiarSiTocaHoy();

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
     * Borra del disco las IMÁGENES con más días de los permitidos, pero deja el
     * registro intacto.
     *
     * Antes se borraba la fila entera, y eso se llevaba puesto el nombre, el
     * teléfono y el contenido del pedido: el cliente que rastreaba a los seis
     * días no encontraba nada, y el "producto más vendido" solo contaba la
     * última semana. La imagen es lo único que pesa; el texto no ocupa casi
     * nada y sirve para siempre.
     */
    public static function limpiarViejas(?int $dias = null): int
    {
        $dias = $dias ?? static::diasDeFotos();
        $borradas = 0;

        try {
            $tieneMarca = \Illuminate\Support\Facades\Schema::hasColumn('guia_fotos', 'foto_borrada_at');

            GuiaFoto::whereNotNull('ruta')->where('ruta', '!=', '')
                ->where('created_at', '<', now()->subDays($dias))
                ->chunkById(200, function ($viejas) use (&$borradas, $tieneMarca) {
                    foreach ($viejas as $f) {
                        try {
                            \Illuminate\Support\Facades\Storage::disk('public')->delete($f->ruta);
                        } catch (\Throwable $e) {
                        }
                        $f->ruta = null;
                        if ($tieneMarca) $f->foto_borrada_at = now();
                        $f->save();
                        $borradas++;
                    }
                });
        } catch (\Throwable $e) {
        }

        return $borradas;
    }

    /** Cuántos días se guarda la foto del paquete (se puede cambiar en Ajustes). */
    public static function diasDeFotos(): int
    {
        try {
            $d = (int) \App\Models\Setting::get('fotos_dias', 30);
        } catch (\Throwable $e) {
            $d = 30;
        }
        return $d > 0 ? $d : 30;
    }

    /**
     * Corre la limpieza como mucho una vez al día. Se llama al subir fotos, que
     * es lo que se hace a diario, así funciona aunque el servidor no tenga una
     * tarea programada corriendo.
     */
    public static function limpiarSiTocaHoy(): void
    {
        try {
            $hoy = now()->toDateString();
            if (\App\Models\Setting::get('fotos_limpieza_dia') === $hoy) return;

            \App\Models\Setting::put('fotos_limpieza_dia', $hoy);
            static::limpiarViejas();
        } catch (\Throwable $e) {
        }
    }

    /** Cuánto ocupan hoy las fotos guardadas (para verlo en pantalla). */
    public static function espacioUsado(): array
    {
        $bytes = 0;
        $conFoto = 0;

        try {
            $disco = \Illuminate\Support\Facades\Storage::disk('public');
            foreach ($disco->files('paquetes') as $archivo) {
                $bytes += (int) $disco->size($archivo);
                $conFoto++;
            }
        } catch (\Throwable $e) {
        }

        $mb = $bytes / 1048576;

        return [
            'archivos' => $conFoto,
            'bytes'    => $bytes,
            'legible'  => $mb >= 1024
                ? number_format($mb / 1024, 2) . ' GB'
                : number_format($mb, 1) . ' MB',
            'dias'     => static::diasDeFotos(),
        ];
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

        $nuevas = 0; $actualizadas = 0; $emparejadas = 0;
        $lote = trim((string) ($data['lote'] ?? '')) ?: now()->format('Y-m-d H:i:s');

        foreach ($data['guias'] as $g) {
            $guia = preg_replace('/\D/', '', $g['guia']);
            if ($guia === '') continue;

            $nombre    = trim((string) ($g['nombre'] ?? '')) ?: null;
            $telefono  = trim((string) ($g['telefono'] ?? '')) ?: null;
            $contenido = trim((string) ($g['contenido'] ?? '')) ?: null;
            $cobrar    = isset($g['cobrar']) && $g['cobrar'] !== '' ? (float) $g['cobrar'] : null;

            $registro = GuiaFoto::where('guia', $guia)->first();

            // Si no existe con ese número, puede ser el pedido que ya se armó
            // y quedó esperando su guía. Se le rellena en vez de duplicarlo.
            if (! $registro && $telefono) {
                $pendiente = GuiaFoto::pendientePorTelefono($telefono);
                if ($pendiente) {
                    $pendiente->guia = $guia;
                    $pendiente->save();
                    $registro = $pendiente;
                }
            }

            if ($registro) {
                // El PDF manda: sus datos son texto exacto de Sistrack, mientras que
                // los de la foto vienen de OCR y pueden estar mal leídos.
                if ($nombre)    $registro->nombre    = $nombre;
                if ($telefono)  $registro->telefono  = $telefono;
                if ($contenido) $registro->contenido = $contenido;
                if ($cobrar !== null) $registro->cobrar = $cobrar;
                $registro->lote = $registro->lote ?: $lote;
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

            // Empareja la guía con el pedido de la tienda usando el teléfono.
            // Así el cliente rastrea con su número y ve el envío real, sin que
            // le tengamos que mandar nada.
            if ($telefono) $emparejadas += $this->emparejarPedido($guia, $telefono);
        }

        return response()->json([
            'ok'           => true,
            'nuevas'       => $nuevas,
            'actualizadas' => $actualizadas,
            'emparejadas'  => $emparejadas,
        ]);
    }

    /**
     * Le pone la guía al pedido de la tienda que tenga ese teléfono y aún no
     * tenga guía. Se busca por los últimos 8 dígitos, para que dé igual cómo
     * estaba escrito el número (con guion, con +503, con espacios).
     */
    private function emparejarPedido(string $guia, string $telefono): int
    {
        $corto = \App\Models\GuiaFoto::telefonoCorto($telefono);
        if (! $corto || strlen($corto) !== 8) return 0;

        try {
            $limpio = "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(phone,''),' ',''),'-',''),'(',''),')',''),'+','')";

            $pedido = \App\Models\Order::whereRaw("$limpio LIKE ?", ['%' . $corto])
                ->where(function ($q) {
                    $q->whereNull('guia')->orWhere('guia', '');
                })
                ->orderByDesc('id')
                ->first();

            if (! $pedido) return 0;

            $pedido->guia = $guia;
            $pedido->save();
            return 1;
        } catch (\Throwable $e) {
            return 0;
        }
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
