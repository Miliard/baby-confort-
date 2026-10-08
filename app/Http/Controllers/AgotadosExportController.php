<?php

namespace App\Http\Controllers;

use App\Models\WaConversacion;
use App\Models\WaMensaje;
use App\Services\Asistente\Entender;
use App\Services\Etiquetado;
use Illuminate\Http\Request;

/**
 * La lista de a quién le dijiste "está agotado", lista para guardar en PDF.
 *
 * Una fila por cliente: nombre, teléfono, cuándo se le dijo, qué pidió (la
 * talla, el tipo y el producto que se leen en lo que escribió y en lo que le
 * contestaste), los dos mensajes tal cual para revisar, si ya hizo un pedido
 * después, y el enlace para abrirle el chat.
 *
 * Salen los que tienen la etiqueta de Agotados y también los que no la tienen
 * pero en el chat se les dijo que algo estaba agotado (por si la etiqueta se
 * quitó o nunca se puso).
 */
class AgotadosExportController extends Controller
{
    public function descargar(Request $request)
    {
        @set_time_limit(300);

        $dias  = max(7, min(365, (int) $request->query('dias', 90)));
        $desde = now()->subDays($dias);
        $zona  = 'America/El_Salvador';

        // 1. Las conversaciones: con la etiqueta, o con un "agotado" en el chat.
        $ids = [];

        $etq = \App\Models\WaEtiqueta::porRol('agotado');
        if ($etq) {
            foreach ($etq->conversaciones()->pluck('wa_conversaciones.id') as $id) $ids[(int) $id] = true;
        }

        WaMensaje::where('direccion', 'saliente')
            ->where('created_at', '>=', $desde)
            ->whereNotNull('texto')
            ->where(function ($q) {
                foreach (['%agot%', '%no hay%', '%no tenemos%', '%no tengo%', '%no nos queda%', '%se nos acab%',
                          '%se acab%', '%se termin%', '%aviso cuando%', '%le aviso%', '%le avisamos%', '%apenas entren%'] as $p) {
                    $q->orWhere('texto', 'like', $p);
                }
            })
            ->select(['id', 'conversacion_id', 'texto'])
            ->chunkById(500, function ($ms) use (&$ids) {
                foreach ($ms as $m) {
                    if (Etiquetado::diceAgotado($m->texto)) $ids[(int) $m->conversacion_id] = true;
                }
            });

        $productos = $this->palabrasDeProductos();

        // 2. Una fila por conversación.
        $filas = [];

        foreach (array_chunk(array_keys($ids), 200) as $lote) {
            foreach (WaConversacion::whereIn('id', $lote)->get() as $conv) {
                $msjs = WaMensaje::where('conversacion_id', $conv->id)
                    ->where('created_at', '>=', $desde)
                    ->orderBy('id')
                    ->get(['id', 'direccion', 'tipo', 'texto', 'created_at']);

                // El último "agotado" que se le dijo.
                $aviso = $msjs->filter(fn ($m) => $m->direccion === 'saliente' && Etiquetado::diceAgotado($m->texto))->last();

                // Con la etiqueta pero sin el mensaje en el período: igual sale,
                // con lo último que escribió.
                $hasta = $aviso ? $aviso->id : ($msjs->last()->id ?? 0);
                if (! $hasta && ! $aviso) continue;

                // Lo que escribió antes del aviso (los últimos mensajes, mismo día o el anterior).
                $limite = $aviso ? $aviso->created_at->copy()->subHours(48) : now()->subDays($dias);
                $suyos = $msjs->filter(fn ($m) => $m->direccion === 'entrante' && $m->id <= $hasta
                        && $m->created_at >= $limite && filled($m->texto) && ! str_starts_with(trim((string) $m->texto), '['))
                    ->slice(-5);

                $loQueEscribio = trim($suyos->pluck('texto')->map(fn ($t) => trim(preg_replace('/\s+/u', ' ', (string) $t)))->implode(' / '));
                $loQueDijiste  = $aviso ? trim(preg_replace('/\s+/u', ' ', (string) $aviso->texto)) : '';

                // ¿Ya pidió después del aviso? (se le mandó una orden de envío)
                $pidio = $aviso && $msjs->contains(fn ($m) => $m->id > $aviso->id && $m->direccion === 'saliente'
                        && preg_match('/orden de env[ií]o|total a pagar/iu', (string) $m->texto));

                $tel = $conv->esExtranjero() ? $conv->telefonoLegible() : preg_replace('/\D/', '', (string) $conv->telefono ?: substr((string) $conv->wa_id, -8));

                $filas[] = [
                    'fecha'    => $aviso ? $aviso->created_at->timezone($zona) : ($msjs->last()?->created_at?->timezone($zona)),
                    'nombre'   => $conv->comoSeLlama() ?: (string) $conv->nombre,
                    'telefono' => $tel,
                    'pidio'    => $this->quePidio($loQueEscribio . ' ' . $loQueDijiste, $productos),
                    'escribio' => mb_substr($loQueEscribio, 0, 500),
                    'dijiste'  => mb_substr($loQueDijiste, 0, 500),
                    'ya'       => $pidio ? 'Sí, ya pidió después' : '',
                    'enlace'   => 'https://wa.me/' . preg_replace('/\D/', '', (string) $conv->wa_id),
                ];
            }
        }

        usort($filas, fn ($a, $b) => ($b['fecha']?->timestamp ?? 0) <=> ($a['fecha']?->timestamp ?? 0));

        // 3. Una hoja lista para guardar como PDF (o leer así nomás en el
        //    teléfono, con el chat de cada uno a un toque). Se abre sola la
        //    ventana de imprimir: ahí se elige "Guardar como PDF".
        return response($this->hoja($filas, $dias, $zona))
            ->header('Content-Type', 'text/html; charset=UTF-8');
    }

    private function hoja(array $filas, int $dias, string $zona): string
    {
        $e = fn ($t) => htmlspecialchars((string) $t, ENT_QUOTES, 'UTF-8');

        // Cuántos por talla, arriba, para saber de un vistazo qué se pidió más.
        $porTalla = [];
        foreach ($filas as $f) {
            if ($f['ya']) continue;
            if (preg_match('/Talla ([^·]+)/u', $f['pidio'], $m)) {
                foreach (array_map('trim', explode(',', $m[1])) as $t) $porTalla[$t] = ($porTalla[$t] ?? 0) + 1;
            } else {
                $porTalla['(sin talla clara)'] = ($porTalla['(sin talla clara)'] ?? 0) + 1;
            }
        }
        arsort($porTalla);

        $resumen = '';
        foreach ($porTalla as $t => $n) $resumen .= '<span class="chip">' . $e($t) . ': <b>' . $n . '</b></span> ';

        $pendientes = count(array_filter($filas, fn ($f) => ! $f['ya']));

        $filasHtml = '';
        foreach ($filas as $i => $f) {
            $filasHtml .= '<tr class="' . ($f['ya'] ? 'ya' : '') . '">'
                . '<td>' . ($i + 1) . '</td>'
                . '<td>' . $e($f['fecha'] ? $f['fecha']->format('d/m/Y') : '') . '</td>'
                . '<td><b>' . $e($f['nombre'] ?: '—') . '</b><br><a href="' . $e($f['enlace']) . '">' . $e($f['telefono']) . '</a></td>'
                . '<td class="pidio">' . $e($f['pidio'] ?: '—') . ($f['ya'] ? '<br><span class="ok">✓ ya pidió después</span>' : '') . '</td>'
                . '<td class="txt"><b>Escribió:</b> ' . $e(mb_substr($f['escribio'], 0, 220)) . '<br><b>Le dijiste:</b> ' . $e(mb_substr($f['dijiste'], 0, 160)) . '</td>'
                . '</tr>';
        }

        $hoy = now($zona)->format('d/m/Y H:i');
        $total = count($filas);

        return <<<HTML
<!doctype html>
<html lang="es"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Agotados {$hoy}</title>
<style>
  body{font-family:Arial,Helvetica,sans-serif;font-size:12px;color:#111;margin:18px}
  h1{font-size:18px;margin:0 0 4px}
  .sub{color:#555;margin-bottom:10px}
  .chip{display:inline-block;border:1px solid #bbb;border-radius:10px;padding:2px 8px;margin:2px 4px 2px 0}
  table{width:100%;border-collapse:collapse;margin-top:12px}
  th,td{border:1px solid #ccc;padding:5px 6px;vertical-align:top;text-align:left}
  th{background:#eef2f7}
  tr{page-break-inside:avoid}
  tr.ya td{color:#888}
  .pidio{font-weight:bold;width:22%}
  .txt{font-size:11px;color:#333}
  .ok{color:#2a7a2a;font-weight:normal}
  a{color:#0b5cad}
  .barra{margin:10px 0}
  .barra button{font-size:14px;padding:8px 14px;border-radius:8px;border:1px solid #0b5cad;background:#0b5cad;color:#fff}
  @media print{.barra{display:none} body{margin:8mm}}
</style></head>
<body>
<h1>Clientes con producto agotado</h1>
<div class="sub">Últimos {$dias} días · {$total} clientes ({$pendientes} sin pedir todavía) · generado el {$hoy}</div>
<div class="barra"><button onclick="window.print()">📄 Guardar como PDF</button>
  <span style="color:#555">En la ventana que se abre, elegí <b>"Guardar como PDF"</b> como impresora.</span></div>
<div><b>Lo que más pidieron (sin contar los que ya pidieron):</b><br>{$resumen}</div>
<table>
<thead><tr><th>#</th><th>Aviso</th><th>Cliente</th><th>Lo que pidió</th><th>Mensajes</th></tr></thead>
<tbody>{$filasHtml}</tbody>
</table>
<script>setTimeout(function(){ window.print(); }, 600);</script>
</body></html>
HTML;
    }

    /**
     * "Talla M · cinta · Cinta de noche": lo que se entiende que pidió.
     * Es una ayuda para ordenar: los mensajes van al lado para confirmar.
     */
    private function quePidio(string $texto, array $productos): string
    {
        $partes = [];

        $tallas = Entender::tallas($texto);
        if (! $tallas && ($n = Entender::numeroDeTalla($texto)) !== null) {
            $t = config('asistente.tallas_numericas', [])[$n] ?? null;
            if ($t) $tallas = [$t];
        }
        if ($tallas) $partes[] = 'Talla ' . implode(', ', array_unique($tallas));

        $tipo = Entender::tipo($texto);
        if (in_array($tipo, ['cinta', 'calzoncito', 'ambos'], true)) $partes[] = $tipo === 'ambos' ? 'cinta o calzoncito' : $tipo;

        $n = Entender::normalizar($texto);
        $nombres = [];
        foreach ($productos as $nombre => $palabras) {
            foreach ($palabras as $p) {
                if (preg_match('/\b' . preg_quote($p, '/') . '\b/', $n)) { $nombres[] = $nombre; break; }
            }
        }
        if ($nombres) $partes[] = implode(' / ', array_slice(array_unique($nombres), 0, 3));

        return implode(' · ', $partes);
    }

    /** Las palabras que distinguen a cada producto ("noche", "magic", "jumbo"…). */
    private function palabrasDeProductos(): array
    {
        $comunes = ['calzoncito', 'calzoncitos', 'cinta', 'pegar', 'lados', 'panal', 'panales', 'para', 'bebe', 'bebes',
                    'aiwibi', 'aiwina', 'tipo', 'ajuste', 'lateral', 'los', 'las', 'del', 'con', 'super'];
        $out = [];

        try {
            foreach (\App\Models\Product::query()->get(['id', 'name']) as $p) {
                $nombre = trim(preg_replace('/[^\p{L}\p{N}\s()]/u', '', (string) $p->name));
                $palabras = array_values(array_filter(
                    preg_split('/[^a-z0-9]+/', Entender::normalizar((string) $p->name), -1, PREG_SPLIT_NO_EMPTY),
                    fn ($w) => mb_strlen($w) >= 4 && ! in_array($w, $comunes, true)
                ));
                if ($palabras) $out[$nombre] = $palabras;
            }
        } catch (\Throwable $e) {
        }

        return $out;
    }
}
