<?php

namespace App\Filament\Pages;

use App\Models\Cliente;
use App\Models\ExpressEntrega;
use App\Models\GuiaFoto;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Schema;

/**
 * Un solo buscador para todo: nombre, teléfono o número de guía.
 *
 * Mira en tres lados a la vez, porque la respuesta suele estar repartida:
 *   · Entregas de Express → cuánto se cobró al entregar (si pagó o no)
 *   · Guías con foto      → qué llevaba y su enlace de rastreo
 *   · Libreta de clientes → dirección y municipio
 */
class Buscar extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-magnifying-glass';
    protected static ?string $navigationLabel = 'Buscar';
    protected static ?string $title = 'Buscar';
    protected static ?int $navigationSort = 0;

    protected static string $view = 'filament.pages.buscar';

    public string $q = '';

    /**
     * Resultados ya buscados. La vista pide cada lista dos veces —una para el
     * conteo de arriba y otra para pintarla—, así que sin esto serían el doble
     * de consultas por cada tecla que escribís.
     */
    private array $memoria = [];

    private function recordar(string $clave, callable $buscar)
    {
        $llave = $clave . '|' . trim($this->q);

        if (! array_key_exists($llave, $this->memoria)) {
            $this->memoria[$llave] = $buscar();
        }

        return $this->memoria[$llave];
    }

    /** Solo los dígitos: sirve igual escribir "7723-2515" que "77232515". */
    private function digitos(): string
    {
        return preg_replace('/\D/', '', $this->q);
    }

    /** Compara nombres sin importar mayúsculas ni tildes. */
    private function condicionNombre($query, string $campo)
    {
        $s = mb_strtolower(trim($this->q));
        $s = strtr($s, ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ü'=>'u','ñ'=>'n']);
        if ($s === '') return $query;

        $limpio = "LOWER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE({$campo},''),"
                . "'á','a'),'é','e'),'í','i'),'ó','o'),'ú','u'),'ñ','n'))";

        return $query->whereRaw("{$limpio} LIKE ?", ['%' . $s . '%']);
    }

    public function buscando(): bool
    {
        return mb_strlen(trim($this->q)) >= 3;
    }

    /** Entregas de Express: acá se ve si cobró y cuánto. */
    public function entregas()
    {
        return $this->recordar('entregas', function () {
            if (! $this->buscando() || ! ExpressEntrega::hayTabla()) return collect();

            $d = $this->digitos();

            try {
                return ExpressEntrega::where(function ($w) use ($d) {
                        $this->condicionNombre($w, 'nombre');
                        if (strlen($d) >= 4) $w->orWhere('orden', 'like', '%' . $d . '%');
                    })
                    ->orderByDesc('fecha')->orderByDesc('id')
                    ->limit(40)->get();
            } catch (\Throwable $e) {
                return collect();
            }
        });
    }

    /** Guías con foto: qué llevaba y a qué teléfono. */
    public function guias()
    {
        return $this->recordar('guias', function () {
            if (! $this->buscando()) return collect();

            try {
                if (! Schema::hasTable('guia_fotos')) return collect();
            } catch (\Throwable $e) {
                return collect();
            }

            $d = $this->digitos();

            try {
                return GuiaFoto::where(function ($w) use ($d) {
                        $this->condicionNombre($w, 'nombre');
                        if (strlen($d) >= 4) {
                            $w->orWhere('guia', 'like', '%' . $d . '%');

                            // El teléfono se guarda con guion, espacios o de corrido.
                            $limpio = "REPLACE(REPLACE(REPLACE(COALESCE(telefono,''),' ',''),'-',''),'+','')";
                            $w->orWhereRaw("{$limpio} LIKE ?", ['%' . $d . '%']);
                        }
                    })
                    ->orderByDesc('id')->limit(40)->get();
            } catch (\Throwable $e) {
                return collect();
            }
        });
    }

    /** Libreta de clientes: dirección y municipio. */
    public function clientes()
    {
        return $this->recordar('clientes', function () {
            if (! $this->buscando()) return collect();

            try {
                if (! Schema::hasTable('clientes')) return collect();
            } catch (\Throwable $e) {
                return collect();
            }

            $d = $this->digitos();

            try {
                return Cliente::where(function ($w) use ($d) {
                        $this->condicionNombre($w, 'nombre');
                        if (strlen($d) >= 4) $w->orWhere('telefono', 'like', '%' . $d . '%');
                    })
                    ->orderByDesc('updated_at')->limit(20)->get();
            } catch (\Throwable $e) {
                return collect();
            }
        });
    }

    /** Cuántos resultados hay en total, para el resumen de arriba. */
    public function cuantos(): int
    {
        return $this->entregas()->count() + $this->guias()->count() + $this->clientes()->count();
    }
}
