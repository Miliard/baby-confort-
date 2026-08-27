<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * "Cuando el texto diga esto, es este producto."
 *
 * Sirve para los renglones que no nombran el producto —"12 paquetes de 8 a 15
 * años"— o que lo escriben distinto a como está en el catálogo.
 */
class Equivalencia extends Model
{
    protected $table = 'equivalencias';

    protected $fillable = ['texto', 'product_id', 'talla', 'nota'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** Al guardar o borrar, la lista en memoria deja de valer. */
    protected static function booted(): void
    {
        $olvidar = fn () => Cache::forget('equivalencias_lista');

        static::saved($olvidar);
        static::deleted($olvidar);
    }

    /**
     * Deja el texto comparable: minúsculas, sin tildes y sin símbolos.
     * A diferencia del ranking, acá NO se quitan palabras como "a" o "de",
     * porque forman parte de lo que se busca ("8 a 15").
     */
    public static function normalizar(?string $texto): string
    {
        $t = mb_strtolower(trim((string) $texto));
        $t = strtr($t, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
        ]);
        $t = preg_replace('/[^a-z0-9\s]/u', ' ', $t);

        return trim(preg_replace('/\s+/', ' ', $t));
    }

    public static function hayTabla(): bool
    {
        try {
            return Schema::hasTable('equivalencias');
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Todas las equivalencias, de la más específica a la más general.
     *
     * El orden importa: si existen "8 a 15" y "8 a 15 años", tiene que ganar
     * la más larga, que es la que describe mejor el caso.
     */
    public static function lista(): array
    {
        if (! static::hayTabla()) return [];

        return Cache::remember('equivalencias_lista', 600, function () {
            $out = [];

            try {
                $filas = static::with('product')->whereNotNull('product_id')->get();
            } catch (\Throwable $e) {
                return [];
            }

            foreach ($filas as $e) {
                $buscar = static::normalizar($e->texto);
                if ($buscar === '' || ! $e->product) continue;

                $out[] = [
                    'buscar' => $buscar,
                    'largo'  => mb_strlen($buscar),
                    'id'     => $e->product->id,
                    'nombre' => $e->product->name,
                    'talla'  => trim((string) $e->talla) ?: null,
                ];
            }

            usort($out, fn ($a, $b) => $b['largo'] <=> $a['largo']);

            return $out;
        });
    }

    /**
     * ¿Alguna equivalencia reconoce este renglón?
     *
     * Se compara por palabras completas, no por trozos sueltos: si no, una
     * regla corta como "rn" se colaría dentro de "carne" o "gorra".
     */
    public static function buscarEn(string $linea): ?array
    {
        $texto = static::normalizar($linea);
        if ($texto === '') return null;

        foreach (static::lista() as $e) {
            $patron = '/\b' . preg_quote($e['buscar'], '/') . '\b/u';
            if (preg_match($patron, $texto)) return $e;
        }

        return null;
    }
}
