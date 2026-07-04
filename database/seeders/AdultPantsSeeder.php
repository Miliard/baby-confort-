<?php
namespace Database\Seeders;
use App\Models\Product;
use Illuminate\Database\Seeder;
class AdultPantsSeeder extends Seeder
{
    public function run(): void
    {
        if (Product::where('name', 'Pants para adulto AIWINA')->exists()) return;
        $p = Product::create([
            'name' => 'Pants para adulto AIWINA',
            'brand' => 'Aiwina',
            'description' => 'Pants absorbentes para adulto, unisex, tipo ropa interior. Súper absorbentes, mantienen la piel seca, con barreras antifugas 3D, indicador de humedad y superficie suave. Ideales para adultos mayores, post-operatorio y movilidad reducida.',
            'image' => 'https://aiwina.com/cdn/shop/files/M_a422a25c-f677-44a8-9ac9-51d0f2f12195.png?v=1776394004',
            'gallery' => [
                'https://aiwina.com/cdn/shop/files/3_01_52cf244a-f50e-4259-84f7-514eb7f91be7.jpg?v=1776394004',
                'https://aiwina.com/cdn/shop/files/3_02_5fbfa8b6-b27e-47d9-a5ce-9d12e188ba9e.jpg?v=1776394004',
                'https://aiwina.com/cdn/shop/files/3_03_a27d50d1-06cc-490b-bc13-0bdff62fef4b.jpg?v=1776394004',
                'https://aiwina.com/cdn/shop/files/3_04_7c90ba9b-55f2-4266-98c9-277442787bc1.jpg?v=1776394004',
            ],
            'features' => [
                ['icon' => "\u{1F9F8}", 'text' => 'Superficie suave, no irritante y transpirable.'],
                ['icon' => "\u{1F4A7}", 'text' => 'Doble núcleo absorbente: mantiene la piel seca.'],
                ['icon' => "\u{1F6E1}", 'text' => 'Barreras antifugas 3D contra escapes laterales.'],
                ['icon' => "\u{1F3F7}", 'text' => 'Indicador de humedad para cambiar a tiempo.'],
                ['icon' => "\u{2705}", 'text' => 'Hipoalergénico, sin cloro ni látex.'],
            ],
            'made_in' => 'AUSTRALIA', 'badge' => 'Producto galardonado', 'active' => true,
        ]);
        foreach ([
            ['size'=>'M (10 uds)','price'=>10,'quantity'=>15,'combo_qty'=>3,'combo_price'=>25,'image'=>'https://aiwina.com/cdn/shop/files/M_a422a25c-f677-44a8-9ac9-51d0f2f12195.png?v=1776394004'],
            ['size'=>'L (10 uds)','price'=>10,'quantity'=>15,'combo_qty'=>3,'combo_price'=>25,'image'=>'https://aiwina.com/cdn/shop/files/L_8aeb08af-2815-4b0b-bc2c-71bd36967086.png?v=1776393980'],
            ['size'=>'XL (10 uds)','price'=>10,'quantity'=>15,'combo_qty'=>3,'combo_price'=>25,'image'=>'https://aiwina.com/cdn/shop/files/XL_ebf53dfc-9f48-4a75-96be-cafe2dc8a6ee.png?v=1776393980'],
        ] as $s) $p->sizes()->create($s);
    }
}
