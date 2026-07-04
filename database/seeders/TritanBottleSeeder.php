<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class TritanBottleSeeder extends Seeder
{
    public function run(): void
    {
        if (Product::where('name', 'Biberón de Tritan con pajilla (irrompible)')->exists()) {
            return;
        }

        $product = Product::create([
            'name'        => 'Biberón de Tritan con pajilla (irrompible)',
            'brand'       => 'Aiwibi',
            'description' => 'Vaso-biberón de Tritan ligero e irrompible con pajilla de silicona suave. Bola de gravedad para succión 360° sin derrames, asas ergonómicas para que beba solito y medidas visibles. Material Tritan de Eastman, libre de BPA.',
            'image'       => 'https://aiwibi.com/cdn/shop/files/300_05f42224-436a-4945-8af9-dc25548fd1f6.png?v=1747876379',
            'gallery'     => [
                'https://aiwibi.com/cdn/shop/files/1_ab98a99c-18f8-4c78-b06c-1e9f6627e0d0.jpg?v=1747876379',
                'https://aiwibi.com/cdn/shop/files/2_82adb7d7-dce9-4bb9-9053-71fce96afcf0.jpg?v=1747876379',
                'https://aiwibi.com/cdn/shop/files/3_de8314de-bada-4bf6-a7fc-be2177dd4c9b.jpg?v=1747876379',
                'https://aiwibi.com/cdn/shop/files/4_09568a9b-1694-4fd8-97b1-bb97bf72fb7b.jpg?v=1747876379',
            ],
            'features'    => [
                ['icon' => '💪', 'text' => 'Material Tritan de Eastman: irrompible y sin BPA.'],
                ['icon' => '🔄', 'text' => 'Bola de gravedad: succión 360° sin derrames.'],
                ['icon' => '🥤', 'text' => 'Pajilla de silicona suave, cómoda para su garganta.'],
                ['icon' => '✋', 'text' => 'Asas ergonómicas: fomentan que beba solito.'],
                ['icon' => '📏', 'text' => 'Medidas visibles para controlar el líquido.'],
            ],
            'made_in'       => 'AUSTRALIA',
            'badge'         => 'Producto galardonado',
            'stock_warning' => 'Ideal para la transición del biberón al vaso. Pocas unidades disponibles.',
            'active'        => true,
        ]);

        $sizes = [
            ['size' => '300 ml (10 oz) - Azul',  'price' => 11.75, 'quantity' => 12, 'image' => 'https://aiwibi.com/cdn/shop/files/300_05f42224-436a-4945-8af9-dc25548fd1f6.png?v=1747876379'],
            ['size' => '300 ml (10 oz) - Verde', 'price' => 11.75, 'quantity' => 12, 'image' => 'https://aiwibi.com/cdn/shop/files/300_7874336c-6d72-4e77-8b6b-2a3cd8827fe7.png?v=1747876379'],
        ];
        foreach ($sizes as $s) {
            $product->sizes()->create($s);
        }
    }
}
