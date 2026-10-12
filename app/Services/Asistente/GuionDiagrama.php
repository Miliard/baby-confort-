<?php

namespace App\Services\Asistente;

use App\Models\ProductSize;
use App\Services\Municipios;

/**
 * El diagrama de Wil (Lucidchart), tal cual: un if y un else por paso.
 *
 *   talla     ¿Dijo talla o peso?            NO → pedir talla o peso
 *             ¿Hay esa talla?                NO → pedir el peso (si falta) y sugerir la que hay
 *                                                  ¿Acepta?  NO → agradecer y avisar cuando entre
 *   opciones  Mandar las presentaciones (fotos)
 *             ¿Tiene dudas?                  SÍ → explicar el propósito de cada una
 *             Elige una presentación
 *   municipio "Tenemos cobertura nacional… ¿desde cuál municipio nos escribe?"
 *             ¿San Miguel?                   SÍ → preguntar la colonia y pasarle el chat a Wil
 *   envio     "El envío… vale $2.50 ¿le parece bien?"   NO → no hacer nada
 *   datos     cuántos paquetes, nombre y dirección → la orden de envío
 *   orden     "¿Toda la información es correcta?"      SÍ → listo (a Pedidos)
 *
 * Fuera del diagrama: fotos, audios, rebajas, reclamos, etc. se le pasan a Wil
 * (las mismas reglas de siempre). Las preguntas sueltas se contestan con los
 * textos de config/asistente.php y nada más.
 */
trait GuionDiagrama
{
    private function correrConDiagrama($mensajes, array $turnos): void
    {
        $textos = [];
        $boton  = null;

        foreach ($mensajes as $m) {
            $this->f->ultimo_mensaje_id = $m->id;
            $t = $this->textoParaEjemplos($m);
            if ($this->paraWil) break;
            if ($b = $this->botones[$m->id] ?? null) $boton = $b;
            if ($t !== null && $t !== '') $textos[] = $t;
            if ($m->responde_a && ($sid = ((array) $this->f->dato('tarjetas', []))[$m->responde_a] ?? null)) {
                $this->f->poner('citada', (string) $sid);
            }
        }

        if (! $this->paraWil && count($turnos) >= (int) config('asistente.max_turnos_hora', 40)) {
            $this->paraWil = 'demasiados mensajes seguidos';
        }
        if ($this->paraWil) {
            $this->pasarAWil($this->paraWil, ! $this->wilEnSilencio);
            return;
        }
        if (! $textos && ! $boton) { $this->f->save(); return; }

        $primero = ! $this->f->dato('turnos');
        $turnos[] = now()->timestamp;
        $this->f->poner('turnos', $turnos);

        $this->dg(implode("\n", $textos), $boton, $primero);
        $this->f->save();
    }

    private function dg(string $texto, ?string $boton, bool $primero): void
    {
        $f = $this->f;
        $paso = (string) ($f->dato('dpaso') ?: 'talla');
        $n = Entender::normalizar($texto);
        $hola = $primero ? '¡Hola! 😊 ' : '';

        // ── Una pregunta suelta con respuesta escrita por vos (config) ──────
        // Se contesta eso y nada más. El envío, las promos y "¿llega
        // mañana?" no van por acá.
        $faq = Entender::respuesta($texto, (array) config('asistente.respuestas', []));
        if ($faq !== null && ! str_contains($faq, '{envio}') && ! str_contains($faq, '{promos}')
            && ! $this->dgDiceAlgoDelPaso($paso, $texto)) {
            $this->dgDecir($hola . $this->rellenar($faq), $paso);
            return;
        }

        // ── Tocó un botón o eligió de una lista ─────────────────────────────
        if ($boton && ! str_starts_with($boton, 'conf:')) {
            [$k, $v] = explode(':', $boton, 2) + [1 => ''];
            if ($k === 'talla') { $this->dgConTalla($v, $hola); return; }
            if ($k === 'tipo')  { $this->dgOpciones($v); return; }
            if ($k === 'op' && ($s = ProductSize::with('product')->find($v))) { $this->dgElegir($s, null); return; }
            if ($k === 'envio') { $this->dgEnvio($v === 'si' ? 'si' : 'no'); return; }
        }

        switch ($paso) {
            case 'talla':     $this->dgTalla($texto, $hola); return;
            case 'peso':      $this->dgPeso($texto, $hola); return;
            case 'unidad':    $this->dgUnidad($texto); return;
            case 'sugerencia':$this->dgSugerencia($texto); return;
            case 'opciones':  $this->dgOpciones($texto); return;
            case 'municipio': $this->dgMunicipio($texto); return;
            case 'depto':     $this->dgDepto($texto); return;
            case 'colonia':   $this->dgColonia($texto); return;
            case 'envio':     $this->dgEnvio($texto); return;
            case 'datos':     $this->dgDatos($texto); return;
            case 'telefono':  $this->dgTelefono($texto); return;
            case 'orden':     $this->dgOrden($texto, $boton); return;
            case 'cantidad_item': $this->dgCantidadItem($texto); return;
        }
    }

    /** ¿El mensaje trae lo que se le está pidiendo en este paso? (para no tomarlo como pregunta suelta) */
    private function dgDiceAlgoDelPaso(string $paso, string $texto): bool
    {
        return match ($paso) {
            'talla', 'peso' => (bool) ($this->dgTallas($texto) || Entender::peso($texto)),
            'municipio'     => (bool) Municipios::buscarEn($texto),
            'envio', 'orden', 'sugerencia' => Entender::siNo($texto) !== null,
            'datos'         => Entender::pareceDireccion($texto) || Entender::cantidad($texto) !== null,
            default         => false,
        };
    }

    /** Manda el mensaje y anota en qué paso queda. */
    private function dgDecir(string $msg, string $paso): void
    {
        $this->f->poner('dpaso', $paso);
        $this->f->paso = 'diagrama · ' . $paso;
        $this->texto($msg);
    }

    /** No entendió: una vez se le repite, la segunda te lo pasa. */
    private function dgNoEntendi(string $paso, string $repetir): void
    {
        $fallos = (array) $this->f->dato('fallos', []);
        $fallos[$paso] = ($fallos[$paso] ?? 0) + 1;
        $this->f->poner('fallos', $fallos);

        if ($fallos[$paso] >= 2) {
            $this->pasarAWil('no le entendí (' . $paso . ')');
            return;
        }
        $this->dgDecir("Disculpe, no le entendí 🙏\n\n" . $repetir, $paso);
    }

    // ════════════════════════════════════════════════════════════════════════
    // 1 · Talla o peso
    // ════════════════════════════════════════════════════════════════════════

    private function dgTallas(string $texto): array
    {
        // "XLyM", "xl/m", "M-XL": tallas pegadas.
        $t = '(xxxl|xxl|2xl|3xl|xl|rn|l|m|s|g|xg|xxg)';
        $texto = preg_replace('/\b' . $t . '(y|\/|-|\+)' . $t . '\b/iu', '$1 y $3', $texto);
        $tallas = Entender::tallas($texto);
        if (! $tallas && ($num = Entender::numeroDeTalla($texto)) !== null) {
            $t = config('asistente.tallas_numericas', [])[$num] ?? null;
            if ($t) $tallas = [$t];
        }
        return array_values(array_intersect(array_map('mb_strtoupper', $tallas), array_map('mb_strtoupper', (array) config('asistente.tallas', []))));
    }

    private function dgPedirTalla(string $hola): void
    {
        $lista = '';
        if (! $this->f->dato('tallas_vistas')) {
            $this->f->poner('tallas_vistas', true);
            $lista = "Estas son las tallas que tenemos:\n\n" . implode("\n", $this->guionLineasTallas()) . "\n\n";
        }
        $this->dgDecir($hola . $lista . '¿Qué talla usa su bebé o cuánto pesa? 😊', 'talla');
    }

    private function dgTalla(string $texto, string $hola): void
    {
        $tallas = $this->dgTallas($texto);
        if ($tallas) {
            if (count($tallas) > 1) { $this->dgConVariasTallas($tallas, $hola); return; }
            $this->dgConTalla($tallas[0], $hola);
            return;
        }

        if ($p = Entender::peso($texto)) { $this->dgConPeso($p, $hola); return; }

        // Primer mensaje (saludo, "información", "¿tiene pampers?"): se pide la talla.
        if ($hola !== '' || Entender::esSaludo($texto) || ! $this->f->dato('tallas_vistas')) { $this->dgPedirTalla($hola); return; }

        if (Entender::esCortesia($texto)) return;
        $this->dgNoEntendi('talla', '¿Qué talla usa su bebé o cuánto pesa? 😊');
    }

    /**
     * Pidió varias ("M y XL"): se le mandan las presentaciones de TODAS las
     * que hay, cada grupo con su talla arriba. Las que no hay, se le avisa.
     */
    private function dgConVariasTallas(array $tallas, string $hola): void
    {
        $disp = array_map('mb_strtoupper', $this->tallasDisponibles());
        $hay = array_values(array_filter($tallas, fn ($t) => in_array(mb_strtoupper($t), $disp, true)));
        $no  = array_values(array_diff($tallas, $hay));

        if (! $hay) { $this->dgConTalla($tallas[0], $hola); return; }

        $antes = $hola;
        if ($no) {
            $this->marcarAgotado();
            $antes .= 'En este momento no tenemos talla ' . $this->unir(array_map(fn ($t) => $this->bonita($t), $no)) . " 😔\n\n";
        }

        $this->f->poner('tallas', array_map('mb_strtoupper', $hay));
        $this->dgMostrar($antes);
    }

    /** Dijo una talla: si hay, las presentaciones; si no, el peso para sugerir. */
    private function dgConTalla(string $talla, string $hola): void
    {
        $f = $this->f;
        $disp = array_map('mb_strtoupper', $this->tallasDisponibles());

        if (in_array(mb_strtoupper($talla), $disp, true)) {
            $f->poner('tallas', [mb_strtoupper($talla)]);
            $this->dgMostrar($hola);
            return;
        }

        $this->marcarAgotado();
        $f->poner('sin_talla', mb_strtoupper($talla));

        // Ya eligió de otra talla: se le avisa que esta no hay y se sigue.
        if ($f->dato('carrito')) {
            $this->dgDespuesDeElegir('En este momento no tenemos talla ' . $this->bonita($talla) . " 😔\n\n");
            return;
        }

        if ($f->dato('peso_lb') !== null) {
            $this->dgSugerir((float) $f->dato('peso_lb'), $hola . 'En este momento no tenemos talla ' . $this->bonita($talla) . ' 😔 ');
            return;
        }

        $quien = in_array(mb_strtoupper($talla), array_map('mb_strtoupper', (array) config('asistente.tallas_nino', [])), true) ? 'el niño o la niña' : 'su bebé';
        $this->dgDecir($hola . 'En este momento no tenemos talla ' . $this->bonita($talla) . " 😔 ¿Cuánto pesa {$quien}? Así le sugiero la talla que le puede quedar.", 'peso');
    }

    private function dgConPeso(array $p, string $hola): void
    {
        $f = $this->f;

        if (! empty($p['kg'])) {
            $lb = str_ends_with((string) ($p['dicho'] ?? ''), 'lb') ? (float) $p['dicho'] : round($p['kg'] * 2.2046, 1);
        } else {
            $v = (float) ($p['valor'] ?? 0);
            if ($v <= 0) { $this->dgPedirTalla($hola); return; }
            if ($v <= 25) {
                $f->poner('peso_pend', $v);
                $num = rtrim(rtrim(number_format($v, 1), '0'), '.');
                $this->dgDecir($hola . "¿Son {$num} libras o {$num} kilos?", 'unidad');
                return;
            }
            $lb = $v;
        }

        $f->poner('peso_lb', $lb);
        $this->dgPorPeso($lb, $hola);
    }

    private function dgPeso(string $texto, string $hola): void
    {
        // Le pedimos el peso, pero puede contestar con otra talla.
        if ($t = $this->dgTallas($texto)) { $this->dgConTalla($t[0], $hola); return; }
        if ($p = Entender::peso($texto)) { $this->dgConPeso($p, $hola); return; }
        if (Entender::esCortesia($texto)) return;
        $this->dgNoEntendi('peso', '¿Cuánto pesa su bebé? Por ejemplo: *25 libras* 😊');
    }

    private function dgUnidad(string $texto): void
    {
        $u = Entender::unidad($texto);
        $v = (float) $this->f->dato('peso_pend');
        if (! $u || $v <= 0) { $this->dgNoEntendi('unidad', '¿Son libras o kilos?'); return; }

        $lb = $u === 'kg' ? round($v * 2.2046, 1) : $v;
        $this->f->quitar('peso_pend');
        $this->f->poner('peso_lb', $lb);
        $this->dgPorPeso($lb, '');
    }

    /** Del peso a la talla: si la que le queda hay, las presentaciones; si no, sugerir. */
    private function dgPorPeso(float $lb, string $hola): void
    {
        $quedan = $this->dgTallasPorPeso($lb);
        $disp = array_map('mb_strtoupper', $this->tallasDisponibles());
        $hay = array_values(array_intersect($quedan, $disp));
        $v = rtrim(rtrim(number_format($lb, 1), '0'), '.');

        if ($quedan && $hay && $hay[count($hay) - 1] === $quedan[count($quedan) - 1]) {
            // La más grande que le queda (le dura más) y hay.
            $t = $hay[count($hay) - 1];
            $this->f->poner('tallas', [$t]);
            $this->dgMostrar($hola . "Con {$v} libras le queda la talla *" . $this->bonita($t) . "* 😊\n\n");
            return;
        }

        $this->dgSugerir($lb, $hola);
    }

    /** Las tallas que le quedan con ese peso, de chica a grande. */
    private function dgTallasPorPeso(float $lb): array
    {
        if ($lb <= (float) config('asistente.rn_hasta_kg', 4.5) * 2.2046) return ['RN'];

        $out = [];
        foreach (array_map('mb_strtoupper', (array) config('asistente.tallas', [])) as $t) {
            $r = $this->guionRangoLb($t);
            if ($r && $lb >= $r[0] && $lb <= $r[1]) $out[] = $t;
        }
        return $out;
    }

    /** No hay la que pidió (o la que le queda): se sugiere la vecina que sí hay. */
    private function dgSugerir(float $lb, string $antes): void
    {
        $disp = array_map('mb_strtoupper', $this->tallasDisponibles());
        $hay = array_values(array_intersect($this->dgTallasPorPeso($lb), $disp));
        $v = rtrim(rtrim(number_format($lb, 1), '0'), '.');

        if (! $hay) {
            $this->marcarAgotado();
            $this->dgDespedidaSinTalla($antes);
            return;
        }

        $t = $hay[count($hay) - 1];
        $this->f->poner('sugerida', $t);
        $this->dgDecir($antes . "Con {$v} libras le puede quedar la talla *" . $this->bonita($t) . '* 😊 ¿Le muestro las presentaciones de esa talla?', 'sugerencia');
    }

    private function dgSugerencia(string $texto): void
    {
        $f = $this->f;
        $si = Entender::siNo($texto);

        if ($si === true) {
            $f->poner('tallas', [(string) $f->dato('sugerida')]);
            $f->quitar('sugerida');
            $this->dgMostrar('');
            return;
        }
        if ($si === false) { $this->dgDespedidaSinTalla(''); return; }

        if ($t = $this->dgTallas($texto)) { $this->dgConTalla($t[0], ''); return; }
        $this->dgNoEntendi('sugerencia', '¿Le muestro las presentaciones de la talla *' . $this->bonita((string) $f->dato('sugerida')) . '*?');
    }

    /** NO acepta la sugerencia (o no hay nada que le quede). */
    private function dgDespedidaSinTalla(string $antes): void
    {
        $this->texto(trim($antes . 'Le damos las gracias por su consulta 😊 No dude en comunicarse con nosotros, estamos para servirle. '
            . 'Apenas tengamos la talla disponible, nos ponemos en contacto.'));
        $this->f->estado = 'terminado';
        $this->f->motivo = 'no hay su talla (avisarle cuando entre)';
        $this->f->paso = 'diagrama · sin talla';
    }

    // ════════════════════════════════════════════════════════════════════════
    // 2 · Presentaciones, dudas y elección
    // ════════════════════════════════════════════════════════════════════════

    private function dgMostrar(string $antes): void
    {
        $f = $this->f;
        $tallas = (array) $f->dato('tallas', []);
        $varias = count($tallas) > 1;
        $max = max(1, (int) config('asistente.max_fotos', 4)) * max(1, count($tallas));
        $enviadas = [];

        foreach ($tallas as $t) {
            $primeraDeTalla = true;
            foreach ($this->presentaciones([$t], 'ambos') as $s) {
                if (count($enviadas) >= $max) break 2;
                $nro = count($enviadas) + 1;

                // Arriba de la primera foto de cada talla: "Talla M 👇".
                $pre = '';
                if (! $enviadas) $pre = trim($antes);
                if ($varias && $primeraDeTalla) $pre = trim($pre . "\n\n" . '*Talla ' . $this->bonita($t) . '* 👇');

                $this->tarjeta($s, $nro, $pre);
                $enviadas[] = ['opcion' => $nro, 'id' => (string) $s->id];
                $primeraDeTalla = false;
            }
        }
        $f->poner('fotos_enviadas', $enviadas);

        // Después de las fotos, según lo que hay en esa talla:
        //  · de cinta Y de calzoncito → botones [Calzoncito] [Cinta]
        //  · solo de un tipo           → "Solo tenemos estas presentaciones de …"
        //    y si son varias, una lista para elegir cuál.
        $this->dgPreguntarDespuesDeFotos($this->guionOpcionesVistas());
    }

    private function dgPreguntarDespuesDeFotos(array $ops): void
    {
        $this->f->poner('dpaso', 'opciones');
        $this->f->paso = 'diagrama · opciones';

        $cintas = array_values(array_filter($ops, fn ($o) => $o['tipo'] === 'Cinta'));
        $calzon = array_values(array_filter($ops, fn ($o) => $o['tipo'] === 'Calzoncito'));

        if ($cintas && $calzon) {
            $this->botones('¿Cómo lo desea: de calzoncito o de cinta (de pegar a los lados)? 😊', [
                'tipo:calzoncito' => 'Calzoncito',
                'tipo:cinta'      => 'Cinta (de pegar)',
            ]);
            return;
        }

        $todas = $cintas ?: $calzon;
        $tipo = $cintas ? 'cinta (de pegar a los lados)' : 'calzoncito';

        if (count($todas) <= 1) { $this->texto('Solo tenemos esta presentación de ' . $tipo . ' 😊'); return; }

        $this->dgListaDeOpciones('Solo tenemos estas presentaciones de ' . $tipo . ' 😊 ¿Cuál desea?', $todas);
    }

    /** Una lista de WhatsApp para elegir la presentación (sin escribir). */
    private function dgListaDeOpciones(string $cuerpo, array $ops): void
    {
        $filas = [];
        foreach (array_slice($ops, 0, 10) as $o) {
            $id = $this->dgIdDeOpcion($o['opcion']);
            $s = $id ? ProductSize::with('product')->find($id) : null;
            if (! $s) continue;
            $corto = trim(preg_replace('/\s*\(.*$/u', '', $o['nombre']));
            // WhatsApp deja 24 letras en el título: va el nombre; el número de
            // opción, la talla, las unidades y el precio, abajo.
            $filas[] = [
                'id'      => 'op:' . $s->id,
                'titulo'  => mb_strlen($corto) > 24 ? rtrim(mb_substr($corto, 0, 23)) . '…' : $corto,
                'detalle' => mb_substr('Opción ' . $o['opcion'] . ' · Talla ' . trim((string) $s->size) . ($o['unidades'] ? ' · ' . $o['unidades'] . ' unidades' : '') . ' · $' . number_format($o['precio'], 2), 0, 72),
            ];
        }

        if (! $filas) { $this->texto($cuerpo); return; }
        $this->lista($cuerpo, 'Elegir', $filas);
    }

    /**
     * Varias del mismo tipo: ¿cuál desea? Nunca "¿de día o de noche?": la
     * clienta cree que la de noche solo se puede usar de noche (y al revés).
     */
    private function dgComoLasQuiere(array $ops): string
    {
        return '¿Cuál desea: ' . $this->unir(array_map(fn ($o) => 'la *opción ' . $o['opcion'] . '* (' . $o['nombre'] . ')', $ops), ' o ') . '?';
    }

    private function dgOpciones(string $texto): void
    {
        $f = $this->f;
        // Con varias tallas, cuenta solo lo de las que faltan elegir.
        $faltan = array_map('mb_strtoupper', $this->dgTallasSinElegir() ?: (array) $f->dato('tallas', []));
        $vistas = [];
        foreach ((array) $f->dato('fotos_enviadas', []) as $e) {
            $s = ProductSize::with('product')->find($e['id']);
            if ($s && in_array(mb_strtoupper(trim((string) $s->size)), $faltan, true)) {
                $vistas[] = ['id' => (string) $s->id, 'nombre' => trim((string) $s->product->name), 'unidades' => (int) $s->unidades, 'precio' => (float) $s->price];
            }
        }

        // ¿Eligió una? (por número, por nombre, o respondiendo a la foto)
        $id = $f->dato('citada') ?: Entender::opcion($texto, $vistas);

        // Hay una sola opción y dice "quiero 2", "sí", "lo quiero": es esa.
        if (! $id && count($vistas) === 1
            && (Entender::cantidad($texto) !== null || Entender::siNo($texto) === true || Entender::quiereComprar($texto)
                || preg_match('/^\s*(quiero|deme|mandeme|me manda|necesito|ocupo)\b/u', Entender::normalizar($texto)))) {
            $id = $vistas[0]['id'];
        }
        $f->quitar('citada');
        if ($id && ($elegida = ProductSize::with('product')->find($id))) {
            $this->dgElegir($elegida, Entender::cantidad($texto));
            return;
        }

        // Dijo el tipo o día/noche ("en calzoncito los quiero", "el de
        // noche"): si con eso queda UNA sola, esa es la que eligió.
        $idsVistas = array_column($vistas, 'id');
        $ops = array_values(array_filter($this->guionOpcionesVistas(), fn ($o) => in_array($this->dgIdDeOpcion($o['opcion']), $idsVistas, true)));
        $nn = Entender::normalizar($texto);
        // Pide OTRA talla ("y la talla M"): se suman sus fotos, sin perder las de antes.
        $nuevas = array_values(array_diff($this->dgTallas($texto), (array) $f->dato('tallas', [])));
        if ($nuevas) { $this->dgAgregarTallas($nuevas); return; }

        // El tipo que dijo antes ("de cinta") sigue valiendo cuando después
        // dice "de noche".
        $quiereNoche = (bool) preg_match('/\bnoche|nocturn|dormir/', $nn);
        $quiereDia   = (bool) preg_match('/\bde dia\b|\bdiario|para el dia/', $nn);
        $tipo = Entender::tipo($texto);
        if (in_array($tipo, ['cinta', 'calzoncito'], true)) $f->poner('dtipo', $tipo);
        elseif ($f->dato('dtipo') && ($quiereNoche || $quiereDia)) $tipo = (string) $f->dato('dtipo');
        if (in_array($tipo, ['cinta', 'calzoncito'], true) || $quiereNoche || $quiereDia) {
            $sirven = array_values(array_filter($ops, function ($o) use ($tipo, $quiereNoche, $quiereDia) {
                if (in_array($tipo, ['cinta', 'calzoncito'], true) && mb_strtolower($o['tipo']) !== $tipo) return false;
                if ($quiereNoche && ! $o['noche']) return false;
                if ($quiereDia && $o['noche']) return false;
                return true;
            }));
            // Si además dice que lo quiere ("quiero 2 de noche"), es una
            // elección. Si solo dice el tipo ("calzoncito"), se le reenvían
            // las fotos de ese tipo y nada más: todavía no eligió.
            $explicito = Entender::cantidad($texto) !== null || Entender::quiereComprar($texto)
                || (bool) preg_match('/^\s*(quiero|deme|mandeme|me manda|necesito|ocupo|lo quiero|los quiero)\b/u', $nn);

            if (count($sirven) === 1 && $explicito) {
                $s = ProductSize::with('product')->find($this->dgIdDeOpcion($sirven[0]['opcion']));
                if ($s) {
                    $this->dgElegir($s, Entender::cantidad($texto));
                    return;
                }
            }

            if ($sirven && count($sirven) < count($ops)) {
                $this->dgReenviar($sirven);
                return;
            }

            if (count($sirven) > 1) {
                $f->poner('dpaso', 'opciones');
                $this->dgListaDeOpciones('¿Cuál desea? 😊', $sirven);
                return;
            }

            if (! $sirven) {
                $que = trim((in_array($tipo, ['cinta', 'calzoncito'], true) ? $tipo : '') . ($quiereNoche ? ' de noche' : ($quiereDia ? ' de día' : '')));
                $this->dgDecir('En esa talla en este momento no tenemos ' . $que . ' 😔', 'opciones');
                return;
            }
        }

        // ¿Tiene dudas? → se le explica el propósito de cada una.
        $duda = Entender::tipo($texto) === 'diferencia' || $this->guionPideDiferencia($nn)
            || str_contains($texto, '?')
            || preg_match('/\b(cual|diferencia|para que|que es|como es|explique|expliqueme|duda)\b/', $nn);
        if ($duda) {
            $txt = $ops ? $this->guionDiferenciaReglas($ops) : '';
            if ($txt !== '') { $this->dgDecir($txt, 'opciones'); return; }
        }

        if (Entender::siNo($texto) === false || Entender::esCortesia($texto)) return;   // "no, gracias": sin insistir

        // Otra talla en vez de elegir.
        if ($t = $this->dgTallas($texto)) { $this->dgConTalla($t[0], ''); return; }

        $this->dgNoEntendi('opciones', '¿Cuál de las opciones le interesa? Puede decirme el número 😊');
    }

    /** Pide otra talla después de ver las fotos: se suman sus fotos, numeradas a continuación. */
    private function dgAgregarTallas(array $nuevas): void
    {
        $f = $this->f;
        $disp = array_map('mb_strtoupper', $this->tallasDisponibles());
        $hay = array_values(array_filter($nuevas, fn ($t) => in_array(mb_strtoupper($t), $disp, true)));
        $no  = array_values(array_diff($nuevas, $hay));

        $antes = '';
        if ($no) {
            $this->marcarAgotado();
            $antes = 'En este momento no tenemos talla ' . $this->unir(array_map(fn ($t) => $this->bonita($t), $no)) . ' 😔';
        }
        if (! $hay) { $this->dgDecir($antes, 'opciones'); return; }

        $f->poner('tallas', array_values(array_unique(array_merge((array) $f->dato('tallas', []), array_map('mb_strtoupper', $hay)))));
        $enviadas = (array) $f->dato('fotos_enviadas', []);

        foreach ($hay as $t) {
            $primera = true;
            foreach ($this->presentaciones([$t], 'ambos')->take(max(1, (int) config('asistente.max_fotos', 4))) as $s) {
                $nro = count($enviadas) + 1;
                $pre = $primera ? trim($antes . "\n\n*Talla " . $this->bonita($t) . '* 👇') : '';
                $this->tarjeta($s, $nro, $pre);
                $enviadas[] = ['opcion' => $nro, 'id' => (string) $s->id];
                $primera = false; $antes = '';
            }
        }
        $f->poner('fotos_enviadas', $enviadas);

        // La pregunta, solo con lo de la talla nueva.
        $nuevasUp = array_map('mb_strtoupper', $hay);
        $ops = array_values(array_filter($this->guionOpcionesVistas(), function ($o) use ($nuevasUp) {
            $s = ProductSize::with('product')->find($this->dgIdDeOpcion($o['opcion']));
            return $s && in_array(mb_strtoupper(trim((string) $s->size)), $nuevasUp, true);
        }));
        $this->dgPreguntarDespuesDeFotos($ops);
    }

    /** Eligió una presentación: al pedido, y sigue con la otra talla o el municipio. */
    private function dgElegir(ProductSize $s, ?int $cant): void
    {
        $f = $this->f;
        if ($cant && $cant > (int) config('asistente.max_paquetes', 10)) { $this->pasarAWil("pide {$cant} paquetes"); return; }

        $carrito = (array) $f->dato('carrito', []);
        $carrito = array_values(array_filter($carrito, fn ($c) => (string) $c['id'] !== (string) $s->id));
        $carrito[] = ['id' => (string) $s->id, 'cant' => $cant ?: 1, 'dicha' => $cant !== null];
        $f->poner('carrito', $carrito);
        $f->quitar('dtipo');

        $que = '*' . trim((string) $s->product->name) . '* talla ' . trim((string) $s->size);

        // Quedan tallas por elegir y no dijo cuántos de esta: se pregunta ya,
        // antes de pasar a la otra.
        if ($this->dgTallasSinElegir() && $cant === null) {
            $this->dgDecir('¡Con gusto! 😊 ¿Cuántos paquetes de ' . $que . ' le enviamos?', 'cantidad_item');
            return;
        }

        $this->dgDespuesDeElegir('¡Con gusto! 😊 ' . $que . ".\n\n");
    }

    /** La siguiente talla que pidió, o el municipio si ya no quedan. */
    private function dgDespuesDeElegir(string $antes): void
    {
        if ($faltan = $this->dgTallasSinElegir()) {
            $this->f->poner('dpaso', 'opciones');
            $ops = array_values(array_filter($this->guionOpcionesVistas(), function ($o) use ($faltan) {
                $s = ProductSize::with('product')->find($this->dgIdDeOpcion($o['opcion']));
                return $s && mb_strtoupper(trim((string) $s->size)) === mb_strtoupper($faltan[0]);
            }));
            $this->dgListaDeOpciones(trim($antes . '¿Y de la talla *' . $this->bonita($faltan[0]) . '*, cuál desea? 😊'), $ops);
            return;
        }

        $this->dgDecir($antes . 'Tenemos cobertura nacional con los envíos. ¿Desde cuál municipio nos escribe, disculpe?', 'municipio');
    }

    /** Las tallas que pidió y de las que todavía no eligió nada. */
    private function dgTallasSinElegir(): array
    {
        $elegidas = [];
        foreach ((array) $this->f->dato('carrito', []) as $c) {
            if ($s = ProductSize::with('product')->find($c['id'])) $elegidas[] = mb_strtoupper(trim((string) $s->size));
        }
        return array_values(array_filter((array) $this->f->dato('tallas', []), fn ($t) => ! in_array(mb_strtoupper($t), $elegidas, true)));
    }

    private function dgCantidadItem(string $texto): void
    {
        $cant = Entender::cantidad($texto) ?? (preg_match('/\b(\d{1,2})\b/u', $texto, $m) ? (int) $m[1] : null);
        $sin = $this->dgSinCantidad();
        if (! $cant || ! $sin) { $this->dgNoEntendi('cantidad_item', '¿Cuántos paquetes le enviamos?'); return; }
        if ($cant > (int) config('asistente.max_paquetes', 10)) { $this->pasarAWil("pide {$cant} paquetes"); return; }

        $this->dgPonerCantidad($sin['i'], $cant);
        $this->dgDespuesDeElegir('');
    }

    /** El último producto del pedido al que todavía no se le dijo cuántos. */
    private function dgSinCantidad(): ?array
    {
        $carrito = (array) $this->f->dato('carrito', []);
        for ($i = count($carrito) - 1; $i >= 0; $i--) {
            if (empty($carrito[$i]['dicha'])) {
                $s = ProductSize::with('product')->find($carrito[$i]['id']);
                return ['i' => $i, 'que' => $s ? trim((string) $s->product->name) . ' talla ' . trim((string) $s->size) : 'ese producto'];
            }
        }
        return null;
    }

    private function dgPonerCantidad(int $i, int $cant): void
    {
        $carrito = (array) $this->f->dato('carrito', []);
        if (! isset($carrito[$i])) return;
        $carrito[$i]['cant'] = $cant;
        $carrito[$i]['dicha'] = true;
        $this->f->poner('carrito', $carrito);
    }

    /** Reenvía solo las fotos de lo que pidió ("calzoncito"), numeradas de nuevo. */
    private function dgReenviar(array $sirven): void
    {
        $ids = [];
        foreach ($sirven as $o) if ($id = $this->dgIdDeOpcion($o['opcion'])) $ids[] = $id;

        $enviadas = [];
        foreach ($ids as $id) {
            $s = ProductSize::with('product')->find($id);
            if (! $s) continue;
            $nro = count($enviadas) + 1;
            $this->tarjeta($s, $nro);
            $enviadas[] = ['opcion' => $nro, 'id' => (string) $s->id];
        }
        $this->f->poner('fotos_enviadas', $enviadas);

        $this->dgPreguntarDespuesDeFotos($this->guionOpcionesVistas());
    }

    private function dgIdDeOpcion(int $nro): ?string
    {
        foreach ((array) $this->f->dato('fotos_enviadas', []) as $e) {
            if ((int) $e['opcion'] === $nro) return (string) $e['id'];
        }
        return null;
    }

    // ════════════════════════════════════════════════════════════════════════
    // 3 · Municipio y envío
    // ════════════════════════════════════════════════════════════════════════

    private function dgMunicipio(string $texto): void
    {
        $f = $this->f;
        $m = Municipios::buscarEn($texto);

        if (! $m) {
            $this->dgNoEntendi('municipio', 'No encontré ese municipio 🙏 ¿Me lo escribe de nuevo? Por ejemplo: *Soyapango*, *Santa Tecla*');
            return;
        }

        $this->ponerMunicipio($m);
        if (! $f->dato('departamento')) {
            $this->dgDecir('Hay un ' . $f->dato('municipio') . ' en varios departamentos. ¿Cuál es el suyo: ' . $this->unir((array) $f->dato('deptos', []), ' o ') . '?', 'depto');
            return;
        }
        $this->dgDespuesDelMunicipio();
    }

    private function dgDepto(string $texto): void
    {
        foreach ((array) $this->f->dato('deptos', []) as $d) {
            if (str_contains(Municipios::normalizar($texto), Municipios::normalizar($d))) {
                $this->ponerDepartamento($d);
                $this->dgDespuesDelMunicipio();
                return;
            }
        }
        $this->dgNoEntendi('depto', '¿De qué departamento: ' . $this->unir((array) $this->f->dato('deptos', []), ' o ') . '?');
    }

    private function dgDespuesDelMunicipio(): void
    {
        if ($this->entregaPropia()) {
            $this->dgDecir('¿De qué colonia nos escribe, para confirmarle el costo del envío? 🚚', 'colonia');
            return;
        }

        $envio = (float) \App\Models\Setting::envio();
        $this->f->poner('envio', $envio);

        // Tu leyenda del envío, la de config/asistente.php ("¡El envío más
        // barato del mercado!…"), con el monto de Configuración.
        $leyenda = '';
        foreach ((array) config('asistente.respuestas', []) as $r) {
            if (str_contains((string) ($r['texto'] ?? ''), '{envio}')) { $leyenda = $this->rellenar((string) $r['texto']); break; }
        }
        if ($leyenda === '') $leyenda = '🚚 Envío: $' . number_format($envio, 2) . ', hasta su casa o lugar de trabajo.';

        $this->f->poner('dpaso', 'envio');
        $this->f->paso = 'diagrama · envio';
        $this->botones($leyenda . "\n\n¿Le parece bien? 😊", ['envio:si' => 'Sí, me parece bien', 'envio:no' => 'No, gracias']);
    }

    private function dgColonia(string $texto): void
    {
        if (mb_strlen(trim($texto)) < 3 || Entender::esCortesia($texto)) return;
        $this->f->poner('colonia', mb_substr(trim($texto), 0, 200));
        $this->despedidaWil = trim((string) config('asistente.entrega_propia.cierre'));
        $this->pasarAWil('envío en ' . $this->f->dato('municipio') . ': ' . mb_substr(trim($texto), 0, 120));
    }

    private function dgEnvio(string $texto): void
    {
        $si = Entender::siNo($texto);

        if ($si === false) {
            // NO → no hacer nada. Se apaga en este chat, sin decirle nada.
            $this->pasarAWil('no aceptó el envío', false);
            return;
        }

        if ($si === true) {
            $this->dgPedirDatos('¡Excelente! 😊 ');
            return;
        }

        $this->dgNoEntendi('envio', '¿Le parece bien el envío? 😊');
    }

    // ════════════════════════════════════════════════════════════════════════
    // 4 · Cantidad, nombre y dirección → la orden
    // ════════════════════════════════════════════════════════════════════════

    private function dgPedirDatos(string $antes): void
    {
        $f = $this->f;
        $falta = [];
        if ($sin = $this->dgSinCantidad()) $falta[] = '• ¿Cuántos paquetes' . (count((array) $f->dato('carrito', [])) > 1 ? ' de *' . $sin['que'] . '*' : '') . ' le enviamos?';
        if (! $f->dato('nombre'))         $falta[] = '• Nombre completo';
        if (! $f->dato('direccion'))      $falta[] = '• Dirección exacta (colonia, calle o pasaje, número de casa y un punto de referencia)';

        if (! $falta) { $this->dgDespuesDeDatos(); return; }

        $this->dgDecir($antes . "Para enviarlo me regala por favor:\n\n" . implode("\n", $falta), 'datos');
    }

    private function dgDatos(string $texto): void
    {
        $f = $this->f;
        $leyo = false;

        // Cuántos: "2", "2 paquetes", o un número al principio ("2, Ana Pérez…").
        $cant = Entender::cantidad($texto) ?? (preg_match('/^\s*(\d{1,2})\b/u', $texto, $m) ? (int) $m[1] : null);
        if ($cant && ($sin = $this->dgSinCantidad())) {
            if ($cant > (int) config('asistente.max_paquetes', 10)) { $this->pasarAWil("pide {$cant} paquetes"); return; }
            $this->dgPonerCantidad($sin['i'], $cant);
            $leyo = true;
        }

        // Nombre y dirección: "Ana Pérez, Col. Escalón pasaje 2 casa 5" o en renglones.
        $sinNumero = trim(preg_replace('/^\s*\d{1,2}\s*(paquetes?)?\s*[,.\-]?\s*/u', '', $texto));
        foreach (preg_split('/\n+/', $sinNumero) as $renglon) {
            $partes = array_map('trim', explode(',', $renglon, 2));
            if (! $f->dato('nombre') && ($nom = Entender::pareceNombre($partes[0]))) {
                $f->poner('nombre', $nom);
                $leyo = true;
                if (count($partes) === 2 && ! $f->dato('direccion') && Entender::pareceDireccion($partes[1])) {
                    $f->poner('direccion', $partes[1]);
                }
            } elseif (! $f->dato('direccion') && Entender::pareceDireccion($renglon)) {
                $f->poner('direccion', trim($renglon));
                $leyo = true;
            }
        }

        // Si las reglas no alcanzaron, la IA solo para separar nombre y dirección.
        if (! $f->dato('nombre') || ! $f->dato('direccion')) {
            try {
                $r = Vendedora::leer([
                    'catalogo' => [], 'datos' => [], 'tallas' => [], 'estado' => ['ultima_pregunta' => 'Nombre completo y dirección exacta'],
                    'charla' => [], 'nuevo' => $texto, 'ejemplos' => [], 'permitidos' => [],
                ]);
                if ($r) {
                    if (! $f->dato('nombre') && $r['nombre'])       { $f->poner('nombre', $r['nombre']); $leyo = true; }
                    if (! $f->dato('direccion') && $r['direccion']) { $f->poner('direccion', $r['direccion']); $leyo = true; }
                }
            } catch (\Throwable $e) {
            }
        }

        if (! $leyo) {
            if (Entender::esCortesia($texto)) return;
            $this->dgNoEntendi('datos', "Para enviarlo me regala por favor su nombre completo y la dirección exacta 🙏");
            return;
        }

        $this->dgPedirDatos('');
    }

    private function dgDespuesDeDatos(): void
    {
        $f = $this->f;

        if (! $f->dato('telefono')) {
            if ($this->conv->esExtranjero()) {
                $this->dgDecir('¿A qué número de El Salvador le llamamos a quien recibe, para coordinar la entrega? 📞', 'telefono');
                return;
            }
            $f->poner('telefono', $this->conv->telefonoLegible());
        }

        $nombre = trim(strtok((string) $f->dato('nombre'), ' ') ?: '');
        $this->f->poner('orden_mostrada', true);
        $this->f->poner('dpaso', 'orden');
        $this->f->paso = 'diagrama · orden';

        $todo = '¡Gracias' . ($nombre !== '' ? ', ' . $nombre : '') . "! 😊\n\n" . $this->textoOrden()
            . "\n\n¿Toda la información es correcta? Así evitamos errores 🙏";
        $botones = ['conf:si' => 'Sí, es correcta', 'conf:corregir' => 'Corregir'];

        if (mb_strlen($todo) <= 1020) { $this->botones($todo, $botones); return; }
        $this->texto($this->textoOrden());
        $this->botones('¿Toda la información es correcta? Así evitamos errores 🙏', $botones);
    }

    private function dgTelefono(string $texto): void
    {
        $tel = Entender::telefonoSV($texto);
        if (! $tel) { $this->dgNoEntendi('telefono', '¿A qué número de El Salvador le llamamos? (8 dígitos) 📞'); return; }
        $this->f->poner('telefono', $tel);
        $this->dgDespuesDeDatos();
    }

    private function dgOrden(string $texto, ?string $boton): void
    {
        if ($boton === 'conf:si' || ($boton === null && Entender::siNo($texto) === true)) {
            $this->f->poner('confirmado', true);
            $this->terminar();
            return;
        }

        if ($boton === 'conf:corregir' || Entender::siNo($texto) === false || preg_match('/\b(corregir|cambiar|esta mal|no es|equivocad)/', Entender::normalizar($texto))) {
            $this->despedidaWil = 'Con gusto 😊 En un momento le ayudamos a corregirlo.';
            $this->pasarAWil('quiere corregir la orden');
            return;
        }

        if (Entender::esCortesia($texto)) return;
        $this->dgNoEntendi('orden', '¿Toda la información de la orden es correcta? 🙏');
    }
}
