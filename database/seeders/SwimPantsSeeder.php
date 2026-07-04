<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class SwimPantsSeeder extends Seeder
{
    public function run(): void
    {
        // Evita duplicar si ya existe
        if (Product::where('name', 'Calzoncitos de Natación')->exists()) {
            return;
        }

        $product = Product::create([
            'name'        => 'Calzoncitos de Natación',
            'brand'       => 'Aiwibi',
            'description' => 'Bañador desechable para bebés. Comodidad y protección en los juegos acuáticos, con película impermeable de PE que no se hincha en el agua, doble protección antifugas y cintura elástica 360°.',
            'image'       => 'https://aiwibi.com/cdn/shop/files/baby-swimming-pants.png?v=1747727044',
            'gallery'     => [
                'https://aiwibi.com/cdn/shop/files/baby-swimming-pants-01.jpg?v=1747727513',
                'https://aiwibi.com/cdn/shop/files/baby-swimming-pants-02.jpg?v=1747727565',
                'https://aiwibi.com/cdn/shop/files/baby-swimming-pants-03.jpg?v=1747727843',
                'https://aiwibi.com/cdn/shop/files/baby-swimming-pants-04.jpg?v=1747727906',
            ],
            'features'    => [
                ['icon' => '💧', 'text' => 'Película impermeable de PE: no se hincha en el agua.'],
                ['icon' => '🛡️', 'text' => 'Doble protección antifugas para jugar tranquilo.'],
                ['icon' => '🔄', 'text' => 'Cintura elástica 360° con libertad de movimiento.'],
                ['icon' => '🌬️', 'text' => 'Diseño transpirable que mantiene fresco a tu bebé.'],
                ['icon' => '👌', 'text' => 'Fácil de poner y quitar (easy-on, easy-off).'],
            ],
            'made_in'       => 'AUSTRALIA',
            'badge'         => 'Producto galardonado',
            'stock_warning' => 'Ideal para la temporada de piscina y playa. Pocas unidades disponibles.',
            'active'        => true,
        ]);

        $sizes = [
            ['size' => 'S',    'weight' => '4-8 kg',   'price' => 12, 'quantity' => 15, 'image' => 'https://aiwibi.com/cdn/shop/files/baby-swimming-pants-s-size.png?v=1747727090'],
            ['size' => 'M',    'weight' => '6-11 kg',  'price' => 12, 'quantity' => 15, 'image' => 'https://aiwibi.com/cdn/shop/files/baby-swimming-pants-m-size.png?v=1747727170'],
            ['size' => 'L',    'weight' => '9-14 kg',  'price' => 12, 'quantity' => 15, 'image' => 'https://aiwibi.com/cdn/shop/files/baby-swimming-pants-l-size.png?v=1747727274'],
            ['size' => 'XL',   'weight' => '12-17 kg', 'price' => 12, 'quantity' => 12, 'image' => 'https://aiwibi.com/cdn/shop/files/baby-swimming-pants-xl-size.png?v=1747727296'],
            ['size' => 'XXL',  'weight' => '15-21 kg', 'price' => 12, 'quantity' => 10, 'image' => 'https://aiwibi.com/cdn/shop/files/baby-swimming-pants-xxl-size.png?v=1747727337'],
            ['size' => 'XXXL', 'weight' => '≥18 kg',   'price' => 12, 'quantity' => 10, 'image' => 'https://aiwibi.com/cdn/shop/files/baby-swimming-pants-xxxl-size.png?v=1747727387'],
        ];
        foreach ($sizes as $s) {
            $product->sizes()->create($s);
        }
    }
}
