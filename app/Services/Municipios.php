<?php

namespace App\Services;

/**
 * Comprueba que el municipio y el departamento se correspondan.
 *
 * Nació de un error concreto: una guía que decía "San Vicente, San Salvador".
 * San Vicente es municipio del departamento de San Vicente, así que ese
 * paquete salía para el otro lado del país.
 */
class Municipios
{
    /** Sin tildes, sin mayúsculas y sin espacios de más, para poder comparar. */
    public static function normalizar(?string $texto): string
    {
        $t = trim(mb_strtolower((string) $texto));
        if ($t === '') return '';

        // Se le quita lo que suele venir pegado: "Depto.", "Municipio de", etc.
        $t = preg_replace('/^(municipio|depto\.?|departamento|dpto\.?)\s+(de\s+)?/u', '', $t);

        $t = strtr($t, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
        ]);

        return trim(preg_replace('/\s+/u', ' ', $t));
    }

    /**
     * Departamentos que los dos catálogos escriben distinto.
     *
     * A la izquierda, el nombre normalizado venga de donde venga. A la derecha,
     * cómo hay que escribirlo: se eligió la grafía de Sistrack —aunque a dos les
     * falte la tilde y una esté mal escrita— porque es la que termina en el
     * Excel de la guía. Que el courier lo acepte pesa más que la ortografía.
     *
     * Sin esto, al unir los catálogos el desplegable mostraba "Chalatenango" y
     * "Chaletenango" como si fueran dos departamentos distintos, cada uno con la
     * mitad de sus municipios.
     */
    private const IGUALES = [
        'chalatenango' => 'Chaletenango',
        'chaletenango' => 'Chaletenango',
        'cuscatlan'    => 'Cuscatlan',
        'usulutan'     => 'Usulutan',
    ];

    /** El nombre con el que se guarda y se muestra ese departamento. */
    public static function departamentoCanonico(?string $departamento): string
    {
        $d = trim((string) $departamento);
        if ($d === '') return '';

        return static::IGUALES[static::normalizar($d)] ?? $d;
    }

    /** El listado con las llaves ya normalizadas, para buscar rápido. */
    private static function tabla(): array
    {
        static $tabla = null;

        if ($tabla !== null) return $tabla;

        $tabla = [];

        foreach (config('municipios', []) as $municipio => $departamento) {
            $tabla[static::normalizar($municipio)] = [
                'nombre'        => $municipio,
                // Por el canónico: si no, los tres departamentos que los dos
                // catálogos escriben distinto quedarían duplicados.
                'departamentos' => array_map(
                    fn ($d) => static::departamentoCanonico($d),
                    (array) $departamento
                ),
            ];
        }

        // Y lo que traiga el catálogo de Sistrack que acá falte.
        //
        // Hay dos listas de municipios en el proyecto: esta, escrita para
        // validar, y municipios_sv, que es la de Sistrack y manda sobre lo que
        // se escribe en la guía. Si una tiene un nombre que la otra no, pasa lo
        // que pasó con "Puerto de La Libertad": Sistrack lo conoce, el panel
        // decía que no existe y no había forma de elegirlo.
        //
        // Uniéndolas acá, cualquier nombre que Sistrack acepte queda aceptado
        // también en el panel, sin tener que mantener las dos a mano.
        foreach (config('municipios_sv', []) as $departamento => $municipios) {
            foreach ((array) $municipios as $municipio) {
                $clave = static::normalizar($municipio);

                if ($clave === '' || isset($tabla[$clave])) continue;

                $tabla[$clave] = [
                    'nombre'        => $municipio,
                    'departamentos' => [static::departamentoCanonico($departamento)],
                ];
            }
        }

        return $tabla;
    }

    /** ¿Lo conocemos? */
    public static function existe(?string $municipio): bool
    {
        return isset(static::tabla()[static::normalizar($municipio)]);
    }

    /** Los departamentos posibles de ese municipio. Vacío si no se conoce. */
    public static function departamentosDe(?string $municipio): array
    {
        return static::tabla()[static::normalizar($municipio)]['departamentos'] ?? [];
    }

    /** El nombre bien escrito, con tildes y mayúsculas. */
    public static function nombreBueno(?string $municipio): ?string
    {
        return static::tabla()[static::normalizar($municipio)]['nombre'] ?? null;
    }

    /**
     * El departamento, si no hay duda.
     *
     * Devuelve null cuando el municipio no se conoce o cuando el nombre existe
     * en varios departamentos: ahí hay que preguntar, no adivinar.
     */
    public static function departamentoSeguro(?string $municipio): ?string
    {
        $d = static::departamentosDe($municipio);

        return count($d) === 1 ? $d[0] : null;
    }

    /**
     * Revisa el par municipio/departamento.
     *
     * Devuelve ['estado' => ..., 'mensaje' => ..., 'sugerido' => ...] donde
     * estado es uno de:
     *
     *   ok          → se corresponden
     *   vacio       → falta el municipio, no hay nada que revisar
     *   desconocido → ese municipio no está en la lista
     *   ambiguo     → el nombre existe en varios departamentos
     *   error       → no se corresponden: hay que corregir antes de guardar
     */
    public static function revisar(?string $municipio, ?string $departamento): array
    {
        $mun = trim((string) $municipio);

        if ($mun === '') {
            return ['estado' => 'vacio', 'mensaje' => '', 'sugerido' => null];
        }

        $posibles = static::departamentosDe($mun);

        if (! $posibles) {
            return [
                'estado'   => 'desconocido',
                'mensaje'  => "No reconozco el municipio «{$mun}». Revisá cómo está escrito.",
                'sugerido' => null,
            ];
        }

        $dep = trim((string) $departamento);

        // Sin departamento y con un solo candidato: se completa solo.
        if ($dep === '') {
            return count($posibles) === 1
                ? ['estado' => 'ok', 'mensaje' => '', 'sugerido' => $posibles[0]]
                : [
                    'estado'   => 'ambiguo',
                    'mensaje'  => "«{$mun}» existe en " . implode(', ', $posibles)
                                . '. Elegí cuál es.',
                    'sugerido' => null,
                ];
        }

        // Se comparan los canónicos, no los nombres crudos: así una guía vieja
        // guardada como "Chalatenango" sigue validando contra "Chaletenango",
        // que es como se escribe ahora. Si no, todo lo cargado antes del cambio
        // empezaría a marcarse como error sin que nada esté mal.
        foreach ($posibles as $p) {
            if (static::departamentoCanonico($p) === static::departamentoCanonico($dep)) {
                return ['estado' => 'ok', 'mensaje' => '', 'sugerido' => $p];
            }
        }

        $correcto = implode(' o ', $posibles);

        return [
            'estado'   => 'error',
            'mensaje'  => "«{$mun}» no pertenece a {$dep}. Es de {$correcto}.",
            'sugerido' => count($posibles) === 1 ? $posibles[0] : null,
        ];
    }

    /**
     * Busca un municipio conocido dentro de un texto suelto.
     *
     * Sirve cuando el municipio vino mezclado con otra cosa, o cuando el campo
     * quedó vacío pero la dirección lo repite — cosa que pasa casi siempre:
     * "caserío los chilamates nueva concepción chalatenango".
     *
     * Prueba primero los nombres largos: si no, "San Miguel" ganaría dentro de
     * "San Miguel Tepezontes" y mandaría el paquete a otro departamento.
     */
    public static function buscarEn(?string $texto): ?string
    {
        $t = static::normalizar($texto);
        if ($t === '') return null;

        $candidatos = array_keys(static::tabla());

        usort($candidatos, fn ($a, $b) => mb_strlen($b) <=> mb_strlen($a));

        foreach ($candidatos as $c) {
            // Con límites de palabra, para que "colon" no aparezca dentro de
            // cualquier palabra que lo contenga.
            if (preg_match('/(?<![\p{L}])' . preg_quote($c, '/') . '(?![\p{L}])/u', $t)) {
                return static::tabla()[$c]['nombre'];
            }
        }

        return null;
    }

    /**
     * Los municipios de un departamento, en orden alfabético.
     *
     * Sirve para el desplegable: eligiendo primero el departamento, la lista de
     * municipios se reduce a los suyos y ya no hay forma de armar una pareja
     * imposible. Es la misma protección que hace revisar(), pero de antemano.
     */
    public static function deDepartamento(?string $departamento): array
    {
        $d = static::departamentoCanonico($departamento);
        if ($d === '') return [];

        $lista = [];

        // Desde tabla() y no desde el config: así incluye también los nombres
        // que solo trae el catálogo de Sistrack.
        foreach (static::tabla() as $fila) {
            foreach ($fila['departamentos'] as $uno) {
                if (static::departamentoCanonico($uno) === $d) {
                    $lista[] = $fila['nombre'];
                    break;
                }
            }
        }

        sort($lista, SORT_LOCALE_STRING);

        return $lista;
    }

    /**
     * Todos los municipios, agrupados por departamento.
     *
     * Es la misma información que deDepartamento(), pero entregada de una sola
     * vez. Sirve para que el buscador del formulario pueda cambiar de lista al
     * cambiar de departamento sin volver a preguntarle al servidor — que es lo
     * que no estaba pasando y por eso salían los 262 juntos.
     *
     * Un municipio cuyo nombre existe en dos departamentos aparece en los dos.
     */
    public static function porDepartamento(): array
    {
        $mapa = [];

        foreach (static::tabla() as $fila) {
            foreach ($fila['departamentos'] as $d) {
                $mapa[$d][] = $fila['nombre'];
            }
        }

        foreach ($mapa as &$lista) {
            sort($lista, SORT_LOCALE_STRING);
        }
        unset($lista);

        ksort($mapa);

        return $mapa;
    }

    /** Todos los municipios conocidos, en orden alfabético. */
    public static function todos(): array
    {
        $lista = array_map(fn ($f) => $f['nombre'], static::tabla());

        sort($lista, SORT_LOCALE_STRING);

        return array_values($lista);
    }

    /** Los 14 departamentos, para el desplegable. */
    public static function departamentos(): array
    {
        $todos = [];

        foreach (static::tabla() as $fila) {
            foreach ($fila['departamentos'] as $uno) $todos[$uno] = $uno;
        }

        ksort($todos);

        return array_values($todos);
    }
}
