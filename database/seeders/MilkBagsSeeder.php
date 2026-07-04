<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class MilkBagsSeeder extends Seeder
{
    public function run(): void
    {
        if (Product::where('name', 'Bolsas para almacenar leche materna')->exists()) {
            return;
        }

        $product = Product::create([
            'name'        => 'Bolsas para almacenar leche materna',
            'brand'       => 'Aiwibi',
            'description' => 'Bolsas pre-esterilizadas y listas para usar para guardar leche materna de forma segura e higiénica. Doble sello antifugas, doble abertura para llenar y verter sin contaminación, y medidas en oz y ml con área para anotar la fecha.',
            'image'       => 'https://aiwibi.com/cdn/shop/files/688aaa1f1ae81c1722c87fad426a37c1.png?v=1759130904',
            'gallery'     => [
                'https://aiwibi.com/cdn/shop/files/01_682c3113-f42b-4ce3-bbd4-27ec871dd6c4.jpg?v=1759130904',
                'https://aiwibi.com/cdn/shop/files/02_1054b726-fad7-4eb2-891f-bd0454e36493.jpg?v=1759130904',
                'https://aiwibi.com/cdn/shop/files/03_7ad2ea9e-6ec8-4e83-9925-8430bb76c229.jpg?v=1759130904',
                'https://aiwibi.com/cdn/shop/files/04_2c349833-96bc-4f30-a526-22f7b29cf76e.jpg?v=1759130904',
            ],
            'features'    => [
                ['icon' => '✅', 'text' => 'Pre-esterilizadas: listas para usar, sin lavar.'],
                ['icon' => '🔒', 'text' => 'Doble sello antifugas, resistente al congelador.'],
                ['icon' => '💧', 'text' => 'Doble abertura: llenar y verter sin contaminación.'],
                ['icon' => '📏', 'text' => 'Medidas en oz y ml (hasta 180 ml / 6 oz) y área para la fecha.'],
            ],
            'made_in'       => 'AUSTRALIA',
            'badge'         => 'Producto galardonado',
            'stock_warning' => 'Prácticas para guardar y congelar leche materna. Pocas unidades.',
            'active'        => true,
        ]);

        $product->sizes()->create([
            'size' => 'Paquete · 180 ml (6 oz)', 'price' => 6, 'quantity' => 30,
        ]);
    }
}
