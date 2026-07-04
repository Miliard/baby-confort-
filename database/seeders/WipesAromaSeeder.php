<?php
namespace Database\Seeders;
use App\Models\Product;
use Illuminate\Database\Seeder;
class WipesAromaSeeder extends Seeder
{
    public function run(): void
    {
        if (Product::where('name', 'Toallitas Aroma Cuidado Sereno (Fresa)')->exists()) return;
        $p = Product::create([
            'name' => 'Toallitas Aroma Cuidado Sereno (Fresa)',
            'brand' => 'Aiwibi',
            'description' => 'Toallitas húmedas con agua natural de manantial australiano. Más grandes, gruesas y suaves, hidratan y limpian con delicadeza. Enriquecidas con extractos orgánicos y aroma natural de fresa, con tapa koala que evita contaminación y salida de una en una.',
            'image' => 'https://aiwibi.com/cdn/shop/files/50p_7aa519e0-9be6-4b71-909a-60790f1d7a15.png?v=1765870123',
            'gallery' => [
                'https://aiwibi.com/cdn/shop/files/1_0670d889-bf5e-4d55-8805-bb8bca7f4843.jpg?v=1765870123',
                'https://aiwibi.com/cdn/shop/files/2_cd638c56-546b-45ae-9178-1e9553bc8069.jpg?v=1765870123',
                'https://aiwibi.com/cdn/shop/files/3_f04636d8-9221-4809-baaf-c4947fb93af7.jpg?v=1765870123',
                'https://aiwibi.com/cdn/shop/files/4_61a77f1b-d1e8-486a-b071-32142c5fb15c.jpg?v=1765870123',
            ],
            'features' => [
                ['icon' => "\u{1F4A7}", 'text' => 'Agua natural de manantial australiano.'],
                ['icon' => "\u{1F353}", 'text' => 'Aroma natural de fresa, calmante y fresco.'],
                ['icon' => "\u{1F343}", 'text' => 'Extractos orgánicos: hidratan y cuidan la piel.'],
                ['icon' => "\u{1F428}", 'text' => 'Tapa koala: salen de una en una, sin contaminar.'],
            ],
            'made_in' => 'AUSTRALIA', 'badge' => 'Producto galardonado', 'active' => true,
        ]);
        foreach ([
            ['size'=>'Fresa · 50 uds','price'=>4,'quantity'=>20,'image'=>'https://aiwibi.com/cdn/shop/files/50p_7aa519e0-9be6-4b71-909a-60790f1d7a15.png?v=1765870123'],
            ['size'=>'Fresa · 80 uds','price'=>4,'quantity'=>20,'image'=>'https://aiwibi.com/cdn/shop/files/80p_b87256b2-957a-4f71-acbb-204995bba30c.png?v=1765870123'],
            ['size'=>'Fresa · 120 uds','price'=>4,'quantity'=>20,'image'=>'https://aiwibi.com/cdn/shop/files/120p_e40ad1fe-d5ba-4654-99d3-1af81558f0c8.png?v=1765870123'],
        ] as $s) $p->sizes()->create($s);
    }
}
