<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;

/**
 * La foto de una presentación lista para mandarla por WhatsApp.
 *
 * WhatsApp solo acepta JPG y PNG. Las fotos guardadas en otro formato (WEBP,
 * por ejemplo) se pasan a JPG una sola vez y se reutiliza la copia.
 */
class FotoWhatsapp
{
    /** URL completa de una JPG/PNG para esa ruta, o null si no se puede. */
    public static function url(?string $ruta, int $id): ?string
    {
        if (! $ruta) return null;

        $ext = strtolower(pathinfo((string) parse_url($ruta, PHP_URL_PATH), PATHINFO_EXTENSION));

        if (in_array($ext, ['jpg', 'jpeg', 'png'], true)) {
            return str_starts_with($ruta, 'http') ? $ruta : url($ruta);
        }

        if (str_starts_with($ruta, '/storage/')) {
            $copia = static::copiaJpg(substr($ruta, strlen('/storage/')), $id);
            if ($copia) return url('/storage/' . $copia);
        }

        return null;
    }

    private static function copiaJpg(string $relativa, int $id): ?string
    {
        try {
            $disco = Storage::disk('public');
            if (! $disco->exists($relativa)) return null;

            $destino = 'whatsapp/presentaciones/' . $id . '-' . substr(md5($relativa . '|' . $disco->lastModified($relativa)), 0, 12) . '.jpg';
            if ($disco->exists($destino)) return $destino;

            if (! function_exists('imagecreatefromstring') || ! function_exists('imagejpeg')) return null;

            $img = @imagecreatefromstring((string) $disco->get($relativa));
            if (! $img) return null;

            // Fondo blanco: con transparencia, el JPG quedaría con fondo negro.
            $w = imagesx($img);
            $h = imagesy($img);
            $plano = imagecreatetruecolor($w, $h);
            imagefill($plano, 0, 0, imagecolorallocate($plano, 255, 255, 255));
            imagecopy($plano, $img, 0, 0, 0, 0, $w, $h);

            ob_start();
            imagejpeg($plano, null, 88);
            $bytes = ob_get_clean();

            imagedestroy($img);
            imagedestroy($plano);

            if (! $bytes) return null;

            $disco->put($destino, $bytes);

            return $destino;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
