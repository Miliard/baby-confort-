<?php

namespace App\Http\Controllers;

use App\Models\GuiaEstado;
use App\Models\GuiaFoto;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Puente con el sistema nuevo de Express (Facilito / T&T).
 *
 * Express no tiene rastreo público ni API activa todavía. El estado de las
 * guías solo se ve con la sesión de Wil abierta. Así que el botón
 * "📦 Actualizar guías" (un marcador del navegador) corre DENTRO de la página de
 * Express:
 *
 *   1. le pide a este servidor qué guías están pendientes  → pendientes()
 *   2. le pregunta a Express el estado de esas guías, con la sesión de Wil
 *   3. le manda a este servidor lo que respondió Express    → estados()
 *
 * La sesión de Express nunca sale del navegador. Este servidor solo recibe
 * número de guía, estado, fechas y nombre del repartidor.
 *
 * Las dos rutas van por POST con texto plano para que el navegador no pida
 * permiso previo (CORS) y se protegen con una clave propia del botón.
 */
class ExpressPuenteController extends Controller
{
    /** Desde dónde se puede llamar. */
    private const ORIGEN = 'https://xpress.facilitoerp.com';

    /** Días hacia atrás que se siguen las guías. */
    private const DIAS = 30;

    public static function clave(): string
    {
        $k = (string) Setting::get('express_puente_clave', '');
        if (strlen($k) < 24) {
            $k = Str::random(40);
            Setting::put('express_puente_clave', $k);
        }

        return $k;
    }

    public function pendientes(Request $request)
    {
        $datos = $this->leer($request);
        if ($datos === null) return $this->responder(['ok' => false, 'error' => 'clave'], 403);

        $guias = collect();

        try {
            $guias = GuiaFoto::whereNotNull('guia')->where('guia', '!=', '')
                ->where('created_at', '>=', now()->subDays(self::DIAS))
                ->orderByDesc('id')
                ->pluck('guia')
                ->map(fn ($g) => preg_replace('/\D/', '', (string) $g))
                ->filter()
                ->unique()
                ->values();
        } catch (\Throwable $e) {
            // sin guías
        }

        // Fuera las ya entregadas y las que Express no reconoce (viejas de Sistrack).
        if ($guias->isNotEmpty() && GuiaEstado::hayTabla()) {
            $cerradas = GuiaEstado::whereIn('guia', $guias->all())
                ->where(fn ($q) => $q->where('etapa', '>=', 4)->orWhere('faltas', '>=', 2))
                ->pluck('guia')->all();
            $guias = $guias->diff($cerradas)->values();
        }

        return $this->responder(['ok' => true, 'guias' => $guias->take(500)->all()]);
    }

    public function estados(Request $request)
    {
        $datos = $this->leer($request);
        if ($datos === null) return $this->responder(['ok' => false, 'error' => 'clave'], 403);

        if (! GuiaEstado::hayTabla()) {
            return $this->responder(['ok' => false, 'error' => 'falta la tabla guia_estados'], 500);
        }

        $cuenta = ['actualizadas' => 0, 'entregadas' => 0, 'en_ruta' => 0, 'bodega' => 0, 'otras' => 0, 'no_encontradas' => 0];

        foreach (array_slice((array) ($datos['rows'] ?? []), 0, 600) as $fila) {
            if (! is_array($fila)) continue;
            $reg = GuiaEstado::registrar($fila);
            if (! $reg) continue;

            $cuenta['actualizadas']++;
            match ((int) $reg->etapa) {
                4       => $cuenta['entregadas']++,
                3       => $cuenta['en_ruta']++,
                2       => $cuenta['bodega']++,
                default => $cuenta['otras']++,
            };
        }

        foreach (array_slice((array) ($datos['missing'] ?? []), 0, 600) as $g) {
            GuiaEstado::noEncontrada((string) $g);
            $cuenta['no_encontradas']++;
        }

        Setting::put('express_puente_ultima', now()->toIso8601String());

        return $this->responder(['ok' => true] + $cuenta);
    }

    /**
     * Prueba la API oficial de Express con la key de Railway (TNT_API_KEY) y
     * muestra la FORMA de las respuestas, con los datos personales tapados.
     * Solo con sesión iniciada en el panel.
     */
    public function pruebaApi()
    {
        if (! \App\Services\ExpressApi::configurada()) {
            return response()->json(['ok' => false, 'error' => 'Falta la variable TNT_API_KEY en Railway']);
        }

        $guia = null;
        try {
            $guia = GuiaFoto::whereNotNull('guia')->where('guia', '!=', '')->orderByDesc('id')->value('guia');
        } catch (\Throwable $e) {
        }
        $guia = preg_replace('/\D/', '', (string) $guia) ?: '7506030';

        $pruebas = [
            'GET orders'               => ['GET', 'orders', ['limit' => 2, 'per_page' => 2]],
            'GET orders/{guia}'        => ['GET', 'orders/' . $guia, []],
            'GET orders?tracking'      => ['GET', 'orders', ['tracking' => $guia, 'trackingCode' => $guia, 'code' => $guia, 'q' => $guia]],
            'GET orders/lookup'        => ['GET', 'orders/lookup', ['codes' => $guia]],
            'GET orders/status'        => ['GET', 'orders/status', ['codes' => $guia]],
            'GET agencies'             => ['GET', 'agencies', []],
        ];

        $out = ['ok' => true, 'base' => \App\Services\ExpressApi::base(), 'guia_probada' => $guia,
            'key' => \App\Services\ExpressApi::diagnosticoKey()];

        // ¿Cómo quiere Express la key? Se prueba cada forma y se guarda la que sirva.
        $modos = [];
        foreach (\App\Services\ExpressApi::MODOS as $modo) {
            $r = \App\Services\ExpressApi::llamar('GET', 'agencies', [], $modo);
            $modos[$modo] = $r['status'] . ' ' . (is_array($r['json']) ? ($r['json']['message'] ?? 'ok') : '');
            if ($r['status'] >= 200 && $r['status'] < 300) {
                \App\Models\Setting::put('tnt_api_modo', $modo);
                break;
            }
        }
        $out['formas_de_mandar_la_key'] = $modos;
        foreach ($pruebas as $nombre => [$m, $ruta, $datos]) {
            $r = \App\Services\ExpressApi::llamar($m, $ruta, $datos);
            $out[$nombre] = [
                'status' => $r['status'],
                'json'   => \App\Services\ExpressApi::tapar($r['json']),
                'texto'  => $r['json'] === null ? mb_substr($r['texto'], 0, 300) : null,
            ];
        }

        return response()->json($out, 200, [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /** Lee el cuerpo (JSON en texto plano) y valida la clave. Null si no vale. */
    private function leer(Request $request): ?array
    {
        $datos = json_decode((string) $request->getContent(), true);
        if (! is_array($datos)) return null;

        $k = (string) ($datos['k'] ?? '');
        if ($k === '' || ! hash_equals(self::clave(), $k)) return null;

        return $datos;
    }

    private function responder(array $json, int $status = 200)
    {
        return response()->json($json, $status)
            ->header('Access-Control-Allow-Origin', self::ORIGEN)
            ->header('Vary', 'Origin');
    }
}
