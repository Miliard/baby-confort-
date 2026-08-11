<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Sirve la foto de vista previa (la que ve WhatsApp/Facebook al pegar el enlace)
 * desde NUESTRO dominio.
 *
 * ¿Por qué? Muchas fotos viven en el CDN de la marca (aiwibi.com) y el robot de
 * WhatsApp no puede bajarlas de ahí: por eso el enlace salía sin imagen.
 * Aquí la bajamos una sola vez, la dejamos en 1200x630 (el tamaño que WhatsApp
 * muestra grande) y la guardamos en caché.
 */
class OgImageController extends Controller
{
    private const ANCHO = 1200;
    private const ALTO  = 630;

    public function producto(Product $product, ?string $talla = null)
    {
        $origen = null;

        if ($talla) {
            $s = $product->sizes->first(
                fn ($x) => Str::slug($x->size) === Str::slug($talla)
            );
            if ($s) $origen = $s->imageUrl();
        }
        $origen = $origen ?: $product->imageUrl();

        if (! $origen) {
            return $this->porDefecto();
        }

        $cache = storage_path('app/og-cache');
        if (! is_dir($cache)) @mkdir($cache, 0775, true);
        $destino = $cache . '/' . md5($origen) . '.jpg';

        // Se rehace cada 7 días por si cambiaste la foto en el panel.
        if (! is_file($destino) || (time() - filemtime($destino)) > 604800) {
            $bytes = $this->descargar($origen);
            if ($bytes === null) return $this->porDefecto();

            $jpg = $this->aLienzo($bytes);
            if ($jpg === null) return $this->porDefecto();

            @file_put_contents($destino, $jpg);
        }

        return response()->file($destino, [
            'Content-Type'  => 'image/jpeg',
            'Cache-Control' => 'public, max-age=604800',
        ]);
    }

    /** Baja la imagen, sea local (/storage/...) o de otro sitio. */
    private function descargar(string $url): ?string
    {
        try {
            if (! Str::startsWith($url, 'http')) {
                $ruta = public_path(ltrim($url, '/'));
                return is_file($ruta) ? file_get_contents($ruta) : null;
            }
            $r = Http::withHeaders([
                    // Sin User-Agent de navegador, varios CDN devuelven 403.
                    'User-Agent' => 'Mozilla/5.0 (compatible; BabyConfort/1.0)',
                ])->timeout(12)->get($url);

            return $r->successful() ? $r->body() : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** Pone la foto centrada sobre un lienzo blanco de 1200x630. */
    private function aLienzo(string $bytes): ?string
    {
        if (! function_exists('imagecreatefromstring')) {
            return $bytes; // sin GD: al menos servimos la original desde nuestro dominio
        }

        $img = @imagecreatefromstring($bytes);
        if ($img === false) return null;

        $ow = imagesx($img);
        $oh = imagesy($img);
        if ($ow < 1 || $oh < 1) { imagedestroy($img); return null; }

        $lienzo = imagecreatetruecolor(self::ANCHO, self::ALTO);
        $blanco = imagecolorallocate($lienzo, 255, 255, 255);
        imagefilledrectangle($lienzo, 0, 0, self::ANCHO, self::ALTO, $blanco);

        $escala = min(self::ANCHO / $ow, self::ALTO / $oh);
        $nw = max(1, (int) round($ow * $escala));
        $nh = max(1, (int) round($oh * $escala));
        $x  = (int) round((self::ANCHO - $nw) / 2);
        $y  = (int) round((self::ALTO - $nh) / 2);

        imagecopyresampled($lienzo, $img, $x, $y, 0, 0, $nw, $nh, $ow, $oh);
        imagedestroy($img);

        ob_start();
        imagejpeg($lienzo, null, 85);
        $salida = ob_get_clean();
        imagedestroy($lienzo);

        return $salida ?: null;
    }

    private function porDefecto()
    {
        $ruta = public_path('og-image.png');
        if (is_file($ruta)) {
            return response()->file($ruta, ['Cache-Control' => 'public, max-age=86400']);
        }
        abort(404);
    }
}
