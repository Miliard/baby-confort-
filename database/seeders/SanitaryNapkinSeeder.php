<?php
namespace Database\Seeders;
use App\Models\Product;
use Illuminate\Database\Seeder;
class SanitaryNapkinSeeder extends Seeder
{
    public function run(): void
    {
        if (Product::where('name', 'Toalla sanitaria Comfy Super Soft 330mm')->exists()) return;
        $p = Product::create([
            'name' => 'Toalla sanitaria Comfy Super Soft 330mm',
            'brand' => 'Aiwina',
            'description' => 'Toalla sanitaria nocturna de 330mm, ideal para flujo abundante. Superficie ultra suave, núcleo de pulpa + SAP que absorbe rápido, canales anti-fugas, aroma fresco de té y empaque individual con sello reutilizable.',
            'image' => 'https://aiwina.com/cdn/shop/files/3306p.png?v=1737358673',
            'gallery' => [
                'https://aiwina.com/cdn/shop/files/03-Comfy_330mm_01.jpg?v=1737365053',
                'https://aiwina.com/cdn/shop/files/03-Comfy_330mm_02.jpg?v=1737365053',
                'https://aiwina.com/cdn/shop/files/03-Comfy_330mm_03.jpg?v=1737365053',
                'https://aiwina.com/cdn/shop/files/03-Comfy_330mm_04.jpg?v=1737365053',
            ],
            'features' => [
                ['icon' => "\u{1F319}", 'text' => 'Nocturna 330mm: extra larga para flujo abundante.'],
                ['icon' => "\u{1F4A7}", 'text' => 'Núcleo pulpa + SAP: absorbe y retiene rápido.'],
                ['icon' => "\u{1F343}", 'text' => 'Aroma fresco de té que neutraliza olores.'],
                ['icon' => "\u{2728}", 'text' => 'Superficie suave hot-air, seca todo el día.'],
            ],
            'made_in' => 'AUSTRALIA', 'badge' => 'Producto galardonado', 'active' => true,
        ]);
        $p->sizes()->create(['size'=>'6 uds (nocturna)','price'=>3,'quantity'=>25]);
    }
}
