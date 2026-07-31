<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

/**
 * Carga la información oficial de Aiwibi para los Pañales Comfy Care 50un.
 * (galería, características y tallas con su foto, precio y unidades).
 *
 * - Si el producto ya existe (creado en el admin), SOLO rellena lo que esté vacío:
 *   nunca pisa textos, precios ni cantidades que el dueño ya haya puesto.
 * - Si no existe, lo crea INACTIVO (borrador) para revisarlo y activarlo en el admin.
 * - Es idempotente: se puede correr varias veces sin duplicar nada.
 *
 * Nota de tallas: Aiwibi los nombra P,M,G,XG,XXG,XXXG. Aquí se usan los nombres
 * S,M,L,XL,XXL,XXXL (equivalentes) para que funcionen las colecciones /talla/S…
 */
class ComfyCareSeeder extends Seeder
{
    public function run(): void
    {
        $cdn = 'https://aiwibi-sv.com/cdn/shop/files/';

        $descripcion = 'Los Pañales para Bebé Desechables Premium Aiwibi Hipoalergénicos están '
            . 'especialmente diseñados para los primeros meses del bebé, combinando suavidad, '
            . 'comodidad y un rendimiento superior. Fabricados con pulpa de madera natural '
            . 'certificada por FSC, su Núcleo Absorbente de PlantPower™ permite una dispersión '
            . 'rápida de líquidos y una absorción eficaz. Gracias a la Película MicroAiry™ '
            . 'transpirable y a la Lámina Superior con Patrón de Perlas 3D, estos pañales cuidan '
            . 'la piel sensible y reducen la fricción. La Cintura de Ajuste Cómodo™ y la Barrera '
            . 'Doble Antifugas brindan una protección ecológica y amorosa desde el primer día.';

        // URLs completas de la galería oficial (12 fotos).
        $galeria = [
            $cdn . '01__01_6874a54f-2a70-4264-a266-fd70c9265f9c.jpg',
            $cdn . '01__02_4d624fb6-e813-4566-9283-eee61c85b656.jpg',
            $cdn . '01__03_e67cf4f8-9285-4cd4-b602-2476051db273.jpg',
            $cdn . '01__04_7f007e2a-4285-4c3c-9cc6-d6d0b9fa32eb.jpg',
            $cdn . '01__05_29651dee-9197-4199-bd37-817c46928e13.jpg',
            $cdn . '01__06_5094280a-5779-470b-857f-8fb021f08243.jpg',
            $cdn . '01__07_c5189a1e-4673-4177-b7c9-a93d2239923e.jpg',
            $cdn . '01__08_e2713b03-62dc-4691-8129-2688896c86d4.jpg',
            $cdn . '01__09_8369e4d8-e4e4-4a97-aa21-83de48055f71.jpg',
            $cdn . '01__10_a88c91fa-59af-4cd2-a776-36a2d33dcfb6.jpg',
            $cdn . '01__11_621408f5-29e8-4659-83b9-8aa90bc5146c.jpg',
            $cdn . '01__12_f8dc64b1-22b5-4070-807e-2a56daa2b289.jpg',
        ];

        $caracteristicas = [
            ['icon' => '🌿', 'text' => 'Lámina superior ligeramente ácida: se adapta al pH de la piel del bebé, protección suave e hipoalergénica.'],
            ['icon' => '💨', 'text' => 'Película MicroAiry™ con millones de microporos: deja pasar el aire pero no el agua (+50% de transpirabilidad).'],
            ['icon' => '🍃', 'text' => '100% fibras naturales premium con rehumectación ultra baja: adiós a la irritación.'],
            ['icon' => '🌳', 'text' => 'Núcleo Absorbente PlantPower™ de pulpa de madera certificada FSC: absorción rápida y ecológica.'],
            ['icon' => '🛡️', 'text' => 'Protectores dobles contra fugas de 45 mm: ajuste seguro y sin preocupaciones.'],
            ['icon' => '🌙', 'text' => 'Hasta 12 horas de protección nocturna: absorbe hasta 800 ml y mantiene al bebé seco toda la noche.'],
            ['icon' => '✅', 'text' => 'Aprobado por dermatólogos alemanes (Dermatest): sin cloro, perfumes, lociones, parabenos ni ftalatos.'],
            ['icon' => '🤗', 'text' => 'Cintura de Ajuste Cómodo™: abraza la cintura sin apretar la pancita.'],
            ['icon' => '🔁', 'text' => 'Cintas mágicas reajustables e impermeables que soportan hasta 45 N sin despegarse.'],
            ['icon' => '💎', 'text' => 'Lámina con patrón de perlas 3D: menos fricción y +30% de transpirabilidad.'],
            ['icon' => '⚡', 'text' => 'Capa de secado rápido que dirige el líquido al núcleo y reduce el riesgo de dermatitis.'],
            ['icon' => '💧', 'text' => 'Indicador de humedad que cambia de color: sabrás al instante cuándo toca el cambio.'],
        ];

        // Tallas: nombre en la tienda => [foto oficial de la talla, peso]. La talla S (P de
        // Aiwibi) no tiene foto propia en el CDN: usa la imagen principal del producto.
        $tallas = [
            'S'    => [null,                  '4–8 kg · 9–18 lb'],
            'M'    => [$cdn . 'M50.png',      '6–11 kg · 13–24 lb'],
            'L'    => [$cdn . 'G50.png',      '9–14 kg · 20–31 lb'],
            'XL'   => [$cdn . 'XG50.png',     '12–17 kg · 26–37 lb'],
            'XXL'  => [$cdn . 'XXG50.png',    '15–21 kg · 33–46 lb'],
            'XXXL' => [$cdn . 'XXXG50_2.png', '18–25 kg · 39–55 lb'],
        ];

        // Busca el producto por su nombre de Aiwibi (creado en el admin) o lo crea como borrador.
        $p = Product::where('name', 'like', 'Pañales para Bebés Comfy Care%')->first();

        if (! $p) {
            $p = Product::create([
                'name'        => 'Pañales para Bebés Comfy Care Talla (P,M,G,XG,XXG,XXXG) Paquete 50un.',
                'brand'       => 'Aiwibi',
                'categoria'   => 'bebe',
                'description' => $descripcion,
                'image'       => $cdn . '222_1.jpg',
                'active'      => false, // borrador: revisar y activar en el admin
            ]);
        }

        // Rellena SOLO los campos que estén vacíos (no pisa lo que puso el dueño).
        $cambios = [];
        if (empty($p->description))            $cambios['description'] = $descripcion;
        if (empty($p->image) && empty($p->image_upload)) $cambios['image'] = $cdn . '222_1.jpg';
        if (empty($p->gallery))                $cambios['gallery'] = $galeria;
        if (empty($p->features))               $cambios['features'] = $caracteristicas;
        if (empty($p->made_in))                $cambios['made_in'] = 'AUSTRALIA';
        if (empty($p->categoria))              $cambios['categoria'] = 'bebe';
        if (! empty($cambios)) $p->update($cambios);

        foreach ($tallas as $nombre => [$foto, $peso]) {
            $s = $p->sizes()->where('size', $nombre)->first();
            if (! $s) {
                $p->sizes()->create([
                    'size'     => $nombre,
                    'weight'   => $peso,
                    'price'    => 17.00,
                    'unidades' => 50,
                    'image'    => $foto,
                    'quantity' => 20,
                ]);
                continue;
            }
            // La talla ya existe: solo completa lo vacío. Precio y stock no se tocan.
            $sc = [];
            if (empty($s->weight))                            $sc['weight'] = $peso;
            if (empty($s->unidades))                          $sc['unidades'] = 50;
            if (empty($s->image) && empty($s->image_upload) && $foto) $sc['image'] = $foto;
            if (! empty($sc)) $s->update($sc);
        }
    }
}
