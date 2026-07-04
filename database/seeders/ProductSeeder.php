<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $featsPanal = [
            ['icon' => '🪶', 'text' => 'Tacto suave y ligero como una pluma.'],
            ['icon' => '🍼', 'text' => 'Facilita cada cambio en tu rutina diaria.'],
            ['icon' => '🌿', 'text' => 'Diseño hipoalergénico que cuida su piel.'],
            ['icon' => '💧', 'text' => 'Protección segura que mantiene su piel seca.'],
        ];

        $productos = [
            [
                'name'        => 'Pantalón de noche ComfyNite — niños de 4 a 7 y 8 a 14 años',
                'brand'       => 'Aiwibi',
                'description' => 'Calzoncito de noche para niños grandes. Absorción hasta 12 horas, núcleo de pulpa suave, transpirable e hipoalergénico.',
                'image'       => 'https://aiwibi.com/cdn/shop/files/SM_1_a1c71c09-a4d3-45a7-a8ed-a1f33c8bac20.png?v=1762854515',
                'features'    => $featsPanal,
                'made_in'     => 'AUSTRALIA',
                'badge'       => 'Producto galardonado',
                'stock_warning' => 'Este producto se agotó varias veces el año pasado. Te animamos a aprovechar esta oferta limitada. Solo está disponible aquí y no se vende en tiendas.',
                'sizes'       => [
                    ['size' => 'S/M', 'weight' => '17-29 kg', 'details' => "👦🩲 Pañal Infantil – Súper Absorbente\n✅ Ideal para niños de 4 a 7 años\n✅ Recomendado para peso de 17 a 29 kilos\n✅ Cintura ajustable de 75 a 90 cm\n✅ Presentación: Paquetes de 10 unidades\n💰 Precio individual: \$10 cada paquete\n🎉 PROMOCIÓN ESPECIAL 🎉\n💥 3 paquetes por \$25💥\nComodidad, discreción y máxima absorción para el día o la noche.\n📦 Disponible para entrega\n📲 Escríbenos para hacer tu pedido", 'price' => 10, 'quantity' => 20, 'combo_qty' => 3, 'combo_price' => 25],
                    ['size' => 'L/XL', 'weight' => '27-57 kg', 'price' => 10, 'quantity' => 20, 'combo_qty' => 3, 'combo_price' => 25],
                ],
            ],
            [
                'name'        => 'Pañales Ultra Suaves Hipoalergénicos',
                'brand'       => 'Aiwibi',
                'description' => 'Brinda a tu pequeño una comodidad absoluta con materiales que cuidan su piel sensible.',
                'image'       => 'https://aiwibi.com/cdn/shop/files/1_7b063378-3d79-43c4-b220-7b22c9b61fd8.png?v=1751511916',
                'features'    => $featsPanal,
                'made_in'     => 'AUSTRALIA',
                'badge'       => 'Producto galardonado',
                'stock_warning' => 'Este producto se agotó 32 veces el año pasado. Te animamos a aprovechar esta oferta limitada. Solo está disponible aquí y no se vende en tiendas.',
                'sizes'       => [
                    ['size' => 'M', 'weight' => '6-11 kg', 'price' => 17, 'quantity' => 15],
                    ['size' => 'L', 'weight' => '9-14 kg', 'price' => 17, 'quantity' => 15],
                    ['size' => 'XL', 'weight' => '12-17 kg', 'price' => 17, 'quantity' => 15],
                    ['size' => 'XXL', 'weight' => '15-21 kg', 'price' => 17, 'quantity' => 10],
                    ['size' => 'XXXL', 'weight' => '≥18 kg', 'price' => 17, 'quantity' => 10],
                ],
            ],
            [
                'name'        => 'Calzoncito de noche — Ultimate Comfort',
                'brand'       => 'Aiwibi',
                'description' => 'Calzoncito de noche premium. Absorbe hasta 2000 ml, protege hasta 14 horas, tela sedosa e indicador de humedad.',
                'image'       => 'https://aiwibi.com/cdn/shop/files/ultimate-comfort-baby-pants.png?v=1747708576',
                'features'    => $featsPanal,
                'made_in'     => 'AUSTRALIA',
                'badge'       => 'Producto galardonado',
                'stock_warning' => 'Pocas unidades disponibles. Aprovecha antes de que se agote.',
                'sizes'       => [
                    ['size' => 'M', 'weight' => '6-11 kg', 'price' => 20, 'quantity' => 12],
                    ['size' => 'L', 'weight' => '9-14 kg', 'price' => 20, 'quantity' => 12],
                    ['size' => 'XL', 'weight' => '12-17 kg', 'price' => 20, 'quantity' => 12],
                    ['size' => 'XXL', 'weight' => '15-21 kg', 'price' => 20, 'quantity' => 8],
                ],
            ],
        ];

        foreach ($productos as $data) {
            $sizes = $data['sizes'];
            unset($data['sizes']);
            $data['active'] = true;
            $product = Product::create($data);
            foreach ($sizes as $s) {
                $product->sizes()->create($s);
            }
        }
    }
}
