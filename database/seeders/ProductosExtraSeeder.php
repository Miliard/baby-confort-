<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductosExtraSeeder extends Seeder
{
    public function run(): void
    {
        // Productos nuevos (accesorios, cuidado y femeninos). Se crean INACTIVOS
        // como borradores: el dueño agrega la foto, revisa el precio y los activa.
        $productos = [
            ['Chupete de Silicona Calmante (Día y Noche)', 'Aiwibi', 'Chupete de silicona suave y segura, con versión para el día y para la noche. Ayuda a calmar y relajar a tu bebé.', 3.50],
            ['Babero de Silicona Plegable', 'Aiwibi', 'Babero de silicona con bolsillo recogedor. Impermeable, fácil de limpiar y se pliega para llevarlo a todos lados.', 3.50],
            ['Cepillo para Biberones', 'Aiwibi', 'Cepillo de cerdas suaves para limpiar biberones y tetinas a fondo, sin rayar.', 1.25],
            ['Cepillo de Dientes Infantil', 'Aiwibi', 'Cepillo de dientes de cerdas suaves y diseño divertido, ideal para cuidar los primeros dientes de tu bebé.', 1.75],
            ['Toallitas Secas 100% Biodegradables', 'Aiwibi', 'Toallitas secas suaves y biodegradables. Humedécelas con agua para una limpieza delicada y ecológica.', 2.50],
            ['Toalla Comprimida Portátil', 'Aiwibi', 'Toallas comprimidas prácticas para llevar. Se expanden con un poco de agua; ideales para salidas y viajes.', 2.50],
            ['Almohadillas de Lactancia Desechables', 'Aiwibi', 'Almohadillas absorbentes y discretas para la lactancia. Mantienen a mamá seca y cómoda todo el día.', 5.00],
            ['Parches Repelente de Mosquitos', 'Aiwibi', 'Parches con aceites naturales que ayudan a alejar a los mosquitos. Suaves con la piel del bebé.', 3.00],
            ['Bastoncillos de Algodón (200 unidades)', 'Aiwibi', 'Bastoncillos de algodón de punta redonda, suaves y seguros para la higiene delicada del bebé.', 2.00],
            ['Colchón Absorbente para Adultos', 'Aiwina', 'Colchón absorbente desechable que protege la cama. Alta absorción para mayor higiene y comodidad.', 4.50],
            ['Protectores Diarios Súper Suaves 160mm', 'Aiwina', 'Protectores diarios ultrafinos y transpirables para frescura y comodidad todos los días.', 1.00],
            ['Toalla Sanitaria Ultrafina 245mm', 'Aiwina', 'Toalla sanitaria ultrafina, suave y de alta absorción para uso diario.', 1.50],
            ['Toalla Sanitaria Ultrafina 420mm', 'Aiwina', 'Toalla sanitaria nocturna extra larga (420mm) para máxima protección y comodidad.', 1.50],
        ];

        foreach ($productos as [$nombre, $marca, $desc, $precio]) {
            if (Product::where('name', $nombre)->exists()) {
                continue;
            }
            $p = Product::create([
                'name'        => $nombre,
                'brand'       => $marca,
                'description' => $desc,
                'active'      => false,
            ]);
            $p->sizes()->create([
                'size'     => 'Único',
                'price'    => $precio,
                'quantity' => 20,
            ]);
        }
    }
}
