<?php

namespace App\Services;

/**
 * Lee con IA todo lo impreso en la etiqueta de un paquete.
 *
 * El lector que corre en el teléfono lee solo la franja de arriba —"Para:
 * 6996 1224 Ana Serpas"— y la lee regular: parte los nombres en pedazos y
 * cambia dígitos. Con eso solo se puede emparejar por teléfono, y si el
 * teléfono salió mal, no hay con qué más.
 *
 * Un modelo de visión lee la etiqueta impresa casi perfecta, y la lee
 * ENTERA: teléfono, nombre, dirección, municipio, departamento, contenido,
 * el monto a cobrar y el número de orden. Con todo eso, un dígito mal leído
 * deja de importar.
 *
 * LA IA SOLO LEE. No decide a quién le va la foto: eso lo hace el sistema,
 * comparando, y lo muestra en pantalla para que vos lo veas.
 */
class LeerEtiqueta
{
    public static function disponible(): bool
    {
        return IA::disponible();
    }

    /**
     * Devuelve los datos de la etiqueta, o null si no se pudo leer.
     *
     * Todas las claves vienen siempre; las que no estaban impresas, vacías.
     */
    public static function de(string $rutaAbsoluta): ?array
    {
        if (! static::disponible()) return null;

        $datos = IA::json(IA::leerImagen(static::instrucciones(), $rutaAbsoluta, 0.0));
        if (! is_array($datos)) return null;

        $texto = fn ($k) => trim((string) (is_scalar($datos[$k] ?? null) ? $datos[$k] : ''));

        $tel = preg_replace('/\D/', '', $texto('telefono'));
        if (strlen($tel) === 11 && str_starts_with($tel, '503')) $tel = substr($tel, 3);

        $cobrar = $datos['cobrar'] ?? null;
        $cobrar = is_numeric($cobrar) ? round((float) $cobrar, 2) : null;

        return [
            'telefono'     => strlen($tel) === 8 ? $tel : '',
            'nombre'       => $texto('nombre'),
            'direccion'    => $texto('direccion'),
            'municipio'    => $texto('municipio'),
            'departamento' => $texto('departamento'),
            'contenido'    => $texto('contenido'),
            'cobrar'       => $cobrar,
            'pagado'       => (bool) ($datos['pagado'] ?? false),
            'orden'        => preg_replace('/\D/', '', $texto('orden')),
        ];
    }

    private static function instrucciones(): string
    {
        return <<<TXT
        Te paso la foto de una etiqueta de envío de Xpress El Salvador, pegada
        sobre un paquete. Leés lo que está IMPRESO y lo devolvés como JSON.

        NO adivines ni completes. Si un dato no se lee con claridad, va vacío.
        Un dato inventado manda la foto a otra persona; uno vacío se nota y se
        completa a mano.

        Contestá SOLO este JSON, sin nada más:

        {
          "telefono": "",
          "nombre": "",
          "direccion": "",
          "municipio": "",
          "departamento": "",
          "contenido": "",
          "cobrar": null,
          "pagado": false,
          "orden": ""
        }

        Dónde está cada cosa en la etiqueta:

        - telefono y nombre: en el renglón "Para:", al principio. El teléfono
          son 8 dígitos, a veces partidos en dos grupos de 4. Escribilo sin
          espacios. Leelo con cuidado dígito por dígito: es lo más importante.
        - direccion: lo que sigue al nombre en ese mismo bloque, sin el
          municipio y el departamento del final.
        - municipio y departamento: en el renglón grande del medio, separados
          por una barra: "Usulután | Usulutan". El de la izquierda es el
          municipio, el de la derecha el departamento.
        - contenido: lo que dice después de "Contenido del paquete".
        - cobrar: el monto de "COBRAR AL ENTREGAR", como número (12.00 → 12).
          Si dice "PAGADO" o "no cobrar", cobrar es 0 y pagado es true.
        - orden: el número de "Orden #".

        Si la foto no es una etiqueta, o no se lee nada, devolvé todo vacío.
        TXT;
    }
}
