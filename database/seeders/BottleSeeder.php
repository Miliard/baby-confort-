<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class BottleSeeder extends Seeder
{
    public function run(): void
    {
        if (Product::where('name', 'Biberón de vidrio anti-cólico')->exists()) {
            return;
        }

        $product = Product::create([
            'name'        => 'Biberón de vidrio anti-cólico',
            'brand'       => 'Aiwibi',
            'description' => 'Biberón de vidrio con sistema anti-cólico para una alimentación más tranquila. Tetina con forma de pecho para una transición fácil, capa de silicona anti-astillas que protege si se cae, y vidrio resistente al calor. Libre de BPA, BPS y plomo.',
            'image'       => 'https://aiwibi.com/cdn/shop/files/120ml.png?v=1774579092',
            'gallery'     => [
                'https://aiwibi.com/cdn/shop/files/120ml__01.jpg?v=1774581944',
                'https://aiwibi.com/cdn/shop/files/120ml__02.jpg?v=1774581944',
                'https://aiwibi.com/cdn/shop/files/120ml__03.jpg?v=1774581944',
                'https://aiwibi.com/cdn/shop/files/120ml__04.jpg?v=1774581944',
            ],
            'features'    => [
                ['icon' => '🍼', 'text' => 'Sistema anti-cólico: reduce el aire y las molestias.'],
                ['icon' => '🤱', 'text' => 'Tetina forma de pecho: transición fácil del pecho al biberón.'],
                ['icon' => '🛡️', 'text' => 'Capa de silicona anti-astillas: protege si se cae.'],
                ['icon' => '🔥', 'text' => 'Vidrio resistente al calor, calienta la leche más rápido.'],
                ['icon' => '✅', 'text' => 'Libre de BPA, BPS y plomo. Materiales puros.'],
            ],
            'made_in'       => 'AUSTRALIA',
            'badge'         => 'Producto galardonado',
            'stock_warning' => 'Incluye tetina adicional de regalo. Pocas unidades disponibles.',
            'active'        => true,
        ]);

        $sizes = [
            ['size' => '120 ml (4 oz)', 'price' => 8, 'quantity' => 15, 'image' => 'https://aiwibi.com/cdn/shop/files/120ml.png?v=1774579092'],
        ];
        foreach ($sizes as $s) {
            $product->sizes()->create($s);
        }
    }
}
