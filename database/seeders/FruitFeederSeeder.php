<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class FruitFeederSeeder extends Seeder
{
    public function run(): void
    {
        if (Product::where('name', 'Comedero de fruta fresca (chupón mordedor)')->exists()) {
            return;
        }

        $product = Product::create([
            'name'        => 'Comedero de fruta fresca (chupón mordedor)',
            'brand'       => 'Aiwibi',
            'description' => 'Comedero de fruta fresca de silicona de grado alimenticio que ayuda a tu bebé a descubrir nuevos sabores de forma segura. Sirve también de mordedor para aliviar las encías. Solo pasan trocitos pequeños y digeribles.',
            'image'       => 'https://aiwibi.com/cdn/shop/files/d2b70bbbbd28bd5a97d689c06ed96301_47ad3d48-4355-43a3-9d9d-6cd2ea0c5423.png?v=1738720323',
            'gallery'     => [
                'https://aiwibi.com/cdn/shop/files/1_a4443f82-7e81-415e-854a-6052e78ed280.jpg?v=1738720323',
                'https://aiwibi.com/cdn/shop/files/2_2b18bb0b-4c8f-4f15-ab23-4e0418fcd34b.jpg?v=1738720323',
                'https://aiwibi.com/cdn/shop/files/3_f3fad0e6-3831-4663-a945-1c8388233332.jpg?v=1738720323',
                'https://aiwibi.com/cdn/shop/files/4_9826a1d4-8cf2-4b9d-b9a5-c81832d9aba6.jpg?v=1738720323',
            ],
            'features'    => [
                ['icon' => '🌿', 'text' => '100% silicona de grado alimenticio, sin BPA.'],
                ['icon' => '🛡️', 'text' => 'Agujeros pequeños: reducen el riesgo de atragantamiento.'],
                ['icon' => '🦷', 'text' => 'Doble uso: comedero y mordedor para las encías.'],
                ['icon' => '🍓', 'text' => 'Para fruta, verdura o leche materna congelada.'],
                ['icon' => '🧼', 'text' => 'Higiénico y fácil de limpiar (apto lavavajillas).'],
            ],
            'made_in'       => 'AUSTRALIA',
            'badge'         => 'Producto galardonado',
            'stock_warning' => 'Ideal para la etapa de dentición y primeros sabores.',
            'active'        => true,
        ]);

        $sizes = [
            ['size' => 'Rosado', 'price' => 3, 'quantity' => 20, 'image' => 'https://aiwibi.com/cdn/shop/files/84cb6a3aba0d38a0f9fcd2b05a4bb50a_4026da5a-e70d-46e1-a373-a31ce33747c0.png?v=1738720323'],
            ['size' => 'Verde',  'price' => 3, 'quantity' => 20, 'image' => 'https://aiwibi.com/cdn/shop/files/7b581ee0ce56cdac8ebc71d4924c7349_6e2930b5-7092-463d-9673-8bfe4f40ca66.png?v=1738720323'],
        ];
        foreach ($sizes as $s) {
            $product->sizes()->create($s);
        }
    }
}
