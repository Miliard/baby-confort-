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

    /** El listado con las llaves ya normalizadas, para buscar rápido. */
    private static function tabla(): array
    {
        static $tabla = null;

        if ($tabla !== null) return $tabla;

        $tabla = [];

        foreach (config('municipios', []) as $municipio => $departamento) {
            $tabla[static::normalizar($municipio)] = [
                'nombre'        => $municipio,
                'departamentos' => (array) $departamento,
            ];
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

        foreach ($posibles as $p) {
            if (static::normalizar($p) === static::normalizar($dep)) {
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
        $d = static::normalizar($departamento);
        if ($d === '') return [];

        $lista = [];

        foreach (config('municipios', []) as $municipio => $departamentos) {
            foreach ((array) $departamentos as $uno) {
                if (static::normalizar($uno) === $d) {
                    $lista[] = $municipio;
                    break;
                }
            }
        }

        sort($lista, SORT_LOCALE_STRING);

        return $lista;
    }

    /** Los 14 departamentos, para el desplegable. */
    public static function departamentos(): array
    {
        $todos = [];

        foreach (config('municipios', []) as $d) {
            foreach ((array) $d as $uno) $todos[$uno] = $uno;
        }

        ksort($todos);

        return array_values($todos);
    }
}
