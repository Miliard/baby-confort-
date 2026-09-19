<?php

namespace App\Console\Commands;

use App\Models\ExpressEntrega;
use App\Models\GuiaFoto;
use App\Models\Order;
use App\Models\WaConversacion;
use App\Models\WaEtiqueta;
use App\Services\Etiquetado;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Mira en qué va cada paquete y mueve la etiqueta de la conversación.
 *
 * El estado lo tiene el courier. El panel ya lo consulta cuando el cliente abre
 * su rastreo; acá lo consulta por su cuenta, para las conversaciones que están
 * esperando entrega, y mueve la etiqueta a Entregados cuando corresponde.
 *
 * TRES REGLAS, y las tres salen de cómo funciona esto en la vida real:
 *
 *  1. No creerle a la primera. Al repartidor se le ha ido marcar entregado por
 *     error y corregirlo minutos después. Hacen falta DOS revisiones seguidas
 *     diciendo lo mismo. Entre una y otra pasa media hora: más que suficiente
 *     para que la corrección llegue.
 *
 *  2. Seguir mirando después de marcar. Si el courier se desdice, la etiqueta
 *     vuelve a Preparados. Sigue al courier en las dos direcciones, porque un
 *     error de ellos no tiene por qué quedarse acá para siempre.
 *
 *  3. La plata es la palabra final. Entregado en la página del courier no es lo
 *     mismo que cobrado. Una conversación deja de vigilarse recién cuando su
 *     guía aparece en la liquidación de Express: ahí sí se sabe que entregó y
 *     que el dinero entró.
 */
class RevisarEntregas extends Command
{
    protected $signature = 'entregas:revisar {--limite=40 : Cuántas conversaciones revisar por vuelta}';

    protected $description = 'Consulta al courier y mueve las conversaciones a Entregados';

    public function handle(): int
    {
        $procesada = WaEtiqueta::porRol('procesada');
        $entregada = WaEtiqueta::porRol('entregada');

        if (! $procesada && ! $entregada) {
            $this->warn('No hay etiquetas con el papel "procesada" ni "entregada". Asignalas en el admin.');
            return self::SUCCESS;
        }

        $ids = collect();
        if ($procesada) $ids = $ids->merge($procesada->conversaciones()->pluck('wa_conversaciones.id'));
        if ($entregada) $ids = $ids->merge($entregada->conversaciones()->pluck('wa_conversaciones.id'));

        $ids = $ids->unique();

        if ($ids->isEmpty()) {
            $this->info('No hay nada esperando entrega.');
            return self::SUCCESS;
        }

        // Las menos revisadas primero, para que ninguna se quede sin turno
        // cuando hay más de las que entran en una vuelta.
        $convs = WaConversacion::whereIn('id', $ids)
            ->orderByRaw('revisado_at IS NULL DESC')
            ->orderBy('revisado_at')
            ->limit((int) $this->option('limite'))
            ->get();

        $movidas = 0;
        $vueltas = 0;
        $cerradas = 0;
        $sinGuia = 0;
        $contando = 0;

        foreach ($convs as $conv) {
            try {
                $r = $this->revisarUna($conv, $entregada);

                if ($r === 'entregada') $movidas++;
                if ($r === 'volvio')    $vueltas++;
                if ($r === 'cerrada')   $cerradas++;
                if ($r === 'sin-guia')  $sinGuia++;
                if ($r === 'primera')   $contando++;
            } catch (\Throwable $e) {
                Log::warning('Revisar entregas (conv ' . $conv->id . '): ' . $e->getMessage());
            }
        }

        $this->info("Revisadas {$convs->count()} · {$movidas} a Entregados · {$vueltas} devueltas · {$cerradas} cerradas por liquidación");

        // Esto es lo que explica por qué una conversación no se mueve nunca.
        // Sin número de guía no hay a quién preguntarle, así que se saltea en
        // silencio — y en silencio parece que el panel no funciona.
        if ($sinGuia > 0) {
            $this->warn("{$sinGuia} sin número de guía: no se les puede preguntar. "
                . 'El número aparece al importar el PDF de Sistrack en Crear guías → PDF guías.');
        }

        if ($contando > 0) {
            $this->line("{$contando} dijeron entregado por primera vez: se mueven en la próxima revisión.");
        }

        return self::SUCCESS;
    }

    /** @return string|null qué pasó con esta conversación */
    private function revisarUna(WaConversacion $conv, ?WaEtiqueta $entregada): ?string
    {
        $guia = $this->guiaDe($conv);

        $conv->revisado_at = now();

        // Sin guía no hay a quién preguntarle. El número lo asigna Sistrack y
        // llega al panel cuando se importa el PDF de guías: hasta entonces,
        // esta conversación no se puede revisar y se quedaría en Preparados
        // para siempre sin que nada lo explique. Por eso se cuenta y se avisa.
        if (! $guia) {
            $conv->save();
            return 'sin-guia';
        }

        $conv->guia = $guia;

        // Regla 3: si ya está liquidada, se cobró y no hay nada más que vigilar.
        if ($this->yaLiquidada($guia)) {
            Etiquetado::marcarEntregada($conv);
            $conv->etapa_envio = 4;
            $conv->entregas_seguidas = 2;
            $conv->save();
            return 'cerrada';
        }

        $etapa = Order::etapaDeGuia($guia);
        $conv->etapa_envio = $etapa;

        $yaMarcada = $entregada && $conv->etiquetas()
            ->where('wa_etiquetas.id', $entregada->id)->exists();

        if ($etapa >= 4) {
            $conv->entregas_seguidas = min(9, (int) $conv->entregas_seguidas + 1);

            // Regla 1: dos seguidas antes de moverla.
            if ($conv->entregas_seguidas >= 2 && ! $yaMarcada) {
                Etiquetado::marcarEntregada($conv);
                $conv->save();
                return 'entregada';
            }

            $conv->save();

            // Primera vez que lo dice: falta una confirmación más.
            return $conv->entregas_seguidas === 1 ? 'primera' : null;
        }

        // Dice que NO está entregada. Se reinicia la cuenta.
        $conv->entregas_seguidas = 0;

        // Regla 2: si estaba marcada, el courier se desdijo. Vuelve.
        if ($yaMarcada) {
            Etiquetado::volverAPreparada($conv);
            $conv->save();
            return 'volvio';
        }

        $conv->save();
        return null;
    }

    /**
     * El número de guía de esta conversación.
     *
     * Se busca por teléfono en las guías registradas, que es la misma llave con
     * la que el cliente rastrea. Se queda con la más reciente: si una guía se
     * rehizo, la que vale es la última.
     */
    private function guiaDe(WaConversacion $conv): ?string
    {
        // Si ya se supo antes, no se vuelve a buscar salvo que haya una más
        // nueva; buscarla igual es barato y cubre las guías rehechas.
        try {
            $g = GuiaFoto::porTelefono($conv->telefono)->first();
            if ($g && filled($g->guia)) return (string) $g->guia;
        } catch (\Throwable $e) {
        }

        return filled($conv->guia) ? (string) $conv->guia : null;
    }

    /** ¿Esa guía ya apareció en una liquidación de Express? */
    private function yaLiquidada(string $guia): bool
    {
        try {
            if (! ExpressEntrega::hayTabla()) return false;

            return ExpressEntrega::where('orden', $guia)->exists();
        } catch (\Throwable $e) {
            return false;
        }
    }
}
