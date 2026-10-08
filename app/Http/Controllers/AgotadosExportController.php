<?php

namespace App\Http\Controllers;

use App\Models\WaConversacion;
use App\Models\WaMensaje;
use App\Services\Asistente\Entender;
use App\Services\Etiquetado;
use Illuminate\Http\Request;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

/**
 * La lista de a quién le dijiste "está agotado", en Excel.
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

        // 3. El Excel.
        $ruta = storage_path('app/agotados-' . now($zona)->format('Ymd-His') . '.xlsx');

        $w = new Writer();
        $w->openToFile($ruta);
        $w->addRow(Row::fromValues(['Fecha del aviso', 'Nombre', 'Teléfono', 'Lo que pidió', 'Lo que escribió', 'Lo que le dijiste', '¿Ya pidió?', 'Abrir chat']));

        foreach ($filas as $f) {
            $w->addRow(Row::fromValues([
                $f['fecha'] ? $f['fecha']->format('d/m/Y H:i') : '',
                $f['nombre'], $f['telefono'], $f['pidio'], $f['escribio'], $f['dijiste'], $f['ya'], $f['enlace'],
            ]));
        }

        $w->close();

        return response()->download($ruta, 'agotados-' . now($zona)->format('Y-m-d') . '.xlsx')->deleteFileAfterSend(true);
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
