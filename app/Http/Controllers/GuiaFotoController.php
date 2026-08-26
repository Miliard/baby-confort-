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
    public const VERSION = '2026-08-25-f';


    /**
     * Guarda el archivo Y COMPRUEBA que de verdad quedó escrito.
     *
     * Esto es lo que faltaba y costó días de búsqueda: cuando el disco está
     * lleno, guardar NO lanza ningún error (el disco 'public' tiene los avisos
     * apagados). Devuelve false, el archivo queda creado pero vacío, y ese
     * false se guardaba en la base como si fuera una ruta. Resultado: el
     * sistema respondía "foto guardada" y no había ninguna foto.
     *
     * Devuelve la ruta, o null con el motivo en $error.
     */
    private static function guardarArchivo($archivo, ?string &$error = null): ?string
    {
        $error = null;

        try {
            $ruta = $archivo->store('paquetes', 'public');
        } catch (\Throwable $e) {
            $error = 'No se pudo escribir en el disco: ' . $e->getMessage();
            return null;
        }

        if (! $ruta || ! is_string($ruta)) {
            $error = 'El disco del servidor no aceptó el archivo. Probablemente esté lleno.';
            return null;
        }

        try {
            $disco = \Illuminate\Support\Facades\Storage::disk('public');

            if (! $disco->exists($ruta)) {
                $error = 'El archivo no quedó guardado. El disco del servidor puede estar lleno.';
                return null;
            }

            if ((int) $disco->size($ruta) === 0) {
                // Se borra el cascarón vacío para no dejar basura.
                try { $disco->delete($ruta); } catch (\Throwable $e) {}
                $error = 'La foto quedó vacía: el disco del servidor está lleno. '
                       . 'Usá el botón "Liberar espacio" en Guías → Fotos.';
                return null;
            }
        } catch (\Throwable $e) {
            $error = 'No se pudo comprobar el archivo: ' . $e->getMessage();
            return null;
        }

        return $ruta;
    }

    /**
     * Página simple para subir una foto, sin JavaScript de por medio.
     *
     * La subida normal hace varias cosas antes de mandar la foto: lee el QR,
     * lee el texto de la etiqueta, achica la imagen. Cualquiera de esos pasos
     * puede trabarse y dejar todo detenido sin mostrar un error.
     *
     * Esta página no hace nada de eso: es un formulario de toda la vida, lo
     * manda el navegador. Si acá funciona, el problema está en aquellos pasos.
     * Si acá tampoco, el problema es del servidor. No hay medias tintas.
     */
    public function formularioSimple()
    {
        $ultimas = collect();
        try {
            $ultimas = GuiaFoto::whereNotNull('ruta')->where('ruta', '!=', '')
                ->orderByDesc('updated_at')->limit(5)->get();
        } catch (\Throwable $e) {
        }

        return view('fotos-simple', compact('ultimas'));
    }

    /** Recibe la foto del formulario simple y la guarda. */
    public function guardarSimple(Request $request)
    {
        $request->validate([
            'guia' => ['required', 'string', 'max:40'],
            // Sin la regla "image": esa rechaza formatos de teléfono como HEIC.
            // Se aceptan y se guardan igual; lo que importa es tener la foto.
            'foto' => ['required', 'file', 'max:20480'],
        ], [
            'foto.max'      => 'La foto pesa más de 20 MB. Sacale una más liviana.',
            'foto.required' => 'No llegó ninguna foto. Puede que sea demasiado pesada para el servidor.',
        ]);

        $guia = preg_replace('/\D/', '', (string) $request->input('guia'));
        if ($guia === '') {
            return back()->with('bc_mal', 'Ese número de guía no es válido.')->withInput();
        }

        $ruta = static::guardarArchivo($request->file('foto'), $error);
        if (! $ruta) {
            return back()->with('bc_mal', $error)->withInput();
        }

        $registro = GuiaFoto::where('guia', $guia)->first();
        $nueva    = ! $registro;

        if ($nueva) {
            $registro = new GuiaFoto();
            $registro->guia = $guia;
        }

        $anterior = $registro->ruta;
        $registro->ruta = $ruta;

        try {
            if (\Illuminate\Support\Facades\Schema::hasColumn('guia_fotos', 'foto_borrada_at')) {
                $registro->foto_borrada_at = null;
            }
        } catch (\Throwable $e) {
        }

        $registro->save();

        if ($anterior && $anterior !== $ruta) {
            try { \Illuminate\Support\Facades\Storage::disk('public')->delete($anterior); } catch (\Throwable $e) {}
        }

        return redirect()->route('fotos.simple')->with(
            'bc_ok',
            $nueva
                ? "Guía {$guia} creada con su foto."
                : "Foto guardada en la guía {$guia}. Ya se ve en el enlace del cliente."
        );
    }

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

        $ruta = static::guardarArchivo($request->file('foto'), $error);
        if (! $ruta) {
            // 507 = no queda espacio. Antes esto devolvía "ok" con la foto vacía.
            return response()->json(['ok' => false, 'error' => $error], 507);
        }

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

    /** Convierte bytes a algo legible. */
    private static function legible(float $bytes): string
    {
        $mb = $bytes / 1048576;
        return $mb >= 1024
            ? number_format($mb / 1024, 2) . ' GB'
            : number_format($mb, 1) . ' MB';
    }

    /**
     * Cuánto ocupan las fotos, cuánto espacio libre queda y cuántas fotos
     * quedaron VACÍAS.
     *
     * Lo de las vacías es la señal de alarma: cuando el disco se llena, el
     * archivo se crea pero no se escribe nada adentro. La subida responde que
     * todo salió bien y en realidad no hay foto. Así se veía la falla.
     */
    public static function espacioUsado(): array
    {
        $bytes = 0;
        $conFoto = 0;
        $vacios = 0;

        try {
            $disco = \Illuminate\Support\Facades\Storage::disk('public');
            foreach ($disco->files('paquetes') as $archivo) {
                $tam = (int) $disco->size($archivo);
                $bytes += $tam;
                $conFoto++;
                if ($tam === 0) $vacios++;
            }
        } catch (\Throwable $e) {
        }

        // Algunos servidores no dejan consultar el espacio libre; se prueban
        // varias rutas antes de darlo por perdido.
        $libre = 0;
        foreach ([storage_path('app/public'), storage_path(), base_path(), '/'] as $donde) {
            try {
                $n = @disk_free_space($donde);
                if ($n && $n > 0) { $libre = (float) $n; break; }
            } catch (\Throwable $e) {
            }
        }

        // Fecha de la foto más vieja: sirve para saber cuánto se libera si se
        // acorta el tiempo que se guardan.
        $masVieja = null;
        try {
            $masVieja = GuiaFoto::whereNotNull('ruta')->where('ruta', '!=', '')
                ->min('created_at');
        } catch (\Throwable $e) {
        }

        return [
            'archivos'     => $conFoto,
            'bytes'        => $bytes,
            'legible'      => static::legible($bytes),
            'vacios'       => $vacios,
            'libre'        => $libre,
            'libreLegible' => $libre > 0 ? static::legible($libre) : 'no se pudo leer',
            'apretado'     => $libre > 0 && $libre < 200 * 1048576,   // menos de 200 MB
            'dias'         => static::diasDeFotos(),
            'masVieja'     => $masVieja,
            'promedio'     => $conFoto > 0 ? static::legible($bytes / $conFoto) : '—',
        ];
    }

    /**
     * Lo que el propio PHP dice de sí mismo. Sin esto solo se puede adivinar:
     * el disco puede tener terabytes libres y aun así fallar la subida por el
     * límite de tamaño, por la carpeta temporal llena o por permisos.
     */
    public static function limitesDelServidor(): array
    {
        $temp = ini_get('upload_tmp_dir') ?: sys_get_temp_dir();

        $libreTemp = 0;
        try { $libreTemp = (float) (@disk_free_space($temp) ?: 0); } catch (\Throwable $e) {}

        $carpeta = storage_path('app/public/paquetes');

        // Prueba real: escribir un archivo con contenido y volver a leerlo.
        $prueba = 'no se pudo probar';
        try {
            $nombre = 'paquetes/.prueba-' . uniqid() . '.txt';
            $texto  = str_repeat('B', 1024);   // 1 KB
            $disco  = \Illuminate\Support\Facades\Storage::disk('public');

            $ok = $disco->put($nombre, $texto);
            if (! $ok) {
                $prueba = '❌ no dejó escribir';
            } else {
                $tam = (int) $disco->size($nombre);
                $prueba = $tam === 1024 ? '✅ escribe y lee bien' : "❌ escribió {$tam} bytes de 1024";
                $disco->delete($nombre);
            }
        } catch (\Throwable $e) {
            $prueba = '❌ ' . $e->getMessage();
        }

        return [
            'subida_max'   => ini_get('upload_max_filesize'),
            'post_max'     => ini_get('post_max_size'),
            'memoria'      => ini_get('memory_limit'),
            'archivos_max' => ini_get('max_file_uploads'),
            'tiempo_max'   => ini_get('max_execution_time'),
            'temp'         => $temp,
            'temp_escribe' => is_writable($temp) ? 'sí' : 'NO',
            'temp_libre'   => $libreTemp > 0 ? static::legible($libreTemp) : 'no se pudo leer',
            'carpeta'      => $carpeta,
            'carpeta_escribe' => is_writable($carpeta) ? 'sí' : 'NO',
            'prueba'       => $prueba,
        ];
    }

    /**
     * Cambia cuántos días se guardan las fotos y aplica el cambio en el acto.
     *
     * Es la palanca para cuando el disco se llena: bajar de 30 a 7 días libera
     * de golpe todo lo viejo, sin tocar lo reciente ni perder ningún dato del
     * pedido (guía, cliente, teléfono y contenido se quedan siempre).
     */
    public function cambiarDias(Request $request)
    {
        $dias = (int) $request->input('dias', 30);
        $dias = max(1, min(365, $dias));

        try {
            \App\Models\Setting::put('fotos_dias', $dias);
        } catch (\Throwable $e) {
            return back()->with('bc_mal', 'No se pudo guardar el ajuste: ' . $e->getMessage());
        }

        $antes    = static::espacioUsado();
        $borradas = static::limpiarViejas($dias);
        $ahora    = static::espacioUsado();

        $liberado = static::legible(max(0, $antes['bytes'] - $ahora['bytes']));

        return back()->with('bc_ok', sprintf(
            'Las fotos ahora se guardan %d días. Se borraron %d imágenes viejas y se liberaron %s. Quedan %d fotos (%s).',
            $dias, $borradas, $liberado, $ahora['archivos'], $ahora['legible']
        ));
    }

    /**
     * Libera espacio en el disco, que es lo que deja de funcionar cuando se
     * llena. Hace tres cosas, de menos a más drástica:
     *
     *   1. Borra las fotos que quedaron VACÍAS (no sirven para nada).
     *   2. Vacía el archivo de registro de errores, que crece sin límite.
     *   3. Corre la limpieza normal de fotos vencidas.
     */
    public static function liberarEspacio(): array
    {
        $r = ['vacias' => 0, 'registro' => '0 MB', 'vencidas' => 0];

        // 1 · Fotos vacías: el archivo existe pero no tiene nada adentro.
        try {
            $disco = \Illuminate\Support\Facades\Storage::disk('public');
            $muertas = [];

            foreach ($disco->files('paquetes') as $archivo) {
                if ((int) $disco->size($archivo) === 0) $muertas[] = $archivo;
            }

            foreach ($muertas as $archivo) {
                try { $disco->delete($archivo); } catch (\Throwable $e) {}
                $r['vacias']++;
            }

            // Y se limpian los registros que apuntaban a esas fotos fantasma.
            if ($muertas) {
                GuiaFoto::whereIn('ruta', $muertas)->update(['ruta' => null]);
            }

            // Cuando el disco se llena, la ruta se guarda como "0" (el archivo
            // nunca se escribió). Esos registros también hay que despegarlos.
            GuiaFoto::where('ruta', '0')->update(['ruta' => null]);
        } catch (\Throwable $e) {
        }

        // 2 · El registro de errores puede llegar a pesar más que las fotos.
        try {
            $log = storage_path('logs/laravel.log');
            if (is_file($log)) {
                $r['registro'] = static::legible((float) (filesize($log) ?: 0));
                file_put_contents($log, '');
            }
        } catch (\Throwable $e) {
        }

        // 3 · Limpieza normal de fotos vencidas.
        $r['vencidas'] = static::limpiarViejas();

        return $r;
    }

    /** Botón "Liberar espacio" del panel. */
    public function liberar()
    {
        $antes = static::espacioUsado();
        $hecho = static::liberarEspacio();
        $ahora = static::espacioUsado();

        return back()->with('bc_ok', sprintf(
            'Espacio liberado · %d fotos vacías borradas · registro de errores: %s · %d fotos vencidas. Libre ahora: %s (antes %s).',
            $hecho['vacias'], $hecho['registro'], $hecho['vencidas'],
            $ahora['libreLegible'], $antes['libreLegible']
        ));
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
