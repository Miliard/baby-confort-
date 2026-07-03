<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $productos = [
            [
                'name'        => 'ComfyNite — Pantalón de noche',
                'brand'       => 'Aiwibi',
                'price'       => 10,
                'combo_qty'   => 3,
                'combo_price' => 25,
                'description' => 'Calzoncito de noche para niños grandes. Absorción hasta 12 horas, núcleo de pulpa suave, transpirable e hipoalergénico.',
                'image'       => 'https://aiwibi.com/cdn/shop/files/SM_1_a1c71c09-a4d3-45a7-a8ed-a1f33c8bac20.png?v=1762854515',
                'sizes'       => [
                    ['size' => 'S/M (17–29 kg)', 'quantity' => 20],
                    ['size' => 'L/XL (27–57 kg)', 'quantity' => 20],
                ],
            ],
            [
                'name'        => 'Magic Comfort — Calzoncito de bebé',
                'brand'       => 'Aiwibi',
                'price'       => 17,
                'combo_qty'   => null,
                'combo_price' => null,
                'description' => 'Calzoncito tipo pull-up premium. Cintura ultra suave sin marcas, núcleo ultra-fino de gran absorción e indicador de humedad.',
                'image'       => 'https://aiwibi.com/cdn/shop/files/1_7b063378-3d79-43c4-b220-7b22c9b61fd8.png?v=1751511916',
                'sizes'       => [
                    ['size' => 'M', 'quantity' => 15],
                    ['size' => 'L', 'quantity' => 15],
                    ['size' => 'XL', 'quantity' => 15],
                    ['size' => 'XXL', 'quantity' => 10],
                    ['size' => 'XXXL', 'quantity' => 10],
                ],
            ],
            [
                'name'        => 'Calzoncito de noche — Ultimate Comfort',
                'brand'       => 'Aiwibi',
                'price'       => 20,
                'combo_qty'   => null,
                'combo_price' => null,
                'description' => 'Calzoncito de noche premium. Absorbe hasta 2000 ml y protege hasta 14 horas, tela sedosa e indicador de humedad.',
                'image'       => 'https://aiwibi.com/cdn/shop/files/ultimate-comfort-baby-pants.png?v=1747708576',
                'sizes'       => [
                    ['size' => 'M', 'quantity' => 12],
                    ['size' => 'L', 'quantity' => 12],
                    ['size' => 'XL', 'quantity' => 12],
                    ['size' => 'XXL', 'quantity' => 8],
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
