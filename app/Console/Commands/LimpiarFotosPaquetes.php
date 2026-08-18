<?php

namespace App\Console\Commands;

use App\Http\Controllers\GuiaFotoController;
use Illuminate\Console\Command;

/**
 * Borra del disco las fotos de paquetes que ya cumplieron su tiempo.
 * El registro (guía, cliente, teléfono, qué llevaba) NUNCA se borra: pesa
 * casi nada y es lo que sostiene el rastreo y el ranking de productos.
 */
class LimpiarFotosPaquetes extends Command
{
    protected $signature = 'fotos:limpiar {--dias= : Días que se guardan (por defecto, lo configurado)}';

    protected $description = 'Borra las fotos de paquetes vencidas para no llenar el disco';

    public function handle(): int
    {
        $dias = $this->option('dias') !== null ? (int) $this->option('dias') : null;

        $antes = GuiaFotoController::espacioUsado();
        $borradas = GuiaFotoController::limpiarViejas($dias);
        $despues = GuiaFotoController::espacioUsado();

        $this->info(sprintf(
            'Fotos borradas: %d · Guardadas %d días · Espacio: %s → %s',
            $borradas,
            $dias ?? GuiaFotoController::diasDeFotos(),
            $antes['legible'],
            $despues['legible']
        ));

        return self::SUCCESS;
    }
}
