<?php
namespace Database\Seeders;
use App\Models\Product;
use Illuminate\Database\Seeder;
class PreemieSeeder extends Seeder
{
    public function run(): void
    {
        if (Product::where('name', 'Pañal de prematuro Aiwibi')->exists()) return;
        $p = Product::create([
            'name' => 'Pañal de prematuro Aiwibi',
            'brand' => 'Aiwibi',
            'description' => 'Pañal diseñado para bebés prematuros y de bajo peso (menos de 3 kg). 20% más pequeño que talla recién nacido, núcleo ultra-fino de SAP japonés, recorte para el cordón umbilical y película transpirable. Ultra suave e hipoalergénico.',
            'image' => 'https://aiwibi.com/cdn/shop/files/aiwibi-preemie-diapers-packaging.png?v=1751521168',
            'gallery' => [
                'https://aiwibi.com/cdn/shop/files/01_c88acd11-2324-4ca4-bb4e-c7535b1d14a3.jpg?v=1760595341',
                'https://aiwibi.com/cdn/shop/files/02_f8cbad2c-e8c5-4bb7-8906-bd803ff71595.jpg?v=1760595341',
                'https://aiwibi.com/cdn/shop/files/03_6e6d46b5-ebb8-47b6-a602-3b8458094ca1.jpg?v=1760595341',
                'https://aiwibi.com/cdn/shop/files/04_8347e4cd-4a19-409b-a2b6-76e009c0895a.jpg?v=1760595341',
            ],
            'features' => [
                ['icon' => "\u{1F476}", 'text' => 'Para prematuros y bajo peso (menos de 3 kg).'],
                ['icon' => "\u{2702}", 'text' => 'Recorte para el cordón umbilical.'],
                ['icon' => "\u{1FAB6}", 'text' => 'Núcleo ultra-fino de SAP japonés, absorbe sin abultar.'],
                ['icon' => "\u{1F33F}", 'text' => 'Ultra suave e hipoalergénico, 0% fragancia.'],
            ],
            'made_in' => 'AUSTRALIA', 'badge' => 'Dermatest 5 estrellas',
            'stock_warning' => 'Talla especial para prematuros. Pocas unidades disponibles.',
            'active' => true,
        ]);
        $p->sizes()->create(['size'=>'Preemie','weight'=>'menos de 3 kg','price'=>7,'quantity'=>20]);
    }
}
