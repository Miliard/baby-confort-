<?php
namespace Database\Seeders;
use App\Models\Product;
use Illuminate\Database\Seeder;
class OvernightPantySeeder extends Seeder
{
    public function run(): void
    {
        if (Product::where('name', 'Panti sanitario nocturno')->exists()) return;
        $p = Product::create([
            'name' => 'Panti sanitario nocturno',
            'brand' => 'Aiwina',
            'description' => 'Panti sanitario nocturno con protección 360°. Absorbe como 5 toallas regulares, se ajusta como ropa interior y no se corre. Tela sedosa y transpirable para dormir tranquila toda la noche.',
            'image' => 'https://aiwina.com/cdn/shop/files/S-M.png?v=1737360545',
            'gallery' => [
                'https://aiwina.com/cdn/shop/files/01_6088b1c9-32e5-4843-a655-a4068925fd11.jpg?v=1737360840',
                'https://aiwina.com/cdn/shop/files/02_76028e7d-0d38-4d26-a3ca-d4c38b81fc7c.jpg?v=1737360840',
                'https://aiwina.com/cdn/shop/files/03_50c5f004-de49-4d7e-a84f-bcf4c423e09d.jpg?v=1737360840',
                'https://aiwina.com/cdn/shop/files/04_46a756fc-e02f-4926-ac88-cd2963b3b0a3.jpg?v=1737360840',
            ],
            'features' => [
                ['icon' => "\u{1F311}", 'text' => 'Protección 360° para cualquier posición al dormir.'],
                ['icon' => "\u{1F4A7}", 'text' => 'Absorbe como 5 toallas regulares.'],
                ['icon' => "\u{1FA72}", 'text' => 'Se ajusta como ropa interior, no se corre.'],
                ['icon' => "\u{2705}", 'text' => 'Sin fluorescencia ni formaldehído, piel sensible.'],
            ],
            'made_in' => 'AUSTRALIA', 'badge' => 'Producto galardonado', 'active' => true,
        ]);
        foreach ([
            ['size'=>'S/M (3 uds)','price'=>2,'quantity'=>15,'image'=>'https://aiwina.com/cdn/shop/files/S-M.png?v=1737360545'],
            ['size'=>'L/XL (3 uds)','price'=>2,'quantity'=>15,'image'=>'https://aiwina.com/cdn/shop/files/L-XL.png?v=1737360737'],
            ['size'=>'XXL/XXXL (3 uds)','price'=>2,'quantity'=>15,'image'=>'https://aiwina.com/cdn/shop/files/XXL-XXXL.png?v=1737360840'],
        ] as $s) $p->sizes()->create($s);
    }
}
