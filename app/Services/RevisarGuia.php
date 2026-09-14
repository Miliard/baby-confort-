<?php

namespace App\Services;

/**
 * Revisa una guía antes de que salga al Excel.
 *
 * La idea es simple: una guía mal escrita no se descubre acá, se descubre
 * cuando el paquete ya va camino al otro lado del país o cuando el motorista
 * llama porque no encuentra la casa. Entonces conviene que la pantalla diga
 * qué está raro mientras todavía se puede corregir con el lápiz.
 *
 * Cada aviso tiene un nivel:
 *   error → esto sale mal seguro. Hay que corregirlo.
 *   ojo   → puede estar bien, pero merece una mirada.
 */
class RevisarGuia
{
    /**
     * Devuelve los avisos de una fila. Arreglo vacío = la guía está limpia.
     *
     * @param  array  $g  La fila tal como va al Excel.
     * @return array<int, array{nivel: string, texto: string}>
     */
    public static function de(array $g): array
    {
        $avisos = [];

        $nombre = trim((string) ($g['nombre'] ?? ''));
        $tel    = preg_replace('/\D/', '', (string) ($g['telefono'] ?? ''));
        $dir    = trim((string) ($g['direccion'] ?? ''));
        $mun    = trim((string) ($g['municipio'] ?? ''));
        $dep    = trim((string) ($g['departamento'] ?? ''));
        $desc   = trim((string) ($g['descripcion'] ?? ''));

        // ── Nombre ───────────────────────────────────────────────────────────
        if ($nombre === '') {
            $avisos[] = ['nivel' => 'error', 'texto' => 'No tiene nombre'];
        } else {
            // El teléfono metido adentro del nombre: sale duplicado en el Excel.
            if (preg_match('/(?<!\d)[267]\d{3}[\s.\-]?\d{4}(?!\d)/u', $nombre)
                || preg_match('/(?<!\d)\+?503[\s.\-]*\d/u', $nombre)) {
                $avisos[] = ['nivel' => 'ojo', 'texto' => 'El nombre lleva el teléfono adentro'];
            }

            // Un nombre de una sola letra o dos suele ser un renglón mal leído.
            if (mb_strlen(preg_replace('/[^\p{L}]/u', '', $nombre)) < 3) {
                $avisos[] = ['nivel' => 'error', 'texto' => 'El nombre no parece un nombre'];
            }
        }

        // ── Teléfono ─────────────────────────────────────────────────────────
        if ($tel === '') {
            $avisos[] = ['nivel' => 'error', 'texto' => 'No tiene teléfono'];
        } else {
            $ocho = strlen($tel) >= 8 ? substr($tel, -8) : $tel;

            if (strlen($ocho) !== 8) {
                $avisos[] = ['nivel' => 'error', 'texto' => 'El teléfono no tiene 8 dígitos'];
            } elseif (! in_array($ocho[0], ['2', '6', '7'], true)) {
                // En El Salvador todos empiezan con 2, 6 o 7.
                $avisos[] = ['nivel' => 'error', 'texto' => "El teléfono empieza con {$ocho[0]}, y acá no existen esos números"];
            }
        }

        // ── Municipio y departamento ─────────────────────────────────────────
        if ($mun === '') {
            $avisos[] = ['nivel' => 'error', 'texto' => 'No tiene municipio'];
        } else {
            $zona = Municipios::revisar($mun, $dep);

            if ($zona['estado'] === 'error' || $zona['estado'] === 'ambiguo' || $zona['estado'] === 'desconocido') {
                $avisos[] = [
                    'nivel' => $zona['estado'] === 'error' ? 'error' : 'ojo',
                    'texto' => $zona['mensaje'],
                ];
            }
        }

        if ($dep === '') {
            $avisos[] = ['nivel' => 'error', 'texto' => 'No tiene departamento'];
        }

        // ── Dirección ────────────────────────────────────────────────────────
        if ($dir === '') {
            $avisos[] = ['nivel' => 'error', 'texto' => 'No tiene dirección'];
        } elseif (mb_strlen($dir) < 12) {
            $avisos[] = ['nivel' => 'ojo', 'texto' => 'La dirección es muy corta: «' . $dir . '»'];
        }

        // ── Contenido ────────────────────────────────────────────────────────
        if ($desc === '') {
            $avisos[] = ['nivel' => 'error', 'texto' => 'No dice qué lleva el paquete'];
        }

        return $avisos;
    }

    /** ¿Tiene algo que impida mandarla así? */
    public static function tieneError(array $g): bool
    {
        foreach (static::de($g) as $a) {
            if ($a['nivel'] === 'error') return true;
        }

        return false;
    }

    /** Cuenta, sobre toda la lista, cuántas traen error y cuántas traen ojo. */
    public static function resumen(array $lista): array
    {
        $errores = 0;
        $ojos    = 0;

        foreach ($lista as $g) {
            $avisos = static::de($g);
            if (! $avisos) continue;

            $conError = false;
            foreach ($avisos as $a) {
                if ($a['nivel'] === 'error') $conError = true;
            }

            $conError ? $errores++ : $ojos++;
        }

        return ['errores' => $errores, 'ojos' => $ojos];
    }
}
