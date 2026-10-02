<?php

namespace App\Services\Asistente;

/**
 * Lee lo que escribió el cliente. Solo reglas, nada de IA: así nunca inventa
 * una talla ni un número, y lo que no entiende lo dice (devuelve null).
 *
 * No depende de Laravel ni de la base: se puede probar suelto.
 */
class Entender
{
    /** Minúsculas, sin tildes, sin signos, espacios simples. */
    public static function normalizar(?string $t): string
    {
        $t = mb_strtolower(trim((string) $t));
        $t = strtr($t, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n']);
        // El punto y la coma se quedan entre dígitos ("10.5 kilos").
        $t = preg_replace('/(?<=\d)[.,](?=\d)/u', '#', $t);
        $t = preg_replace('/[^a-z0-9#$\s]/u', ' ', $t);
        $t = str_replace('#', '.', $t);

        return trim(preg_replace('/\s+/u', ' ', $t));
    }

    private static function palabras(string $n): int
    {
        return $n === '' ? 0 : count(explode(' ', $n));
    }

    // ── Pasar a Wil ──────────────────────────────────────────────────────────

    /**
     * Lo que el asistente no debe contestar nunca. Devuelve el motivo, o null.
     */
    public static function motivoParaWil(?string $texto): ?string
    {
        $n = static::normalizar($texto);
        if ($n === '') return null;

        // El mensaje que trae escrito el anuncio de Facebook ("Me gustaría
        // obtener más información. ¿Puedo hablar con alguien?") NO es pedir
        // una persona: es como empiezan 1 de cada 4 chats. Se lee como saludo.
        if (static::esDelAnuncio($n)) return null;

        $reglas = [
            'pidió hablar con una persona' => '/\b(asesor|asesora|persona|humano|agente|encargad[oa]|vendedor[a]?|hablar con alguien|me atiende alguien|atiendame|llamenme|llameme|me pueden llamar|me puede llamar)\b/',
            'queja o reclamo'              => '/\b(queja|reclamo|estafa|estafador|mal servicio|pesimo|no me ha llegado|no me llego|no ha llegado|no llego|devolucion|devolver|reembolso|venia malo|vino malo|vinieron malos|incompleto)\b/',
            'pregunta por un pedido que ya hizo' => '/\b(donde (esta|va|viene) mi (pedido|paquete)|ya lo enviaron|ya lo mandaron|numero de guia|rastreo|ya hice (mi|el) pedido|ya pedi)\b/',
            'salud del bebé'               => '/\b(rozadura|rozaduras|rosadura|rosaduras|alergia|alergico|alergica|irritacion|irritado|irritada|sarpullido|roncha|ronchas|sangre|herida|hongo|hongos|dermatitis|pediatra|infeccion|quemadura|paspado|paspadura)\b/',
            'pidió rebaja o precio especial' => '/\b(rebaja|rebajita|descuento|mas barato|mas baratos|por mayor|mayoreo|al mayor|precio especial|credito|fiado|a plazos|factura|credito fiscal|ccf)\b/',
            'pregunta por pañal de adulto'  => '/\b(adulto|adultos|adulta|adultas|para (el|la|mi) (abuel[oa]|senor[a]?|mama|papa|esposo|esposa)|anciano|anciana)\b/',
            'pregunta por otro producto'    => '/\b(toallitas|toallas humedas|toalla humeda|wipes|biberon|biberones|viberon|viveron|biveron|pacha|pachas|chupon|chupete|toallas sanitarias|toalla sanitaria)\b/',
            'mensaje ofensivo'             => '/\b(puta|puto|mierda|pendej[oa]|idiota|estupid[oa]|maldit[oa]|hijo de|cerot[oa]|ladron|ladrones)\b/',
        ];

        foreach ($reglas as $motivo => $re) {
            if (preg_match($re, $n)) return $motivo;
        }

        return null;
    }

    // ── Talla ────────────────────────────────────────────────────────────────

    /**
     * Las tallas que nombra, en el orden de la lista. [] si ninguna.
     *
     * Las letras sueltas ("m", "l", "g") solo cuentan si dice "talla" antes o
     * si el mensaje es cortito: "la l" dentro de una frase larga es cualquier
     * cosa.
     */
    public static function tallas(?string $texto, bool $estricto = false): array
    {
        $n = ' ' . static::normalizar($texto) . ' ';
        if (trim($n) === '') return [];

        // Estricto: a mitad del pedido, "la grande" es el paquete grande, no la
        // talla L. Ahí solo cuenta lo que no se presta a confusión.
        $corto = ! $estricto && static::palabras(trim($n)) <= 3;

        // Se busca de la más específica a la más general y se borra lo que ya
        // se reconoció: si no, "extra grande" también sería "grande" (L).
        // Las marcadas con * no valen en modo estricto.
        $reglas = [
            'RN'   => ['/\b(rn|nb|newborn)\b/', '/\brecien nacid[oa]s?\b/', '/\btalla (rn|0|cero)\b/'],
            '8 A 14 AÑOS' => ['/\b8\s*(a|al|-)?\s*1[45]\b/', '/\bocho a (catorce|quince)\b/', '/\btalla especial\b/'],
            '4 A 7 AÑOS'  => ['/\b4\s*(a|al|-)?\s*7\b(?!\s*(\d|dias|horas))/', '/\bcuatro a siete\b/'],
            'XXXL' => ['/\b(xxxl|3xl|xxxg|3xg)\b/', '/\btriple extra ?grande\b/'],
            'XXL'  => ['/\b(xxl|2xl|xxg|2xg)\b/', '/\b(doble|extra) extra ?grande\b/'],
            'XL'   => ['/\b(xl|xg|exel|equis ele)\b/', '/\bextra ?grande\b/'],
            'L'    => ['/\btalla (l|g|grande)\b/', '*/\bla (l|g)\b/'],
            'M'    => ['/\btalla (m|mediana|mediano)\b/', '*/\b(mediana|mediano)\b/', '*/\bla m\b/'],
            'S'    => ['/\btalla (s|p|pequena|pequeno|chiquita|chica)\b/', '*/\bla s\b/', '*/\b(pequena|pequeno|chiquita)\b/'],
        ];

        // Sueltas, solo en mensajes cortos: "m", "l", "g", "grande".
        $sueltas = ['L' => '/\b(l|g|grande)\b/', 'M' => '/\bm\b/', 'S' => '/\bs\b/'];

        $pos = [];
        foreach ($reglas as $talla => $res) {
            foreach ($res as $re) {
                if ($re[0] === '*') {
                    if ($estricto) continue;
                    $re = substr($re, 1);
                }
                if (preg_match($re, $n, $m, PREG_OFFSET_CAPTURE)) {
                    $pos[$talla] = $m[0][1];
                    $n = substr_replace($n, str_repeat(' ', strlen($m[0][0])), $m[0][1], strlen($m[0][0]));
                    break;
                }
            }
        }

        if ($corto) {
            foreach ($sueltas as $talla => $re) {
                if (isset($pos[$talla])) continue;
                if (preg_match($re, $n, $m, PREG_OFFSET_CAPTURE)) $pos[$talla] = $m[0][1];
            }
        }

        // En el orden en que las escribió: "la M o la L" → M, L.
        asort($pos);

        return array_keys($pos);
    }

    /**
     * "Talla 3", "talla 6 de Pampers": la numeración de otra marca. No se
     * traduce (cada marca mide distinto); se pregunta el peso.
     */
    public static function tallaNumerica(?string $texto): bool
    {
        $n = static::normalizar($texto);

        return (bool) preg_match('/\b(talla|tallita|numero|etapa)\s*[0-9]\b(?!\s*(a|al|-)?\s*\d)/', $n)
            || (bool) preg_match('/\bpampers?\b.*\b[1-8]\b(?!\s*(a|al|-)?\s*\d)/', $n);
    }

    /** El número de "talla 6", "talla 3 de Pampers"; null si no dice uno. */
    public static function numeroDeTalla(?string $texto): ?int
    {
        $n = static::normalizar($texto);

        if (preg_match('/\b(talla|tallita|numero|etapa)\s*([0-9])\b(?!\s*(a|al|-)?\s*\d)/', $n, $m)) return (int) $m[2];
        if (preg_match('/\bpampers?\b.*?\b([0-8])\b(?!\s*(a|al|-)?\s*\d)/', $n, $m)) return (int) $m[1];

        return null;
    }

    /** "¿Qué precio tiene?", "¿cuánto cuesta?", "¿cuántos trae?". */
    public static function preguntaPrecio(?string $texto): bool
    {
        $n = static::normalizar($texto);

        if (preg_match('/\benvio\b/', $n)) return false;   // eso lo contesta la respuesta del envío

        return (bool) preg_match('/\b(precio|precios|(q|que) (cuesta|cuestan|vale|valen)|cuanto (cuesta|cuestan|vale|valen|sale|salen|es|son|esta|estan|cobra)|a como|a cuanto|que valor|cuantos? (trae|traen|vienen|viene|unidades|panales|pamper|pampers)|cuantas unidades|de cuantos?)\b/', $n);
    }

    // ── Peso ─────────────────────────────────────────────────────────────────

    /**
     * El peso que dice, en kilos. Devuelve:
     *   ['kg' => 10.0]                 si dijo la unidad
     *   ['valor' => 22.0, 'kg' => null] si dijo un número sin unidad
     *   null                            si no dijo peso
     */
    public static function peso(?string $texto): ?array
    {
        $n = static::normalizar($texto);
        if ($n === '') return null;

        $num = '(\d{1,3}(?:\.\d{1,2})?)';

        if (preg_match('/' . $num . '\s*(libras?|lbs?|lb|lbr|lbrs|librs)\b/', $n, $m)) {
            return ['kg' => round((float) $m[1] * 0.4536, 2), 'dicho' => $m[1] . ' lb'];
        }

        if (preg_match('/' . $num . '\s*(kilos?|kgs?|kilogramos?|k)\b/', $n, $m)) {
            return ['kg' => (float) $m[1], 'dicho' => $m[1] . ' kg'];
        }

        // "libra y media" no; "pesa 22", "22" a secas cuando se le preguntó.
        if (preg_match('/\b(pesa|peso|pesando|pesa como|pesa unos|pesa unas)\s+(como\s+|unos\s+|unas\s+|mas o menos\s+)?' . $num . '\b/', $n, $m)) {
            return ['valor' => (float) $m[3], 'kg' => null];
        }

        if (preg_match('/^' . $num . '$/', $n, $m)) {
            return ['valor' => (float) $m[1], 'kg' => null];
        }

        return null;
    }

    /** "libras" / "kilos" como respuesta a la pregunta de la unidad. */
    public static function unidad(?string $texto): ?string
    {
        $n = static::normalizar($texto);
        if (preg_match('/\b(libras?|lbs?|lb)\b/', $n)) return 'lb';
        if (preg_match('/\b(kilos?|kgs?|kilogramos?)\b/', $n)) return 'kg';
        return null;
    }

    /** ¿Dijo la edad y no el peso? "tiene 8 meses". */
    public static function diceEdad(?string $texto): bool
    {
        $n = static::normalizar($texto);
        if (preg_match('/\b\d+\s*(a|-)\s*\d+\s*anos\b/', $n)) return false;   // es una talla de niño

        return (bool) preg_match('/\b(\d+|un|una|dos|tres|cuatro|cinco|seis|siete|ocho|nueve|diez|once)\s*(mes|meses|ano|anos|semana|semanas|anito|anitos|mesecito|mesecitos)\b/', $n)
            || (bool) preg_match('/\b(recien nacid[oa]|nacio|va a nacer|embarazada)\b/', $n);
    }

    // ── Tipo ─────────────────────────────────────────────────────────────────

    /** 'cinta', 'calzoncito', 'ambos', 'diferencia' o null. */
    public static function tipo(?string $texto): ?string
    {
        $n = static::normalizar($texto);
        if ($n === '') return null;

        if (preg_match('/\b(diferencias?|cual es mejor|cual me recomienda|cual recomienda|que diferencia|como son|en que se diferencian|que tienen de diferente|cual conviene)\b/', $n)) return 'diferencia';

        $calzon = (bool) preg_match('/\b(calzoncitos?|calzon|calzones|pants?|pantis?|de subir|tipo calzon|braga|bragas|training)\b/', $n);
        $cinta  = (bool) preg_match('/\b(cintas?|de pegar|pegar|broches?|de broche|pegadit[oa]s?|adhesiv[oa]s?|velcro|normal|normales|tradicional|de los normales)\b/', $n);

        if ($calzon && $cinta) return 'ambos';
        if ($calzon) return 'calzoncito';
        if ($cinta)  return 'cinta';

        if (preg_match('/\b(los dos|las dos|ambos|ambas|cualquiera|los 2|las 2|no se|me da igual)\b/', $n)) return 'ambos';

        return null;
    }

    // ── Sí / no y números ────────────────────────────────────────────────────

    /** true = sí, false = no, null = ni una cosa ni otra. */
    public static function siNo(?string $texto): ?bool
    {
        $n = static::normalizar($texto);
        if ($n === '') return null;

        if (preg_match('/^(no|nop|nel|no gracias|todavia no|aun no|mejor no|no por ahora|nada)\b/', $n)) return false;

        if (preg_match('/^(si|sii+|sip|simon|ok|okay|oki|va|vaya|valla|baya|dale|claro|correcto|esta bien|asi esta bien|perfecto|listo|de acuerdo|confirmo|confirmado|exacto|eso|ese|esa|sale|bueno|confirmar|confirmar pedido|si lo quiero|lo quiero|esta correcto|todo bien|todo correcto)\b/', $n)) return true;

        return null;
    }

    private const NUMEROS = [
        'un' => 1, 'uno' => 1, 'una' => 1, 'dos' => 2, 'tres' => 3, 'cuatro' => 4, 'cinco' => 5,
        'seis' => 6, 'siete' => 7, 'ocho' => 8, 'nueve' => 9, 'diez' => 10, 'once' => 11, 'doce' => 12,
        'quince' => 15, 'veinte' => 20,
    ];

    /** Cuántos paquetes: "2", "dos", "2 paquetes", "unos 3". null si no dice. */
    public static function cantidad(?string $texto, bool $soloConPalabra = false): ?int
    {
        $n = static::normalizar($texto);
        if ($n === '') return null;

        $pal = implode('|', array_keys(self::NUMEROS));
        $unidad = '(paquetes?|paquetitos?|bolsas?|pacas?|fardos?|cajas?|packs?|de esos|de esas|de ese|de esa)';

        if (preg_match('/\b(\d{1,3}|' . $pal . ')\s+' . $unidad . '\b/', $n, $m)) {
            return static::aNumero($m[1]);
        }

        if ($soloConPalabra) return null;

        // Un número solo (o casi): "2", "dos", "quiero 3", "mandeme 2".
        if (preg_match('/^(quiero|deme|mandeme|me da|me manda|serian|seria|solo|unos|unas|nada mas)?\s*(\d{1,3}|' . $pal . ')(\s+(por favor|porfa|nada mas|mas))?$/', $n, $m)) {
            return static::aNumero($m[2]);
        }

        return null;
    }

    private static function aNumero(string $s): int
    {
        return preg_match('/^\d+$/', $s) ? (int) $s : (self::NUMEROS[$s] ?? 0);
    }

    /**
     * Cuál de las opciones mostradas eligió.
     *
     * $opciones = [['id' => …, 'nombre' => …, 'unidades' => 40, 'precio' => 12.5], …]
     * en el mismo orden en que se mostraron. Devuelve el id, o null si no se
     * puede saber sin adivinar.
     */
    public static function opcion(?string $texto, array $opciones): ?string
    {
        $n = static::normalizar($texto);
        if ($n === '' || ! $opciones) return null;

        $opciones = array_values($opciones);
        $total = count($opciones);

        // "la 2", "opción 3", "el número 1", "#2"
        if (preg_match('/\b(opcion|numero|la|el|#)\s*(\d{1,2})\b(?!\s*(paquetes?|bolsas?|unidades|u\b|panales|libras|kilos))/', $n, $m)) {
            $i = (int) $m[2];
            if ($i >= 1 && $i <= $total) return (string) $opciones[$i - 1]['id'];
        }

        // Un número solo es la opción, si hay tantas.
        if (preg_match('/^(\d{1,2})$/', $n, $m)) {
            $i = (int) $m[1];
            if ($i >= 1 && $i <= $total) return (string) $opciones[$i - 1]['id'];
        }

        $ordinales = ['primer' => 1, 'primero' => 1, 'primera' => 1, 'segundo' => 2, 'segunda' => 2,
                      'tercer' => 3, 'tercero' => 3, 'tercera' => 3, 'cuarto' => 4, 'cuarta' => 4,
                      'quinto' => 5, 'quinta' => 5, 'ultimo' => $total, 'ultima' => $total];
        foreach ($ordinales as $p => $i) {
            if (preg_match('/\b' . $p . '\b/', $n) && $i >= 1 && $i <= $total) {
                return (string) $opciones[$i - 1]['id'];
            }
        }

        // "la más barata", "la que trae más".
        if (preg_match('/\b(mas barat[oa]|mas economic[oa]|menor precio)\b/', $n)) {
            usort($opciones, fn ($a, $b) => $a['precio'] <=> $b['precio']);
            return (string) $opciones[0]['id'];
        }
        if (preg_match('/\b(trae mas|mas unidades|la mas grande|el mas grande|la grande|mas cantidad)\b/', $n)) {
            usort($opciones, fn ($a, $b) => $b['unidades'] <=> $a['unidades']);
            return (string) $opciones[0]['id'];
        }

        // "la de 40" / "la de 40 unidades" / "la de $12": por unidades o precio,
        // solo si calza con UNA.
        if (preg_match_all('/\$?\s*(\d{1,3}(?:\.\d{1,2})?)/', $n, $mm)) {
            $cand = [];
            foreach ($mm[1] as $num) {
                $v = (float) $num;
                foreach ($opciones as $o) {
                    if ((int) $o['unidades'] > 0 && (int) $o['unidades'] === (int) $v && floor($v) == $v) $cand[$o['id']] = true;
                    if (abs((float) $o['precio'] - $v) < 0.01) $cand[$o['id']] = true;
                }
            }
            if (count($cand) === 1) return (string) array_key_first($cand);
        }

        // Por el nombre: una palabra del nombre que no tengan las demás ("magic",
        // "noche").
        $tokens = explode(' ', $n);
        $cand = [];
        foreach ($opciones as $o) {
            foreach (explode(' ', static::normalizar($o['nombre'])) as $w) {
                if (mb_strlen($w) < 4) continue;
                if (in_array($w, ['panal', 'panales', 'calzoncito', 'calzoncitos', 'talla', 'pack', 'paquete', 'bebe'], true)) continue;
                if (in_array($w, $tokens, true)) $cand[$o['id']] = true;
            }
        }
        if (count($cand) === 1) return (string) array_key_first($cand);

        // "ese", "esa", "si" con una sola opción en pantalla.
        if ($total === 1 && preg_match('/^(si |ok |)?(ese|esa|eso|este|esta|si|lo quiero|la quiero|los quiero|me gusta|me interesa|ese mismo|esa misma|ese quiero|esa quiero|quiero ese|quiero esa|seria ese|seria esa)( (por favor|porfa|gracias|mismo|misma))?$/', $n)) {
            return (string) $opciones[0]['id'];
        }

        return null;
    }

    /**
     * Varias en un mismo mensaje: "2 de noche y 1 magic", "el de noche y el
     * magic". Devuelve [[id, cantidad], …] si reconoce dos o más distintas;
     * si no, null (y se lee como una sola). Sin cantidad, va 1.
     */
    public static function varias(?string $texto, array $opciones): ?array
    {
        $n = static::normalizar($texto);
        if ($n === '' || count($opciones) < 2) return null;

        $pal = implode('|', array_keys(self::NUMEROS));

        // La coma ya no está (normalizar la quita): "1 magic 2 noche" se corta
        // también antes de cada cantidad.
        $partes = preg_split('/\s*(?:\by\b|\be\b|\bmas\b|\bademas\b|\btambien\b|\bcon\b)\s*|\s+(?=(?:\d{1,2}|' . $pal . ')\s+[a-z])/', $n);
        $vistos = [];

        foreach ($partes as $p) {
            $p = trim(preg_replace('/^(quiero|quisiera|deme|denme|mandeme|me manda|me da|me das|serian|seria|solo|nada mas|unos|unas|y|tambien|ademas|por favor)\s+/', '', trim($p)));
            if ($p === '') continue;

            // La cantidad del principio se saca antes de buscar la opción: si
            // no, "2 de noche" se leería como "la opción 2".
            $cant = 1;
            if (preg_match('/^(\d{1,2}|' . $pal . ')\s+(paquetes?\s+)?(de\s+)?(la|el|los|las)?\s*(.*)$/', $p, $m) && $m[5] !== '') {
                $cant = static::aNumero($m[1]) ?: 1;
                $p = $m[5];
            }

            $id = static::opcion($p, $opciones);
            if ($id === null) continue;

            $vistos[$id] = ($vistos[$id] ?? 0) + $cant;
        }

        if (count($vistos) < 2) return null;

        $r = [];
        foreach ($vistos as $id => $c) $r[] = [(string) $id, (int) $c];
        return $r;
    }

    /**
     * Las opciones cuyo nombre tiene alguna palabra del mensaje ("noche"),
     * para preguntar cuál cuando hay más de una.
     */
    public static function candidatos(?string $texto, array $opciones): array
    {
        $tokens = explode(' ', static::normalizar($texto));
        $ids = [];

        foreach ($opciones as $o) {
            foreach (explode(' ', static::normalizar($o['nombre'])) as $w) {
                if (mb_strlen($w) < 4) continue;
                if (in_array($w, ['panal', 'panales', 'calzoncito', 'calzoncitos', 'cinta', 'talla', 'pack', 'paquete', 'bebe', 'para'], true)) continue;
                if (in_array($w, $tokens, true)) { $ids[] = (string) $o['id']; break; }
            }
        }

        return array_values(array_unique($ids));
    }

    // ── Datos de entrega ─────────────────────────────────────────────────────

    /** Un celular o fijo de El Salvador en el texto: "7123 4567". */
    public static function telefonoSV(?string $texto): ?string
    {
        $t = preg_replace('/\$\s*[\d.,]+/u', ' ', (string) $texto);   // los montos no

        if (preg_match('/(?:^|[^\d])(?:\+?503[\s-]*)?([267]\d{3})[\s.-]?(\d{4})(?!\d)/u', $t, $m)) {
            return $m[1] . ' ' . $m[2];
        }

        return null;
    }

    /** ¿Parece un nombre de persona? Entre 1 y 6 palabras, sin números. */
    public static function pareceNombre(?string $texto): ?string
    {
        $t = trim(preg_replace('/\s+/u', ' ', (string) $texto));
        $t = preg_replace('/^(mi nombre es|me llamo|soy|a nombre de|nombre)\s*:?\s*/iu', '', $t);
        $t = trim($t, " .,:;-");

        if ($t === '' || preg_match('/\d/', $t)) return null;

        $n = static::palabras(static::normalizar($t));
        if ($n < 1 || $n > 6) return null;
        if (! preg_match('/^[\p{L} .\'-]+$/u', $t)) return null;

        return mb_convert_case(mb_strtolower($t), MB_CASE_TITLE, 'UTF-8');
    }

    /** ¿Alcanza como dirección? Al menos 4 palabras o una seña clara. */
    public static function pareceDireccion(?string $texto): bool
    {
        $n = static::normalizar($texto);
        if (static::palabras($n) >= 4) return true;

        return (bool) preg_match('/\b(col|colonia|calle|pasaje|pje|avenida|av|block|poligono|casa|residencial|res|barrio|canton|caserio|km|kilometro|frente|contiguo|costado)\b/', $n)
            && static::palabras($n) >= 3;
    }

    /**
     * Si pregunta algo de la lista de respuestas ("¿son calientes?"), el
     * texto para contestarle. null si no.
     */
    public static function respuesta(?string $texto, array $respuestas): ?string
    {
        $n = ' ' . static::normalizar($texto) . ' ';
        if (trim($n) === '') return null;

        foreach ($respuestas as $r) {
            foreach ((array) ($r['palabras'] ?? []) as $p) {
                $p = static::normalizar($p);
                if ($p !== '' && preg_match('/\b' . preg_quote($p, '/') . '\b/', $n)) {
                    return (string) ($r['texto'] ?? '');
                }
            }
        }

        return null;
    }

    /** "Lo quiero", "quiero comprar", "¿cómo hago el pedido?". */
    public static function quiereComprar(?string $texto): bool
    {
        $n = static::normalizar($texto);
        if ($n === '') return false;

        return (bool) preg_match('/\b(lo quiero|la quiero|los quiero|las quiero|quiero comprar|quiero pedir|quiero ordenar|quiero hacer (un|el|mi) pedido|hacer (un|el|mi) pedido|como (lo |la )?compro|como (hago|hacer|puedo hacer) (el |un |mi )?pedido|como (lo |la )?pido|me interesa|lo compro|la compro|lo llevo|la llevo|me lo llevo|deseo comprar|quisiera comprar|quisiera pedir|quiero adquirir|adquirir|comprar|me los manda|me lo manda|mandemelo|envienmelo)\b/', $n);
    }

    /** "Gracias", "ok", un emoji solo: no piden respuesta. */
    public static function esCortesia(?string $texto): bool
    {
        $n = static::normalizar($texto);
        if ($n === '') return true;

        return (bool) preg_match('/^(ah |a |o |oh )?(gracias|grasias|gracia|muchas gracias|mil gracias|ok|okey|oki|okis|dale|va|vaya|valla|baya|vaya vaya|listo|perfecto|bueno|esta bien|genial|excelente|entendido|entiendo|comprendo|de acuerdo|asi es|aja|mmm+|(ja)+j?|(je)+j?|ijole|le agradezco|se lo agradezco|todo esta bien|gracias estare pendiente|por favor|porfavor|porfa|xfavor|ya le digo|ya le confirmo|ya le aviso|estare pendiente|en serio|que bien|que bueno|ya recibi)( (gracias|esta bien|muchas gracias|por favor))?$/', $n);
    }

    /** El texto que viene escrito de fábrica en el botón del anuncio. */
    public static function esDelAnuncio(?string $texto): bool
    {
        $n = static::normalizar($texto);

        return (bool) preg_match('/\b(obtener|tener|recibir|saber) (mas )?informacion\b/', $n)
            && (bool) preg_match('/\b(puedo hablar con alguien|me gustaria|quisiera|quiero)\b/', $n)
            && mb_strlen($n) <= 90;
    }

    /** Mensajes que no dicen nada: "hola", "buenas", "gracias", un emoji. */
    public static function esSaludo(?string $texto): bool
    {
        $n = static::normalizar($texto);
        if ($n === '') return true;

        if (static::esDelAnuncio($n)) return true;

        // Se le sacan las palabras de saludo y relleno: si no queda nada (o una
        // palabra suelta), era un saludo. Así "hola buenas tardes", "hola
        // buen día, una consulta" y "buenas noches" cuentan, que en los chats
        // reales eran de los mensajes más comunes.
        $resto = preg_replace('/\b(hola|holi|holis|ola|buenas|buenos|buena|buen|muy|dias|dia|tardes|tarde|noches|noche|hey|que tal|como esta|como estan|saludos|disculpe|disculpa|una consulta|consulta|una pregunta|pregunta|mire|fijese|info|informacion|me gustaria|quisiera|quiero|me interesa|por favor|porfa|gracias|bendiciones|feliz)\b/', ' ', $n);
        $resto = trim(preg_replace('/\s+/', ' ', $resto));

        // Tiene que haber un saludo de verdad: si no, "de día porfa" (sin
        // "día" ni "porfa") quedaba en "de" y contaba como saludo.
        $saluda = (bool) preg_match('/\b(hola|holi|holis|ola|buenas|buenos|buen dia|saludos|hey|que tal|como esta|como estan)\b/', $n);

        if ($saluda && ($resto === '' || ! str_contains($resto, ' ') && mb_strlen($resto) <= 8 && ! preg_match('/\d/', $resto))) {
            return true;
        }

        return (bool) preg_match('/^(precio|precios|disponible|tiene|hay)( [a-z]+)?$/', $n);
    }
}
