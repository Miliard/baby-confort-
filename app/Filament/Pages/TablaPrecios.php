<?php

namespace App\Filament\Pages;

use App\Models\Product;
use Filament\Pages\Page;
use Illuminate\Support\Str;

/**
 * Arma una tabla de precios lista para mandar por WhatsApp como imagen.
 *
 * Lee el catálogo en vivo, así que nunca queda desactualizada: si cambiás un
 * precio o entra una talla nueva, la tabla sale corregida sola.
 *
 * Se incluyen TODAS las tallas, también las agotadas: el cliente quiere ver el
 * detalle completo aunque en ese momento no haya existencia.
 */
class TablaPrecios extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-table-cells';
    protected static ?string $navigationLabel = 'Tabla de precios';
    protected static ?string $title = 'Tabla de precios';
    protected static ?int $navigationSort = 4;

    protected static string $view = 'filament.pages.tabla-precios';

    /** Palabras que identifican a los productos absorbentes (pañales y afines). */
    public const PISTAS = ['pañal', 'panal', 'calzoncito', 'cinta', 'pants'];

    /** IDs de los productos marcados para salir en la tabla. */
    public array $elegidos = [];

    /** Mostrar u ocultar la marca de "sin existencia". */
    public bool $marcarAgotados = false;

    /** Mostrar la columna de precio por unidad. */
    public bool $porUnidad = true;

    public function mount(): void
    {
        // Arranca con los pañales ya marcados; él puede agregar o quitar.
        $this->elegidos = $this->catalogo()
            ->filter(fn ($p) => $this->pareceAbsorbente($p->name))
            ->pluck('id')->map(fn ($id) => (string) $id)->all();
    }

    /** Todos los productos activos que tengan al menos una talla. */
    public function catalogo()
    {
        try {
            return Product::where('active', true)
                ->with(['sizes' => fn ($q) => $q->orderBy('id')])
                ->orderBy('orden')->orderBy('id')
                ->get()
                ->filter(fn ($p) => $p->sizes->isNotEmpty())
                ->values();
        } catch (\Throwable $e) {
            return collect();
        }
    }

    private function pareceAbsorbente(?string $nombre): bool
    {
        $n = Str::lower((string) $nombre);
        foreach (self::PISTAS as $pista) {
            if (str_contains($n, $pista)) return true;
        }
        return false;
    }

    /** Peso que corresponde a una talla (de la config, o del propio catálogo). */
    public static function pesoDe($size): ?string
    {
        $llave = Str::upper(trim(preg_replace('/\s+/u', ' ', (string) $size->size)));
        $peso  = config('tallas_peso', [])[$llave] ?? null;

        if (! $peso) {
            // Algunas tallas traen el rango escrito en el propio catálogo.
            $peso = trim((string) ($size->weight ?? '')) ?: null;
        }

        return $peso;
    }

    /** Los grupos que se dibujan en la tabla, ya ordenados. */
    public function grupos(): array
    {
        $ids = array_map('intval', $this->elegidos);
        if (empty($ids)) return [];

        $grupos = [];

        foreach ($this->catalogo() as $p) {
            if (! in_array($p->id, $ids, true)) continue;

            $filas = [];
            foreach ($p->sizes as $s) {
                $precio = (float) $s->price;
                if ($precio <= 0) continue;   // tallas sin precio no dicen nada

                $uds = (int) ($s->unidades ?? 0);

                $filas[] = [
                    'talla'   => trim((string) $s->size),
                    'peso'    => static::pesoDe($s),
                    'uds'     => $uds,
                    'precio'  => $precio,
                    'unidad'  => $uds > 0 ? $precio / $uds : null,
                    'combo'   => ($s->combo_qty > 0 && $s->combo_price > 0)
                        ? ((int) $s->combo_qty) . ' x $' . number_format((float) $s->combo_price, 2)
                        : null,
                    'agotado' => (int) $s->quantity <= 0,
                ];
            }

            if ($filas) {
                $grupos[] = [
                    'producto' => static::limpiarNombre($p->name),
                    'filas'    => $filas,
                ];
            }
        }

        return $grupos;
    }

    /** Quita emojis y marcas del nombre para que la tabla se lea limpia. */
    public static function limpiarNombre(?string $nombre): string
    {
        $n = preg_replace('/[\x{1F000}-\x{1FAFF}\x{2600}-\x{27BF}\x{FE0F}\x{2190}-\x{21FF}]/u', '', (string) $nombre);
        $n = preg_replace('/\s{2,}/u', ' ', $n);
        return trim($n, " \t·-–—");
    }
}
