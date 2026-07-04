<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class PPSUBottleSeeder extends Seeder
{
    public function run(): void
    {
        if (Product::where('name', 'Biberón de PPSU anticólico resistente al calor')->exists()) {
            return;
        }

        $product = Product::create([
            'name'        => 'Biberón de PPSU anticólico resistente al calor',
            'brand'       => 'Aiwibi',
            'description' => 'Biberón de material PPSU de primera calidad: resistente al calor y muy durable. Tetina de silicona suave con forma de pecho, sistema anticólico avanzado y válvula antifugas. Asa ergonómica y bola de gravedad para succión 360°. Incluye tetina de repuesto y cepillo.',
            'image'       => 'https://aiwibi.com/cdn/shop/files/ppsu.png?v=1727580973',
            'gallery'     => [
                'https://aiwibi.com/cdn/shop/files/1_f86dbfc5-01bf-407e-a65e-423bd23efb91.jpg?v=1728974685',
                'https://aiwibi.com/cdn/shop/files/2_f81acf35-bfec-4202-b0eb-55d52888dc07.jpg?v=1728974679',
                'https://aiwibi.com/cdn/shop/files/3_cade40b9-65f9-4dad-97b6-efd0e9974652.jpg?v=1728974681',
                'https://aiwibi.com/cdn/shop/files/4_a00eddc1-f6ef-4229-988d-7b0c1e1ccbcc.jpg?v=1728974677',
            ],
            'features'    => [
                ['icon' => '🔥', 'text' => 'Material PPSU resistente al calor y muy durable.'],
                ['icon' => '🤱', 'text' => 'Tetina de silicona forma de pecho: transición fácil.'],
                ['icon' => '🌀', 'text' => 'Sistema anticólico avanzado: reduce la hinchazón.'],
                ['icon' => '🚫', 'text' => 'Válvula antifugas (triángulo invertido).'],
                ['icon' => '✋', 'text' => 'Asa ergonómica y bola de gravedad (succión 360°).'],
                ['icon' => '🎁', 'text' => 'Incluye tetina de repuesto y cepillo.'],
            ],
            'made_in'       => 'AUSTRALIA',
            'badge'         => 'Producto galardonado',
            'stock_warning' => 'Material premium resistente al calor. Pocas unidades disponibles.',
            'active'        => true,
        ]);

        $sizes = [
            ['size' => '180 ml (6 oz) - Rosa', 'price' => 10.50, 'quantity' => 12, 'image' => 'https://aiwibi.com/cdn/shop/files/180-_1.png?v=1727243709'],
            ['size' => '180 ml (6 oz) - Azul', 'price' => 10.50, 'quantity' => 12, 'image' => 'https://aiwibi.com/cdn/shop/files/180-_1_2b3d8ecb-b041-46aa-bdd2-51035f2bb08d.png?v=1727243709'],
            ['size' => '240 ml (8 oz) - Rosa', 'price' => 12.25, 'quantity' => 12, 'image' => 'https://aiwibi.com/cdn/shop/files/240-_1.png?v=1727243709'],
            ['size' => '240 ml (8 oz) - Verde', 'price' => 12.25, 'quantity' => 12, 'image' => 'https://aiwibi.com/cdn/shop/files/240-_1_966c6c54-589b-4133-9f90-37487118c7bc.png?v=1727243710'],
        ];
        foreach ($sizes as $s) {
            $product->sizes()->create($s);
        }
    }
}
