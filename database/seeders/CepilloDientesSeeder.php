<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

/**
 * Carga la información oficial de Aiwibi para el Cepillo de Dientes Infantil Divertido
 * (galería, características y los 4 colores como presentaciones, cada uno con su foto).
 *
 * - Si el producto ya existe (el borrador "Cepillo de Dientes Infantil"), lo completa:
 *   la presentación "Único" se convierte en el color Rosa (conservando precio y stock)
 *   y se agregan los demás colores.
 * - Si no existe, lo crea INACTIVO (borrador) para revisar y activar en el admin.
 * - Idempotente: se puede correr varias veces sin duplicar nada.
 */
class CepilloDientesSeeder extends Seeder
{
    public function run(): void
    {
        $cdn = 'https://aiwibi-sv.com/cdn/shop/files/';

        $descripcion = "Nuestro cepillo de dientes para niños Aiwibi está especialmente diseñado "
            . "para la delicada boca de los bebés. Cuenta con miles de cerdas ultra suaves que "
            . "limpian los dientes a fondo mientras cuidan gentilmente las encías sensibles. Su "
            . "cabezal compacto se adapta cómodamente a bocas pequeñas, garantizando una "
            . "experiencia de cepillado suave y eficaz.\n\n"
            . "Las cerdas flexibles con puntas redondeadas evitan la irritación, y su diseño de "
            . "cerdas agrupadas permite un enjuague rápido que resiste el moho y los olores. El "
            . "mango de silicona antideslizante ofrece un agarre seguro para las manitas "
            . "pequeñas, con materiales 100% libres de BPA. ¡Sus adorables personajes y colores "
            . "vibrantes convierten el cepillado en un ritual divertido!";

        $galeria = [
            $cdn . '02_6d099259-468d-4b05-aba5-2e9683c6d6dc.jpg',
            $cdn . '03_c4fab9e8-3a24-407a-bd6b-e377dab73943.jpg',
            $cdn . '04_6e45cff5-c4f0-4c08-a47b-fd7e2c7d7a44.jpg',
            $cdn . '05_90ac4808-c724-42db-869d-e23ad70cd431.jpg',
            $cdn . '06_cd1ddde8-8ae0-4a58-8667-ae9214342489.jpg',
            $cdn . '07_f3c8aa13-0033-48d1-adf1-c5e981d9cfb4.jpg',
            $cdn . '08_f8f96929-76ec-482e-967c-77ced199cdeb.jpg',
            $cdn . '09_b08b108b-cb0f-40d1-8b85-16e31d18f3db.jpg',
            $cdn . '10_59b4d9f8-ed8f-4219-931c-74d363192f1b.jpg',
            $cdn . '11_01e11ec3-4251-41b4-88ab-84a80f9c760a.jpg',
        ];

        $caracteristicas = [
            ['icon' => '🪥', 'text' => 'Miles de cerdas ultrasuaves que limpian a fondo mientras protegen las encías delicadas.'],
            ['icon' => '🔵', 'text' => 'Puntas de filamento redondeadas pulidas a 0,12 mm: previenen la irritación y el daño a las encías.'],
            ['icon' => '👶', 'text' => 'Cabezal compacto diseñado para bocas pequeñas: llega a cada rincón difícil.'],
            ['icon' => '💧', 'text' => 'Cerdas agrupadas de enjuague rápido: resisten el moho y los olores, más higiene entre usos.'],
            ['icon' => '🤲', 'text' => 'Mango de silicona antideslizante, 100% libre de BPA y seguro para morder.'],
            ['icon' => '🐨', 'text' => 'Adorable diseño de koala y colores vibrantes: el cepillado se vuelve un juego diario.'],
        ];

        // Colores como presentaciones, cada uno con su foto oficial.
        $colores = [
            'Rosa'     => $cdn . 'pink.png',
            'Púrpura'  => $cdn . 'purple.png',
            'Verde'    => $cdn . 'green.png',
            'Amarillo' => $cdn . 'yellow.png',
        ];

        // Busca el borrador existente ("Cepillo de Dientes Infantil") o cualquier variante del nombre.
        $p = Product::where('name', 'like', 'Cepillo de dientes infantil%')->first();

        if (! $p) {
            $p = Product::create([
                'name'        => 'Cepillo de Dientes Infantil Divertido',
                'brand'       => 'Aiwibi',
                'categoria'   => 'accesorios',
                'description' => $descripcion,
                'image'       => $cdn . '3_b4d50ae8-01e8-4b45-830a-9b1e0170460f.jpg',
                'active'      => false, // borrador: revisar y activar en el admin
            ]);
        }

        // Completa el producto. La descripción corta del seeder viejo se reemplaza por la
        // oficial; cualquier otra descripción escrita por el dueño se respeta.
        $descVieja = 'Cepillo de dientes de cerdas suaves y diseño divertido, ideal para cuidar los primeros dientes de tu bebé.';
        $cambios = [];
        if ($p->name === 'Cepillo de Dientes Infantil')  $cambios['name'] = 'Cepillo de Dientes Infantil Divertido';
        if (empty($p->description) || trim((string) $p->description) === $descVieja) $cambios['description'] = $descripcion;
        if (empty($p->image) && empty($p->image_upload)) $cambios['image'] = $cdn . '3_b4d50ae8-01e8-4b45-830a-9b1e0170460f.jpg';
        if (empty($p->gallery))                          $cambios['gallery'] = $galeria;
        if (empty($p->features))                         $cambios['features'] = $caracteristicas;
        if (empty($p->categoria))                        $cambios['categoria'] = 'accesorios';
        if (! empty($cambios)) $p->update($cambios);

        // La presentación "Único" del borrador viejo se convierte en el primer color (Rosa),
        // conservando su precio y stock.
        $unico = $p->sizes()->where('size', 'Único')->first();
        if ($unico && ! $p->sizes()->where('size', 'Rosa')->exists()) {
            $unico->update(['size' => 'Rosa', 'image' => $colores['Rosa']]);
        }

        foreach ($colores as $color => $foto) {
            $s = $p->sizes()->where('size', $color)->first();
            if (! $s) {
                $p->sizes()->create([
                    'size'     => $color,
                    'price'    => 1.75,
                    'image'    => $foto,
                    'quantity' => 20,
                ]);
                continue;
            }
            // Ya existe: solo completa la foto si falta. Precio y stock no se tocan.
            if (empty($s->image) && empty($s->image_upload)) {
                $s->update(['image' => $foto]);
            }
        }
    }
}
