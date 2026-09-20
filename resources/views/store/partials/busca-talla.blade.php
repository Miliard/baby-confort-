{{--
    Buscador de talla por peso.

    Es la pregunta que Wil contesta a mano por WhatsApp todos los días: "¿cuánto
    pesa tu bebé?". Acá el cliente la responde solo, y de paso llega directo a
    la talla que le sirve en vez de irse a mirar seis.

    Los rangos salen de config/tallas_peso.php, que es la misma fuente que usa
    la guía de tallas y la tabla de precios del admin. Si un peso cambia, cambia
    en los tres lados a la vez — no hay nada escrito dos veces.
--}}
@php
    // Se leen los "desde–hasta" de cada rango para poder comparar números.
    // El texto viene como "6–11 kg · 13–24 lb", con guion largo.
    $rangos = [];

    foreach (config('tallas_peso', []) as $talla => $texto) {
        if (! preg_match('/([\d.]+)\s*[–\-]\s*([\d.]+)\s*kg/u', $texto, $m)) continue;

        $rangos[] = [
            'talla' => $talla,
            'desde' => (float) $m[1],
            'hasta' => (float) $m[2],
            'texto' => trim(explode('·', $texto)[0]),
            'url'   => route('store.talla', \Illuminate\Support\Str::slug($talla)),
        ];
    }

    usort($rangos, fn ($a, $b) => $a['desde'] <=> $b['desde']);
@endphp

@if($rangos)
<div class="buscatalla" x-data="{
    peso: '',
    rangos: @js($rangos),
    elegida: null,
    buscar() {
        const p = parseFloat(String(this.peso).replace(',', '.'));

        if (!p || p <= 0) { this.elegida = null; return; }

        // La primera talla donde el peso entra. Si está justo en el borde entre
        // dos, gana la más chica: un pañal que queda justo aprieta menos que
        // uno grande que se corre.
        let r = this.rangos.find(x => p >= x.desde && p <= x.hasta);

        // Más liviano que todo: la más chica. Más pesado: la más grande.
        if (!r) r = p < this.rangos[0].desde
            ? this.rangos[0]
            : this.rangos[this.rangos.length - 1];

        this.elegida = r;
    }
}">
    <h2 class="bt-t">¿Qué talla le queda a tu bebé?</h2>
    <p class="bt-p">Decinos cuánto pesa y te decimos la talla. Sin adivinar.</p>

    <form @submit.prevent="buscar()">
        <label class="bt-lab" for="bt-peso">Peso del bebé</label>

        <div class="bt-campo">
            {{-- inputmode decimal abre el teclado numérico en el teléfono, que
                 es donde se va a usar esto casi siempre. --}}
            <input id="bt-peso" x-model="peso" type="number"
                   inputmode="decimal" min="1" max="60" step="0.1"
                   placeholder="Ej. 7.5" aria-describedby="bt-ayuda"
                   @input="buscar()">
            <button class="bt-btn" type="submit">Ver talla</button>
        </div>

        <p class="bt-ayuda" id="bt-ayuda">
            En kilogramos. Si lo tenés en libras, dividilo entre 2.2.
        </p>

        {{-- role=status + aria-live: quien usa lector de pantalla se entera del
             resultado sin tener que ir a buscarlo. --}}
        <div class="bt-res" x-show="elegida" x-cloak role="status" aria-live="polite">
            <strong>Talla <span x-text="elegida?.talla"></span></strong>
            <span x-text="elegida?.texto"></span>
            <a class="bt-ver" :href="elegida?.url">
                Ver los productos de esta talla →
            </a>
        </div>
    </form>
</div>
@endif
