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
        if ($tallas) { $this->dgConTalla($tallas[0], $hola); return; }

        if ($p = Entender::peso($texto)) { $this->dgConPeso($p, $hola); return; }

        // Primer mensaje (saludo, "información", "¿tiene pampers?"): se pide la talla.
        if ($hola !== '' || Entender::esSaludo($texto) || ! $this->f->dato('tallas_vistas')) { $this->dgPedirTalla($hola); return; }

        if (Entender::esCortesia($texto)) return;
        $this->dgNoEntendi('talla', '¿Qué talla usa su bebé o cuánto pesa? 😊');
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

        if ($f->dato('peso_lb') !== null) {
            $this->dgSugerir((float) $f->dato('peso_lb'), $hola . 'En este momento no tenemos talla ' . $this->bonita($talla) . ' 😔 ');
            return;
        }

        $this->dgDecir($hola . 'En este momento no tenemos talla ' . $this->bonita($talla) . ' 😔 ¿Cuánto pesa su bebé? Así le sugiero la talla que le puede quedar.', 'peso');
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
        $opciones = $this->presentaciones((array) $f->dato('tallas', []), 'ambos');
        $enviadas = [];

        $primera = true;
        foreach ($opciones->take(max(1, (int) config('asistente.max_fotos', 4))) as $s) {
            $nro = count($enviadas) + 1;
            $this->tarjeta($s, $nro, $primera ? trim($antes) : '');
            $enviadas[] = ['opcion' => $nro, 'id' => (string) $s->id];
            $primera = false;
        }
        $f->poner('fotos_enviadas', $enviadas);

        $this->dgDecir('¿Tiene alguna duda sobre las opciones? 😊', 'opciones');
    }

    private function dgOpciones(string $texto): void
    {
        $f = $this->f;
        $vistas = [];
        foreach ((array) $f->dato('fotos_enviadas', []) as $e) {
            $s = ProductSize::with('product')->find($e['id']);
            if ($s) $vistas[] = ['id' => (string) $s->id, 'nombre' => trim((string) $s->product->name), 'unidades' => (int) $s->unidades, 'precio' => (float) $s->price];
        }

        // ¿Eligió una? (por número, por nombre, o respondiendo a la foto)
        $id = $f->dato('citada') ?: Entender::opcion($texto, $vistas);
        $f->quitar('citada');
        if ($id) {
            $f->poner('carrito', [['id' => (string) $id, 'cant' => Entender::cantidad($texto) ?: 1]]);
            $f->poner('cantidad_dicha', Entender::cantidad($texto) !== null);
            $this->dgDecir('¡Con gusto! 😊 Tenemos cobertura nacional con los envíos. ¿Desde cuál municipio nos escribe, disculpe?', 'municipio');
            return;
        }

        // ¿Tiene dudas? → se le explica el propósito de cada una.
        $duda = Entender::tipo($texto) === 'diferencia' || $this->guionPideDiferencia($texto === '' ? '' : Entender::normalizar($texto))
            || str_contains($texto, '?') || Entender::siNo($texto) === true
            || preg_match('/\b(cual|diferencia|para que|que es|como es|explique|expliqueme|duda)\b/', Entender::normalizar($texto));
        if ($duda) {
            $ops = $this->guionOpcionesVistas();
            $txt = $ops ? $this->guionDiferenciaReglas($ops) : '';
            if ($txt !== '') { $this->dgDecir($txt, 'opciones'); return; }
        }

        if (Entender::siNo($texto) === false || Entender::esCortesia($texto)) return;   // "no, gracias": sin insistir

        // Otra talla en vez de elegir.
        if ($t = $this->dgTallas($texto)) { $this->dgConTalla($t[0], ''); return; }

        $this->dgNoEntendi('opciones', '¿Cuál de las opciones le interesa? Puede decirme el número 😊');
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

        $this->dgDecir($leyenda . "\n\n¿Le parece bien? 😊", 'envio');
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
        if (! $f->dato('cantidad_dicha')) $falta[] = '• ¿Cuántos paquetes le enviamos?';
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
        if ($cant && ! $f->dato('cantidad_dicha')) {
            if ($cant > (int) config('asistente.max_paquetes', 10)) { $this->pasarAWil("pide {$cant} paquetes"); return; }
            $c = (array) $f->dato('carrito', []);
            if ($c) { $c[0]['cant'] = $cant; $f->poner('carrito', $c); }
            $f->poner('cantidad_dicha', true);
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
