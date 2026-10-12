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

        $dias = max(7, min(365, (int) $request->query('dias', 90)));
        $zona = 'America/El_Salvador';
        $filas = $this->filas($dias, $zona);

        // 3. Una hoja lista para guardar como PDF (o leer así nomás en el
        //    teléfono, con el chat de cada uno a un toque). Se abre sola la
        //    ventana de imprimir: ahí se elige "Guardar como PDF".
        return response($this->hoja($filas, $dias, $zona))
            ->header('Content-Type', 'text/html; charset=UTF-8');
    }

    /**
     * "Avisar a agotados": la misma lista, con casillas, para mandarles la
     * plantilla de "ya llegó" de un solo botón (por talla o a todos).
     */
    public function avisar(Request $request)
    {
        @set_time_limit(300);

        $dias  = max(7, min(365, (int) $request->query('dias', 120)));
        $zona  = 'America/El_Salvador';
        $filas = $this->filas($dias, $zona);
        $e = fn ($t) => htmlspecialchars((string) $t, ENT_QUOTES, 'UTF-8');

        $cfg     = (array) config('asistente.plantilla_aviso', []);
        $nombre  = $e($cfg['nombre'] ?? 'aviso_disponibles');
        $idioma  = $e($cfg['idioma'] ?? 'es');
        $texto   = $e($cfg['texto'] ?? '');
        $precio  = (float) ($cfg['precio'] ?? 0.074);
        $csrf    = $e(csrf_token());
        $url     = $e(route('agotados.enviar'));

        $porTalla = [];
        $filasHtml = '';
        foreach ($filas as $i => $f) {
            $tallas = [];
            if (preg_match('/Talla ([^·]+)/u', $f['pidio'], $m)) $tallas = array_map('trim', explode(',', $m[1]));
            if (! $tallas) $tallas = ['sin talla'];
            foreach ($tallas as $t) $porTalla[$t] = ($porTalla[$t] ?? 0) + 1;

            $ya = $f['avisado'] ? 'avisado el ' . $f['avisado']->format('d/m H:i') : '';
            $filasHtml .= '<tr class="' . ($ya ? 'ya' : '') . '" data-tallas="' . $e('|' . implode('|', $tallas) . '|') . '">'
                . '<td><input type="checkbox" class="fila" value="' . $f['conv_id'] . '"' . ($ya ? '' : ' checked') . '></td>'
                . '<td><b>' . $e($f['nombre'] ?: '—') . '</b><br><a href="' . $e($f['enlace']) . '" target="_blank">' . $e($f['telefono']) . '</a></td>'
                . '<td>' . $e($f['pidio'] ?: '—') . ($f['ya'] ? '<br><small>ya pidió después</small>' : '') . '</td>'
                . '<td class="estado">' . $e($ya) . '</td>'
                . '</tr>';
        }
        ksort($porTalla);
        $chips = '';
        foreach ($porTalla as $t => $n) {
            $chips .= '<label class="chip"><input type="checkbox" class="talla" value="' . $e($t) . '" checked> ' . $e($t) . ' (' . $n . ')</label> ';
        }
        $total = count($filas);

        $html = <<<HTML
<!doctype html>
<html lang="es"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Avisar a agotados</title>
<style>
  body{font-family:Arial,Helvetica,sans-serif;font-size:13px;color:#111;margin:16px;max-width:1000px}
  h1{font-size:19px;margin:0 0 6px}
  .caja{border:1px solid #ccd;border-radius:10px;padding:10px 12px;margin:10px 0;background:#f8fafc}
  .chip{display:inline-block;border:1px solid #bbb;border-radius:12px;padding:3px 9px;margin:3px 4px 3px 0;background:#fff;cursor:pointer}
  input[type=text],textarea{width:100%;box-sizing:border-box;padding:6px;border:1px solid #bbb;border-radius:6px;font-size:13px}
  table{width:100%;border-collapse:collapse;margin-top:8px}
  th,td{border:1px solid #ddd;padding:5px 6px;text-align:left;vertical-align:top}
  th{background:#eef2f7}
  tr.ya td{color:#999}
  tr.oculta{display:none}
  .ok{color:#1e7b34;font-weight:bold} .mal{color:#b3261e;font-weight:bold}
  button.enviar{font-size:15px;padding:10px 18px;border-radius:8px;border:0;background:#1e7b34;color:#fff;cursor:pointer}
  button.enviar:disabled{background:#999}
  #progreso{font-weight:bold;margin-left:10px}
</style></head>
<body>
<h1>📣 Avisar a agotados</h1>
<div>{$total} clientes de los últimos {$dias} días. Los que ya recibieron el aviso (últimos 30 días) salen en gris y sin marcar.</div>

<div class="caja"><b>1. Tallas que llegaron</b> (desmarcá las que no entraron):<br>{$chips}</div>

<div class="caja"><b>2. Plantilla</b> (tiene que estar <b>aprobada</b> en WhatsApp Manager)<br>
  Nombre: <input type="text" id="nombre" value="{$nombre}" style="width:220px"> Idioma: <input type="text" id="idioma" value="{$idioma}" style="width:60px"><br><br>
  Texto que queda en el chat del panel (el mismo de la plantilla):<br><textarea id="texto" rows="4">{$texto}</textarea></div>

<div class="caja"><b>3. Enviar</b><br>
  <button class="enviar" id="enviar">Enviar</button><span id="progreso"></span>
  <div style="color:#555;margin-top:6px">Se manda de a 10. No cierres esta pestaña hasta que termine. Si algo falla, se detiene y te dice por qué.</div></div>

<table>
<thead><tr><th><input type="checkbox" id="todos" checked></th><th>Cliente</th><th>Lo que pidió</th><th>Aviso</th></tr></thead>
<tbody>{$filasHtml}</tbody>
</table>

<script>
const PRECIO = {$precio};
const filas = () => [...document.querySelectorAll('tbody tr')];
function aplicarTallas() {
  const on = [...document.querySelectorAll('.talla:checked')].map(c => '|' + c.value + '|');
  filas().forEach(tr => {
    const sirve = on.some(t => tr.dataset.tallas.includes(t));
    tr.classList.toggle('oculta', !sirve);
  });
  contar();
}
function marcados() {
  return filas().filter(tr => !tr.classList.contains('oculta')).map(tr => tr.querySelector('.fila')).filter(c => c.checked).map(c => c.value);
}
function contar() {
  const n = marcados().length;
  document.getElementById('enviar').textContent = 'Enviar a ' + n + ' clientes (≈ $' + (n * PRECIO).toFixed(2) + ')';
}
document.querySelectorAll('.talla').forEach(c => c.addEventListener('change', aplicarTallas));
document.querySelectorAll('.fila').forEach(c => c.addEventListener('change', contar));
document.getElementById('todos').addEventListener('change', e => {
  filas().filter(tr => !tr.classList.contains('oculta') && !tr.classList.contains('ya')).forEach(tr => tr.querySelector('.fila').checked = e.target.checked);
  contar();
});
document.getElementById('enviar').addEventListener('click', async () => {
  const ids = marcados();
  const nombre = document.getElementById('nombre').value.trim();
  const idioma = document.getElementById('idioma').value.trim();
  const texto  = document.getElementById('texto').value.trim();
  if (!ids.length) return alert('No hay nadie marcado.');
  if (!nombre || !texto) return alert('Falta el nombre de la plantilla o el texto.');
  if (!confirm('¿Mandar la plantilla "' + nombre + '" a ' + ids.length + ' clientes?\\nCosto aproximado: $' + (ids.length * PRECIO).toFixed(2))) return;

  const btn = document.getElementById('enviar'), prog = document.getElementById('progreso');
  btn.disabled = true;
  let ok = 0, mal = 0;
  for (let i = 0; i < ids.length; i += 10) {
    const lote = ids.slice(i, i + 10);
    prog.textContent = 'Enviando… ' + Math.min(i + 10, ids.length) + ' de ' + ids.length;
    let r;
    try {
      r = await fetch('{$url}', {method: 'POST', headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': '{$csrf}'},
        body: JSON.stringify({ids: lote, nombre, idioma, texto})}).then(x => x.json());
    } catch (e) { prog.innerHTML = '<span class="mal">Se cortó la conexión. Enviados: ' + ok + '. Volvé a cargar la página y seguí (los ya avisados no se repiten).</span>'; btn.disabled = false; return; }
    let fallosLote = 0, ultimoError = '';
    for (const [id, res] of Object.entries(r.resultados || {})) {
      const td = document.querySelector('.fila[value="' + id + '"]').closest('tr').querySelector('.estado');
      if (res.ok) { ok++; td.innerHTML = '<span class="ok">✓ enviado</span>'; document.querySelector('.fila[value="' + id + '"]').checked = false; }
      else { mal++; fallosLote++; ultimoError = res.error; td.innerHTML = '<span class="mal">✗ ' + res.error + '</span>'; }
    }
    if (fallosLote === lote.length) {
      prog.innerHTML = '<span class="mal">Se detuvo: ' + ultimoError + '</span>';
      btn.disabled = false; contar(); return;
    }
  }
  prog.innerHTML = '<span class="ok">Listo: ' + ok + ' enviados' + (mal ? ', ' + mal + ' con error' : '') + '.</span>';
  btn.disabled = false; contar();
});
aplicarTallas();
</script>
</body></html>
HTML;

        return response($html)->header('Content-Type', 'text/html; charset=UTF-8');
    }

    /** Manda la plantilla a un lote (de a 10) y dice cómo le fue a cada uno. */
    public function enviar(Request $request)
    {
        @set_time_limit(120);

        $ids    = array_slice(array_map('intval', (array) $request->input('ids', [])), 0, 20);
        $nombre = trim((string) $request->input('nombre'));
        $idioma = trim((string) $request->input('idioma', 'es')) ?: 'es';
        $texto  = trim((string) $request->input('texto'));

        if ($nombre === '' || $texto === '' || ! preg_match('/^[a-z0-9_]+$/', $nombre)) {
            return response()->json(['resultados' => array_fill_keys($ids, ['ok' => false, 'error' => 'Nombre de plantilla inválido (solo minúsculas, números y _).'])]);
        }

        $res = [];
        foreach ($ids as $id) {
            $conv = WaConversacion::find($id);
            if (! $conv) { $res[$id] = ['ok' => false, 'error' => 'no existe el chat']; continue; }

            // Nunca dos veces al mismo cliente (si se recarga la página a medias).
            $ya = WaMensaje::where('conversacion_id', $id)->where('tipo', 'template')
                ->where('estado', '!=', 'fallido')->where('created_at', '>=', now()->subDays(30))->exists();
            if ($ya) { $res[$id] = ['ok' => true, 'error' => '']; continue; }

            $m = \App\Services\WhatsappApi::enviarPlantilla($conv, $nombre, $idioma, $texto);
            $res[$id] = $m->estado === 'fallido'
                ? ['ok' => false, 'error' => (string) ($m->error ?: 'falló')]
                : ['ok' => true, 'error' => ''];
        }

        return response()->json(['resultados' => $res]);
    }

    /** Una fila por cliente al que se le dijo "agotado" en esos días. */
    private function filas(int $dias, string $zona): array
    {
        $desde = now()->subDays($dias);

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

                // ¿Ya se le mandó la plantilla de "ya llegó"?
                $avisado = WaMensaje::where('conversacion_id', $conv->id)
                    ->where('tipo', 'template')->where('estado', '!=', 'fallido')
                    ->where('created_at', '>=', now()->subDays(30))
                    ->max('created_at');

                $filas[] = [
                    'conv_id'  => (int) $conv->id,
                    'avisado'  => $avisado ? \Illuminate\Support\Carbon::parse($avisado)->timezone($zona) : null,
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

        return $filas;
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
